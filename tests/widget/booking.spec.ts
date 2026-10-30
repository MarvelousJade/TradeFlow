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
  await page.screenshot({
    path: test.info().outputPath('booking-success.png'),
    fullPage: true,
  });
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

test('retry after a lost response returns the original reference', async ({
  page,
}) => {
  // Stands in for the server: the first attempt is stored, then the
  // connection drops before the customer sees the response.
  const stored = new Map<string, string>();
  const keys: string[] = [];
  await page.route('**/demo-api/leads', async (route) => {
    const body = route.request().postData() ?? '';
    const key = /name="idempotency_key"\r\n\r\n([^\r]*)\r\n/.exec(body)?.[1] ?? '';
    keys.push(key);
    // Like the plugin, a missing key gets no replay, only the daily duplicate rule.
    const original = key ? stored.get(key) : undefined;
    if (original) {
      await route.fulfill({
        status: 200,
        json: { reference: original, status: 'new', statusUrl: '', message: 'Your request is in.' },
      });
    } else if (stored.size > 0) {
      await route.fulfill({
        status: 409,
        json: { message: 'We already received this request today.' },
      });
    } else {
      stored.set(key, 'TF-ORIGINAL');
      await route.abort('connectionreset');
    }
  });
  await page.goto('/demo/');
  await contactStep(page);
  await page.getByRole('button', { name: 'Send request' }).click();
  await expect(page.getByRole('alert')).toHaveText(
    'We could not send the request. Please try again.',
  );
  expect(keys).toHaveLength(1); // Still no automatic retry.

  await page.getByRole('button', { name: 'Send request' }).click();
  await expect(
    page.getByRole('heading', { name: 'Your request is in.' }),
  ).toBeVisible();
  await expect(page.getByText('TF-ORIGINAL', { exact: true })).toBeVisible();
  expect(keys).toHaveLength(2);
  expect(keys[0]).toMatch(
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
  );
  expect(keys[1]).toBe(keys[0]);
});

test('failed submission and back navigation preserve the draft for retry', async ({
  page,
}) => {
  let attempts = 0;
  const submitted: string[] = [];
  await page.route('**/demo-api/leads', async (route) => {
    attempts++;
    submitted.push(route.request().postData() ?? '');
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
  for (const body of submitted) {
    for (const [field, value] of Object.entries({
      customer_name: 'Demo Customer',
      email: 'demo@example.test',
      phone: '4165550198',
      postal_code: 'M5V 2T6',
      details: 'Basement drain backs up when laundry runs.',
      service_id: '1',
      area_id: '2',
      slot_start: bootstrap.slots[0].start,
      slot_end: bootstrap.slots[0].end,
    })) {
      expect(body).toContain(`name="${field}"\r\n\r\n${value}\r\n`);
    }
  }
});
