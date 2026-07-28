import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { BookingWidget } from './BookingWidget';

const bootstrap = {
  nonce: 'nonce',
  services: [
    {
      id: 1,
      name: 'Drain repair',
      slug: 'drain-repair',
      summary: '',
      selected: true,
      duration: 120,
      basePrice: 149,
      icon: 'drain',
    },
  ],
  areas: [
    {
      id: 2,
      name: 'Toronto',
      slug: 'toronto',
      summary: '',
      selected: true,
      city: 'Toronto',
      phone: '4165550147',
    },
  ],
  slots: [
    {
      start: '2027-01-04T08:00:00-05:00',
      end: '2027-01-04T12:00:00-05:00',
      dateLabel: 'Mon, Jan 4',
      timeLabel: '8 am – 12 pm',
    },
  ],
  maxPhotos: 3,
  maxPhotoBytes: 5242880,
};

describe('BookingWidget', () => {
  beforeEach(() => {
    window.tradeFlowConfig = {
      restUrl: '/wp-json/tradeflow/v1',
      pageUrl: 'http://localhost/',
      siteName: 'TradeFlow',
    };
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => bootstrap,
      }),
    );
  });

  afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
  });

  it('loads location-specific services and validates the postal code', async () => {
    render(
      <BookingWidget
        serviceSlug="drain-repair"
        areaSlug="toronto"
        heading="Request drain repair"
      />,
    );
    expect(await screen.findByText('Where can we help?')).toBeInTheDocument();
    expect(screen.getByText('Drain repair')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /continue/i }));
    expect(
      await screen.findByText('Enter a valid postal code.'),
    ).toBeInTheDocument();
  });

  it('continues after an eligible postal-code response', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({
        ok: true,
        json: async () => bootstrap,
      })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          eligible: true,
          area: 'Toronto',
          message: 'Great — this address is in our Toronto service area.',
        }),
      });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <BookingWidget
        serviceSlug="drain-repair"
        areaSlug="toronto"
        heading="Request drain repair"
      />,
    );
    const postal = await screen.findByLabelText('Postal code');
    fireEvent.change(postal, { target: { value: 'M5V 2T6' } });
    fireEvent.click(screen.getByRole('button', { name: /continue/i }));

    await waitFor(() =>
      expect(screen.getByText('What is going on?')).toBeInTheDocument(),
    );
  });
});
