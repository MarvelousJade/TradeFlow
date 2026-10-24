import {
  act,
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

  it.each(['success', 'ineligible', 'network error'])(
    'ignores an obsolete eligibility %s after a postal edit',
    async (outcome) => {
      let resolve!: (value: unknown) => void;
      let reject!: (reason: Error) => void;
      const pending = new Promise((yes, no) => {
        resolve = yes;
        reject = no;
      });
      vi.stubGlobal(
        'fetch',
        vi
          .fn()
          .mockResolvedValueOnce({ ok: true, json: async () => bootstrap })
          .mockReturnValueOnce(pending),
      );
      render(
        <BookingWidget
          serviceSlug="drain-repair"
          areaSlug="toronto"
          heading="Book"
        />,
      );
      const postal = await screen.findByLabelText('Postal code');
      fireEvent.change(postal, { target: { value: 'M5V 2T6' } });
      fireEvent.click(screen.getByRole('button', { name: /continue/i }));
      fireEvent.change(postal, { target: { value: 'L5B 1M2' } });
      await act(async () => {
        if (outcome === 'network error') reject(new Error('Obsolete failure'));
        else
          resolve({
            ok: true,
            json: async () => ({
              eligible: outcome === 'success',
              message: 'Obsolete coverage',
              area: 'Toronto',
            }),
          });
      });
      expect(screen.getByText('Where can we help?')).toBeInTheDocument();
      expect(postal).toHaveValue('L5B 1M2');
      expect(screen.queryByText(/Obsolete/)).not.toBeInTheDocument();
      expect(screen.getByRole('button', { name: /continue/i })).toBeEnabled();
    },
  );

  it('keeps the newer area check pending when an older request finishes', async () => {
    let finishOld!: (value: unknown) => void;
    let finishNew!: (value: unknown) => void;
    const oldRequest = new Promise((resolve) => {
      finishOld = resolve;
    });
    const newRequest = new Promise((resolve) => {
      finishNew = resolve;
    });
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          ...bootstrap,
          areas: [
            ...bootstrap.areas,
            {
              ...bootstrap.areas[0],
              id: 3,
              name: 'Mississauga',
              selected: false,
            },
          ],
        }),
      })
      .mockReturnValueOnce(oldRequest)
      .mockReturnValueOnce(newRequest);
    vi.stubGlobal('fetch', fetchMock);
    render(
      <BookingWidget
        serviceSlug="drain-repair"
        areaSlug="toronto"
        heading="Book"
      />,
    );
    fireEvent.change(await screen.findByLabelText('Postal code'), {
      target: { value: 'M5V 2T6' },
    });
    fireEvent.click(screen.getByRole('button', { name: /continue/i }));
    fireEvent.change(screen.getByLabelText('Service area'), {
      target: { value: '3' },
    });
    fireEvent.click(screen.getByRole('button', { name: /continue/i }));
    expect(fetchMock).toHaveBeenCalledTimes(3);
    await act(async () =>
      finishOld({
        ok: true,
        json: async () => ({ eligible: true, message: 'Old' }),
      }),
    );
    expect(screen.getByRole('button', { name: /checking/i })).toBeDisabled();
    expect(screen.getByText('Where can we help?')).toBeInTheDocument();
    await act(async () =>
      finishNew({
        ok: true,
        json: async () => ({
          eligible: false,
          message: 'Outside current area',
        }),
      }),
    );
    expect(screen.getByText('Outside current area')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /continue/i })).toBeEnabled();
  });

  it('recovers from bootstrap failure with an explicit retry', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockRejectedValueOnce(new Error('Network unavailable'))
        .mockResolvedValueOnce({ ok: true, json: async () => bootstrap }),
    );
    render(
      <BookingWidget
        serviceSlug="drain-repair"
        areaSlug="toronto"
        heading="Book"
      />,
    );
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Network unavailable',
    );
    fireEvent.click(screen.getByRole('button', { name: 'Retry loading' }));
    expect(await screen.findByLabelText('Postal code')).toHaveValue('');
    expect(screen.queryByText('Network unavailable')).not.toBeInTheDocument();
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
