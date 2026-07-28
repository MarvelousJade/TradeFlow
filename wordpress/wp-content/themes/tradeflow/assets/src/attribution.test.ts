import { beforeEach, describe, expect, it } from 'vitest';
import { getAttribution, trackEvent } from './attribution';

describe('campaign attribution', () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.dataLayer = [];
    window.history.replaceState(
      {},
      '',
      '/?utm_source=google&utm_medium=cpc&utm_campaign=summer-drains',
    );
  });

  it('captures and retains the first campaign touch', () => {
    const first = getAttribution();
    expect(first.utm_source).toBe('google');
    expect(first.utm_campaign).toBe('summer-drains');

    window.history.replaceState({}, '', '/?utm_source=newsletter');
    expect(getAttribution().utm_source).toBe('google');
  });

  it('pushes GTM-compatible events', () => {
    trackEvent('tradeflow_quote_start', { service: 'drain-repair' });
    expect(window.dataLayer).toContainEqual({
      event: 'tradeflow_quote_start',
      service: 'drain-repair',
    });
  });
});

