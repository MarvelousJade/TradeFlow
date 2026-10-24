// Synthetic data only; not live availability.
export default {
  nonce: 'demo-only',
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
    {
      id: 3,
      name: 'Mississauga',
      slug: 'mississauga',
      summary: '',
      selected: false,
      city: 'Mississauga',
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
