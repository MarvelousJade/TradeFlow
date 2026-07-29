import { expect, test } from '@playwright/test';

test('public homepage renders its booking entry point cleanly', async ({
  page,
}) => {
  await page.goto('/');
  await expect(
    page.getByRole('heading', { name: 'Book the fix. Get on with it.' }),
  ).toBeVisible();
  await expect(
    page
      .locator('#booking')
      .getByRole('heading', { name: 'Where can we help?' }),
  ).toBeVisible();
  await expect(page.locator('.tf-mobile-menu')).toBeHidden();
  await expect(page.getByText(/critical error/i)).toHaveCount(0);
});

test('customer can complete the local booking workflow', async ({ page }) => {
  await page.addInitScript(() => {
    const measuredWindow = window as Window & {
      __tradeFlowEventDurations?: number[];
    };
    measuredWindow.__tradeFlowEventDurations = [];
    if (PerformanceObserver.supportedEntryTypes.includes('event')) {
      new PerformanceObserver((list) => {
        measuredWindow.__tradeFlowEventDurations?.push(
          ...list.getEntries().map((entry) => entry.duration),
        );
      }).observe({
        type: 'event',
        buffered: true,
        durationThreshold: 16,
      } as PerformanceObserverInit & { durationThreshold: number });
    }
  });
  await page.goto('/services/drain-repair/toronto/?utm_source=playwright&utm_campaign=e2e');
  await expect(
    page.getByRole('heading', { name: /drain repair/i }).first(),
  ).toBeVisible();

  const booking = page.locator('#booking');
  await booking.getByLabel('Postal code').fill('M5V 2T6');
  await booking.getByRole('button', { name: 'Continue' }).click();

  await booking
    .getByLabel(/job details/i)
    .fill('The basement drain is backing up whenever the laundry runs.');
  await booking.getByRole('button', { name: 'Continue' }).click();

  await booking.locator('.tf-slot').first().click();
  await booking.getByRole('button', { name: 'Continue' }).click();

  const unique = Date.now();
  await booking.getByLabel('Full name').fill('Playwright Customer');
  await booking
    .getByLabel('Email', { exact: true })
    .fill(`customer-${unique}@example.test`);
  await booking.getByLabel('Phone', { exact: true }).fill('416-555-0198');
  await booking.getByLabel(/I agree/).check();
  await booking.getByRole('button', { name: 'Send request' }).click();

  await expect(
    booking.getByRole('heading', { name: 'Your request is in.' }),
  ).toBeVisible();
  await expect(booking.getByText(/TF-/)).toBeVisible();

  await page.waitForTimeout(100);
  const maxInteractionDuration = await page.evaluate(
    () =>
      Math.max(
        ...((window as Window & { __tradeFlowEventDurations?: number[] })
          .__tradeFlowEventDurations ?? [0]),
      ),
  );
  expect(maxInteractionDuration).toBeLessThan(200);
});

test('duplicate requests receive a clear conflict message', async ({ request }) => {
  const bootstrapResponse = await request.get('/wp-json/tradeflow/v1/bootstrap?service=drain-repair&area=toronto');
  const bootstrap = await bootstrapResponse.json();
  const uniqueEmail = `duplicate-${Date.now()}@example.test`;
  const fields = {
    customer_name: 'Duplicate Test',
    email: uniqueEmail,
    phone: '4165550177',
    service_id: String(bootstrap.services[0].id),
    area_id: String(bootstrap.areas.find((area: { slug: string }) => area.slug === 'toronto').id),
    postal_code: 'M5V2T6',
    details: 'A test request with enough detail to pass validation.',
    slot_start: bootstrap.slots[0].start,
    slot_end: bootstrap.slots[0].end,
  };

  const first = await request.post('/wp-json/tradeflow/v1/leads', {
    multipart: fields,
    headers: { 'X-WP-Nonce': bootstrap.nonce },
  });
  expect(first.status()).toBe(201);

  const second = await request.post('/wp-json/tradeflow/v1/leads', {
    multipart: fields,
    headers: { 'X-WP-Nonce': bootstrap.nonce },
  });
  expect(second.status()).toBe(409);
  expect((await second.json()).code).toBe('duplicate_lead');
});

test('staff can assign a technician and update lead status', async ({
  page,
  request,
}, testInfo) => {
  test.skip(testInfo.project.name === 'mobile', 'Admin workflow is covered once on desktop.');
  const password = process.env.WP_ADMIN_PASSWORD;
  const username = process.env.WP_ADMIN_USER ?? 'admin';
  test.skip(!password, 'Set WP_ADMIN_PASSWORD to run the staff workflow.');

  const bootstrapResponse = await request.get(
    '/wp-json/tradeflow/v1/bootstrap?service=drain-repair&area=toronto',
  );
  const bootstrap = await bootstrapResponse.json();
  const created = await request.post('/wp-json/tradeflow/v1/leads', {
    multipart: {
      customer_name: 'Dispatch Test',
      email: `dispatch-${Date.now()}@example.test`,
      phone: '4165550133',
      service_id: String(bootstrap.services[0].id),
      area_id: String(
        bootstrap.areas.find(
          (area: { slug: string }) => area.slug === 'toronto',
        ).id,
      ),
      postal_code: 'M5V2T6',
      details: 'Staff workflow verification for technician assignment.',
      slot_start: bootstrap.slots[0].start,
      slot_end: bootstrap.slots[0].end,
    },
    headers: { 'X-WP-Nonce': bootstrap.nonce },
  });
  expect(created.status()).toBe(201);
  const reference = (await created.json()).reference;

  await page.goto('/wp-login.php');
  await page.getByLabel('Username or Email Address').fill(username);
  await page.getByLabel('Password', { exact: true }).fill(password!);
  await page.getByRole('button', { name: 'Log In' }).click();
  await page.goto('/wp-admin/admin.php?page=tradeflow-leads');

  await expect(
    page.getByRole('heading', { name: 'Service requests' }),
  ).toBeVisible();
  const lead = page.locator('.tf-lead').filter({ hasText: reference });
  await expect(lead).toBeVisible();
  await expect(lead.getByRole('heading', { name: 'Attribution' })).toBeVisible();
  await lead.getByLabel('Technician').selectOption('Alex Morgan');
  await lead.getByLabel('Status').selectOption('assigned');
  await lead.getByRole('button', { name: 'Save & notify' }).click();

  await expect(page.getByText(/Request updated/)).toBeVisible();
  await expect(
    page
      .locator('.tf-lead')
      .filter({ hasText: reference })
      .locator('.tf-status', { hasText: 'Assigned' }),
  ).toBeVisible();
});
