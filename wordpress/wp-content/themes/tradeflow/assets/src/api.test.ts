import { afterEach, describe, expect, it, vi } from 'vitest';
import { newIdempotencyKey, submitBooking } from './api';
import type { BookingForm } from './types';

const uuidV4 =
  /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

const form: BookingForm = {
  serviceId: 1,
  areaId: 2,
  postalCode: 'M5V 2T6',
  details: 'Basement drain backs up when laundry runs.',
  photos: [],
  slot: {
    start: '2027-01-04T08:00:00-05:00',
    end: '2027-01-04T12:00:00-05:00',
    dateLabel: 'Mon, Jan 4',
    timeLabel: '8 am – 12 pm',
  },
  customerName: 'Demo Customer',
  email: 'demo@example.test',
  phone: '4165550198',
  consent: true,
  website: '',
};

describe('idempotency keys', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it('creates distinct UUID v4 keys', () => {
    const first = newIdempotencyKey();
    const second = newIdempotencyKey();
    expect(first).toMatch(uuidV4);
    expect(second).toMatch(uuidV4);
    expect(first).not.toBe(second);
  });

  it('builds a valid v4 key when randomUUID is unavailable', () => {
    vi.stubGlobal('crypto', {
      getRandomValues: crypto.getRandomValues.bind(crypto),
    });
    for (let i = 0; i < 50; i++) expect(newIdempotencyKey()).toMatch(uuidV4);
  });

  it('sends the given key with the booking', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        reference: 'TF-1',
        status: 'new',
        statusUrl: '',
        message: '',
      }),
    });
    vi.stubGlobal('fetch', fetchMock);
    const key = '3f0c2a52-6a8e-4c4b-9d1e-0b9f5e7a2c11';

    await submitBooking(form, 'nonce', {} as never, key);

    const body = fetchMock.mock.calls[0][1].body as FormData;
    expect(body.get('idempotency_key')).toBe(key);
  });
});
