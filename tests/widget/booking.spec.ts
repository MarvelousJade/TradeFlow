import { expect, test, type Page } from '@playwright/test';
import bootstrap from '../../demo/bootstrap';

async function contactStep(page: Page) {
  await page.getByLabel('Postal code').fill('M5V 2T6');
  await page.getByRole('button', { name: 'Continue' }).click();
  await page
    .getByLabel(/job details/i)
    .fill('Basement drain backs up when laundry runs.');
  await page.getByRole('button', { name: 'Continue' }).click();
  await page.getByRole('button', { name: '8 am – 12 pm' }).click();
  await page.getByRole('button', { name: 'Continue' }).click();
  await page.getByLabel('Full name').fill('Demo Customer');
  await page.getByLabel('Email', { exact: true }).fill('demo@example.test');
  await page.getByLabel('Phone', { exact: true }).fill('4165550198');
  await page.getByLabel(/I agree/).check();
}

test('demo completes with real widget and disposable API', async ({ page }) => {
  await page.goto('/demo/');
  await expect(page.getByRole('note')).toContainText('no booking saved');
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.getByText('Enter a valid postal code.')).toBeVisible();
  await contactStep(page);
  await page.getByRole('button', { name: 'Send request' }).click();
  await expect(
    page.getByRole('heading', { name: 'Your request is in.' }),
  ).toBeVisible();
  await expect(page.getByText('TF-DEMO', { exact: true })).toBeVisible();
});

test('read errors recover and outside coverage stays on address step', async ({
  page,
}) => {
  let attempts = 0;
  await page.route('**/demo-api/bootstrap?*', async (route) => {
    attempts++;
    if (attempts === 1) await route.abort();
    else await route.fulfill({ json: bootstrap });
  });
  await page.goto('/demo/');
  await expect(page.getByRole('alert')).toBeVisible();
  await page.getByRole('button', { name: 'Retry loading' }).click();
  await page.getByLabel('Postal code').fill('L5B 1M2');
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.getByText(/Outside demo coverage/)).toBeVisible();
  await expect(
    page.getByRole('heading', { name: 'Where can we help?' }),
  ).toBeVisible();
  await page.getByLabel('Service area').selectOption('3');
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.getByLabel(/job details/i)).toBeVisible();
});

test('edited address ignores an old approval in the browser', async ({
  page,
}) => {
  let release!: () => void;
  const gate = new Promise<void>((resolve) => {
    release = resolve;
  });
  await page.route('**/demo-api/eligibility', async (route) => {
    await gate;
    await route.fulfill({
      json: { eligible: true, area: 'Toronto', message: 'Old approval' },
    });
  });
  await page.goto('/demo/');
  await page.getByLabel('Postal code').fill('M5V 2T6');
  const requested = page.waitForRequest('**/demo-api/eligibility');
  await page.getByRole('button', { name: 'Continue' }).click();
  await requested;
  await page.getByLabel('Postal code').fill('L5B 1M2');
  const responded = page.waitForResponse('**/demo-api/eligibility');
  release();
  await responded;
  await expect(page.getByRole('button', { name: 'Continue' })).toBeEnabled();
  await expect(
    page.getByRole('heading', { name: 'Where can we help?' }),
  ).toBeVisible();
  await expect(page.getByLabel('Postal code')).toHaveValue('L5B 1M2');
  await expect(page.getByText('Old approval')).toHaveCount(0);
});

test('failed submission and back navigation preserve the draft for retry', async ({
  page,
}) => {
  let attempts = 0;
  await page.route('**/demo-api/leads', async (route) => {
    attempts++;
    if (attempts === 1)
      await route.fulfill({
        status: 503,
        json: { message: 'Please retry later.' },
      });
    else await route.continue();
  });
  await page.goto('/demo/');
  await contactStep(page);
  await page.getByRole('button', { name: 'Send request' }).click();
  await expect(page.getByRole('alert')).toHaveText('Please retry later.');
  expect(attempts).toBe(1); // No automatic retry of a potentially accepted write.
  await expect(page.getByLabel('Email', { exact: true })).toHaveValue(
    'demo@example.test',
  );
  await page.getByRole('button', { name: 'Back', exact: true }).click();
  await page.getByRole('button', { name: 'Back', exact: true }).click();
  await expect(page.getByLabel(/job details/i)).toHaveValue(
    'Basement drain backs up when laundry runs.',
  );
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(
    page.getByRole('button', { name: '8 am – 12 pm' }),
  ).toHaveAttribute('aria-pressed', 'true');
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.getByLabel(/I agree/)).toBeChecked();
  await page.getByRole('button', { name: 'Send request' }).click();
  await expect(
    page.getByRole('heading', { name: 'Your request is in.' }),
  ).toBeVisible();
  expect(attempts).toBe(2);
});
