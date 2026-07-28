import type {
  BookingForm,
  BookingResponse,
  BootstrapData,
  FieldErrors,
} from './types';
import type { Attribution } from './attribution';

export class BookingApiError extends Error {
  fields: FieldErrors;
  status: number;

  constructor(message: string, status: number, fields: FieldErrors = {}) {
    super(message);
    this.name = 'BookingApiError';
    this.fields = fields;
    this.status = status;
  }
}

const apiUrl = (path: string): string => {
  const base = window.tradeFlowConfig?.restUrl ?? '/wp-json/tradeflow/v1';
  return `${base.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;
};

async function parsedResponse<T>(response: Response): Promise<T> {
  const payload = await response.json();
  if (!response.ok) {
    throw new BookingApiError(
      payload?.message ?? 'Something went wrong. Please try again.',
      response.status,
      payload?.data?.fields ?? {},
    );
  }
  return payload as T;
}

export async function fetchBootstrap(
  service: string,
  area: string,
): Promise<BootstrapData> {
  const params = new URLSearchParams({ service, area });
  const response = await fetch(`${apiUrl('bootstrap')}?${params}`, {
    headers: { Accept: 'application/json' },
  });
  return parsedResponse<BootstrapData>(response);
}

export async function checkEligibility(
  areaId: number,
  postalCode: string,
): Promise<{ eligible: boolean; area: string; message: string }> {
  const response = await fetch(apiUrl('eligibility'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ area_id: areaId, postal_code: postalCode }),
  });
  return parsedResponse(response);
}

export async function submitBooking(
  form: BookingForm,
  nonce: string,
  attribution: Attribution,
): Promise<BookingResponse> {
  if (!form.slot) {
    throw new BookingApiError('Choose an appointment window.', 422, {
      slot: 'Choose an appointment window.',
    });
  }

  const body = new FormData();
  const values: Record<string, string> = {
    customer_name: form.customerName,
    email: form.email,
    phone: form.phone,
    service_id: String(form.serviceId),
    area_id: String(form.areaId),
    postal_code: form.postalCode,
    details: form.details,
    slot_start: form.slot.start,
    slot_end: form.slot.end,
    website: form.website,
    ...attribution,
  };
  Object.entries(values).forEach(([key, value]) => body.append(key, value));
  form.photos.forEach((photo) => body.append('photos[]', photo, photo.name));

  const response = await fetch(apiUrl('leads'), {
    method: 'POST',
    headers: { 'X-WP-Nonce': nonce, Accept: 'application/json' },
    body,
  });
  return parsedResponse<BookingResponse>(response);
}

