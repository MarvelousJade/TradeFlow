export interface Service {
  id: number;
  name: string;
  slug: string;
  summary: string;
  selected: boolean;
  duration: number;
  basePrice: number;
  icon: string;
}

export interface Area {
  id: number;
  name: string;
  slug: string;
  summary: string;
  selected: boolean;
  city: string;
  phone: string;
}

export interface Slot {
  start: string;
  end: string;
  dateLabel: string;
  timeLabel: string;
}

export interface BootstrapData {
  nonce: string;
  services: Service[];
  areas: Area[];
  slots: Slot[];
  maxPhotos: number;
  maxPhotoBytes: number;
}

export interface BookingForm {
  serviceId: number;
  areaId: number;
  postalCode: string;
  details: string;
  photos: File[];
  slot?: Slot;
  customerName: string;
  email: string;
  phone: string;
  consent: boolean;
  website: string;
}

export interface BookingResponse {
  reference: string;
  status: string;
  statusUrl: string;
  message: string;
}

export type FieldErrors = Record<string, string>;

declare global {
  interface Window {
    tradeFlowConfig?: {
      restUrl: string;
      pageUrl: string;
      siteName: string;
    };
    dataLayer?: Array<Record<string, unknown>>;
    gtag?: (...args: unknown[]) => void;
  }
}

