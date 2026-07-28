import { createRoot } from 'react-dom/client';
import { BookingWidget } from './BookingWidget';

document.querySelectorAll<HTMLElement>('.tf-booking-root').forEach((element) => {
  createRoot(element).render(
    <BookingWidget
      serviceSlug={element.dataset.service ?? ''}
      areaSlug={element.dataset.area ?? ''}
      heading={element.dataset.heading ?? 'Request your free quote'}
    />,
  );
});

