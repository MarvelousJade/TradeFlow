export interface Attribution {
  landing_page: string;
  referrer: string;
  utm_source: string;
  utm_medium: string;
  utm_campaign: string;
  utm_content: string;
  utm_term: string;
  gclid: string;
}

const STORAGE_KEY = 'tradeflow_first_touch';
const CAMPAIGN_KEYS = [
  'utm_source',
  'utm_medium',
  'utm_campaign',
  'utm_content',
  'utm_term',
  'gclid',
] as const;

export function getAttribution(): Attribution {
  const fallback: Attribution = {
    landing_page: window.location.href,
    referrer: document.referrer,
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
    utm_content: '',
    utm_term: '',
    gclid: '',
  };

  try {
    const existing = window.localStorage.getItem(STORAGE_KEY);
    if (existing) {
      return { ...fallback, ...(JSON.parse(existing) as Attribution) };
    }

    const params = new URLSearchParams(window.location.search);
    CAMPAIGN_KEYS.forEach((key) => {
      fallback[key] = params.get(key)?.slice(0, 190) ?? '';
    });
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(fallback));
  } catch {
    // Booking must keep working when storage is disabled.
  }
  return fallback;
}

export function trackEvent(
  event: string,
  properties: Record<string, unknown> = {},
): void {
  const payload = { event, ...properties };
  window.dataLayer = window.dataLayer ?? [];
  window.dataLayer.push(payload);
  window.gtag?.('event', event, properties);
}

