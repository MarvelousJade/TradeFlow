import { useEffect, useMemo, useRef, useState } from 'react';
import {
  BookingApiError,
  checkEligibility,
  fetchBootstrap,
  submitBooking,
} from './api';
import { getAttribution, trackEvent } from './attribution';
import { Icon } from './Icon';
import type {
  BookingForm,
  BookingResponse,
  BootstrapData,
  FieldErrors,
  Slot,
} from './types';

interface Props {
  serviceSlug: string;
  areaSlug: string;
  heading: string;
}

const initialForm: BookingForm = {
  serviceId: 0,
  areaId: 0,
  postalCode: '',
  details: '',
  photos: [],
  customerName: '',
  email: '',
  phone: '',
  consent: false,
  website: '',
};

const stepLabels = ['Service & area', 'Job details', 'Arrival window', 'Contact'];

export function BookingWidget({ serviceSlug, areaSlug, heading }: Props) {
  const [data, setData] = useState<BootstrapData | null>(null);
  const [form, setForm] = useState<BookingForm>(initialForm);
  const [step, setStep] = useState(0);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [eligibility, setEligibility] = useState('');
  const [result, setResult] = useState<BookingResponse | null>(null);
  const started = useRef(false);

  useEffect(() => {
    let active = true;
    fetchBootstrap(serviceSlug, areaSlug)
      .then((payload) => {
        if (!active) return;
        const selectedService =
          payload.services.find((service) => service.selected) ??
          payload.services[0];
        const selectedArea =
          payload.areas.find((area) => area.selected) ?? payload.areas[0];
        setData(payload);
        setForm((current) => ({
          ...current,
          serviceId: selectedService?.id ?? 0,
          areaId: selectedArea?.id ?? 0,
        }));
      })
      .catch((caught: unknown) => {
        if (active)
          setError(
            caught instanceof Error
              ? caught.message
              : 'Booking is temporarily unavailable.',
          );
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [serviceSlug, areaSlug]);

  useEffect(() => {
    trackEvent('tradeflow_booking_step_view', {
      step: step + 1,
      step_name: stepLabels[step],
    });
  }, [step]);

  const selectedService = data?.services.find(
    (service) => service.id === form.serviceId,
  );
  const selectedArea = data?.areas.find((area) => area.id === form.areaId);

  const groupedSlots = useMemo(() => {
    const groups = new Map<string, Slot[]>();
    data?.slots.forEach((slot) => {
      groups.set(slot.dateLabel, [...(groups.get(slot.dateLabel) ?? []), slot]);
    });
    return groups;
  }, [data]);

  const startQuote = () => {
    if (started.current) return;
    started.current = true;
    trackEvent('tradeflow_quote_start', {
      service: selectedService?.slug ?? '',
      area: selectedArea?.slug ?? '',
      landing_page: window.location.pathname,
    });
  };

  const update = <K extends keyof BookingForm>(
    key: K,
    value: BookingForm[K],
  ) => {
    startQuote();
    setForm((current) => ({ ...current, [key]: value }));
    setFieldErrors((current) => {
      const next = { ...current };
      delete next[key];
      if (key === 'serviceId' || key === 'areaId') delete next.service;
      return next;
    });
    setError('');
    if (key === 'postalCode' || key === 'areaId') setEligibility('');
  };

  const validateStep = (): FieldErrors => {
    const errors: FieldErrors = {};
    if (step === 0) {
      if (!form.serviceId) errors.service = 'Choose a service.';
      if (!form.areaId) errors.areaId = 'Choose a service area.';
      if (form.postalCode.replace(/\s/g, '').length < 3)
        errors.postalCode = 'Enter a valid postal code.';
    }
    if (step === 1 && form.details.trim().length < 12)
      errors.details = 'Add at least 12 characters about the work needed.';
    if (step === 2 && !form.slot)
      errors.slot = 'Choose an appointment window.';
    if (step === 3) {
      if (form.customerName.trim().length < 2)
        errors.customerName = 'Enter your full name.';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email))
        errors.email = 'Enter a valid email address.';
      if (form.phone.replace(/\D/g, '').length < 10)
        errors.phone = 'Enter a valid phone number.';
      if (!form.consent) errors.consent = 'Confirm that we may contact you.';
    }
    return errors;
  };

  const next = async () => {
    const errors = validateStep();
    setFieldErrors(errors);
    if (Object.keys(errors).length) return;

    if (step === 0) {
      setSubmitting(true);
      try {
        const response = await checkEligibility(form.areaId, form.postalCode);
        setEligibility(response.message);
        if (!response.eligible) {
          setFieldErrors({ postalCode: response.message });
          return;
        }
      } catch (caught) {
        setError(
          caught instanceof Error
            ? caught.message
            : 'We could not check that postal code.',
        );
        return;
      } finally {
        setSubmitting(false);
      }
    }

    if (step < stepLabels.length - 1) {
      setStep((current) => current + 1);
      setError('');
      setFieldErrors({});
    }
  };

  const submit = async () => {
    const errors = validateStep();
    setFieldErrors(errors);
    if (Object.keys(errors).length || !data) return;
    setSubmitting(true);
    setError('');
    try {
      const response = await submitBooking(
        form,
        data.nonce,
        getAttribution(),
      );
      setResult(response);
      trackEvent('tradeflow_booking_complete', {
        reference: response.reference,
        service: selectedService?.slug ?? '',
        area: selectedArea?.slug ?? '',
        value: selectedService?.basePrice ?? 0,
        currency: 'CAD',
      });
      trackEvent('generate_lead', {
        currency: 'CAD',
        value: selectedService?.basePrice ?? 0,
      });
    } catch (caught) {
      if (caught instanceof BookingApiError) {
        setError(caught.message);
        setFieldErrors(caught.fields);
      } else {
        setError('We could not send the request. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const addPhotos = (files: FileList | null) => {
    if (!data || !files) return;
    const accepted = Array.from(files).filter(
      (file) =>
        ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) &&
        file.size <= data.maxPhotoBytes,
    );
    const nextPhotos = [...form.photos, ...accepted].slice(0, data.maxPhotos);
    update('photos', nextPhotos);
    if (accepted.length !== files.length) {
      setFieldErrors((current) => ({
        ...current,
        photos: 'Use JPG, PNG, or WebP photos under 5 MB.',
      }));
    }
  };

  if (loading) {
    return (
      <div className="tf-booking tf-booking-loading" role="status">
        <span className="tf-spinner" />
        Loading local availability…
      </div>
    );
  }

  if (!data || (error && !data)) {
    return (
      <div className="tf-booking tf-success" role="alert">
        <h3>Booking is temporarily unavailable.</h3>
        <p>{error || 'Call the local team and we will help you book.'}</p>
      </div>
    );
  }

  if (result) {
    return (
      <div className="tf-booking tf-success" aria-live="polite">
        <span className="tf-success__icon">
          <Icon name="check" />
        </span>
        <h3>Your request is in.</h3>
        <p>{result.message}</p>
        <p className="tf-success__reference">
          Reference <strong>{result.reference}</strong>
        </p>
        <div className="tf-success__next">
          <div>
            <span>Now</span>
            <strong>Request received</strong>
          </div>
          <div>
            <span>Next</span>
            <strong>Staff review</strong>
          </div>
          <div>
            <span>Then</span>
            <strong>Email confirmation</strong>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="tf-booking" onPointerDown={startQuote}>
      <div className="tf-booking__header">
        <div>
          <strong>{heading}</strong>
          <p>{stepLabels[step]}</p>
        </div>
        <span className="tf-booking__step-count">
          {step + 1} / {stepLabels.length}
        </span>
      </div>
      <div className="tf-progress" aria-hidden="true">
        <span style={{ width: `${((step + 1) / stepLabels.length) * 100}%` }} />
      </div>

      <div className="tf-booking__body">
        {error && (
          <div className="tf-form-alert" role="alert">
            {error}
          </div>
        )}
        {step === 0 && (
          <ServiceStep
            data={data}
            form={form}
            update={update}
            errors={fieldErrors}
            eligibility={eligibility}
          />
        )}
        {step === 1 && (
          <DetailsStep
            form={form}
            update={update}
            addPhotos={addPhotos}
            maxPhotos={data.maxPhotos}
            errors={fieldErrors}
          />
        )}
        {step === 2 && (
          <ScheduleStep
            groups={groupedSlots}
            form={form}
            update={update}
            errors={fieldErrors}
          />
        )}
        {step === 3 && (
          <ContactStep
            form={form}
            update={update}
            errors={fieldErrors}
            serviceName={selectedService?.name ?? ''}
            areaName={selectedArea?.name ?? ''}
          />
        )}
      </div>

      <div className="tf-booking__footer">
        {step > 0 ? (
          <button
            className="tf-link-button"
            type="button"
            onClick={() => setStep((current) => current - 1)}
            disabled={submitting}
          >
            Back
          </button>
        ) : (
          <span />
        )}
        <button
          className="tf-button"
          type="button"
          onClick={step === 3 ? submit : next}
          disabled={submitting}
        >
          {submitting
            ? step === 3
              ? 'Sending…'
              : 'Checking…'
            : step === 3
              ? 'Send request'
              : 'Continue'}
          {!submitting && <Icon name="arrow" />}
        </button>
      </div>
    </div>
  );
}

interface StepProps {
  form: BookingForm;
  update: <K extends keyof BookingForm>(key: K, value: BookingForm[K]) => void;
  errors: FieldErrors;
}

function ServiceStep({
  data,
  form,
  update,
  errors,
  eligibility,
}: StepProps & { data: BootstrapData; eligibility: string }) {
  return (
    <>
      <h3>Where can we help?</h3>
      <p className="tf-booking__intro">
        Choose the job and enter the service address postal code.
      </p>
      <fieldset className="tf-field" style={{ border: 0, padding: 0 }}>
        <legend className="tf-field__label">Service needed</legend>
        <div className="tf-choice-grid">
          {data.services.map((service) => (
            <label className="tf-choice" key={service.id}>
              <input
                type="radio"
                name="service"
                value={service.id}
                checked={form.serviceId === service.id}
                onChange={() => update('serviceId', service.id)}
              />
              <strong>{service.name}</strong>
              <span>Assessment from ${service.basePrice}</span>
            </label>
          ))}
        </div>
        {errors.service && <p className="tf-field-error">{errors.service}</p>}
      </fieldset>
      <div className="tf-field-grid tf-field-grid--stack">
        <div className="tf-field">
          <label htmlFor="tf-area">Service area</label>
          <select
            id="tf-area"
            value={form.areaId}
            onChange={(event) => update('areaId', Number(event.target.value))}
            aria-invalid={Boolean(errors.areaId)}
          >
            {data.areas.map((area) => (
              <option value={area.id} key={area.id}>
                {area.name}
              </option>
            ))}
          </select>
          {errors.areaId && (
            <p className="tf-field-error">{errors.areaId}</p>
          )}
        </div>
        <div className="tf-field">
          <label htmlFor="tf-postal">Postal code</label>
          <input
            id="tf-postal"
            autoComplete="postal-code"
            placeholder="M5V 2T6"
            value={form.postalCode}
            onChange={(event) => update('postalCode', event.target.value)}
            aria-invalid={Boolean(errors.postalCode)}
            aria-describedby={errors.postalCode ? 'tf-postal-error' : undefined}
          />
          {errors.postalCode && (
            <p className="tf-field-error" id="tf-postal-error">
              {errors.postalCode}
            </p>
          )}
        </div>
      </div>
      {eligibility && !errors.postalCode && (
        <div className="tf-eligibility-note">
          <Icon name="pin" />
          {eligibility}
        </div>
      )}
    </>
  );
}

function DetailsStep({
  form,
  update,
  addPhotos,
  maxPhotos,
  errors,
}: StepProps & {
  addPhotos: (files: FileList | null) => void;
  maxPhotos: number;
}) {
  return (
    <>
      <h3>What is going on?</h3>
      <p className="tf-booking__intro">
        A little context helps us send the right person and equipment.
      </p>
      <div className="tf-field">
        <label htmlFor="tf-details">
          Job details <span>(required)</span>
        </label>
        <textarea
          id="tf-details"
          value={form.details}
          onChange={(event) => update('details', event.target.value)}
          placeholder="For example: the basement floor drain started backing up this morning…"
          aria-invalid={Boolean(errors.details)}
        />
        {errors.details && (
          <p className="tf-field-error">{errors.details}</p>
        )}
      </div>
      <label className="tf-upload">
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          onChange={(event) => addPhotos(event.target.files)}
          disabled={form.photos.length >= maxPhotos}
        />
        <Icon name="camera" />
        <strong>
          {form.photos.length >= maxPhotos
            ? 'Photo limit reached'
            : 'Add helpful photos'}
        </strong>
        <span>Up to {maxPhotos} JPG, PNG, or WebP files · 5 MB each</span>
      </label>
      {errors.photos && <p className="tf-field-error">{errors.photos}</p>}
      {form.photos.length > 0 && (
        <div className="tf-photo-list" aria-label="Selected photos">
          {form.photos.map((photo, index) => (
            <PhotoPreview
              photo={photo}
              key={`${photo.name}-${photo.lastModified}`}
              remove={() =>
                update(
                  'photos',
                  form.photos.filter((_, photoIndex) => photoIndex !== index),
                )
              }
            />
          ))}
        </div>
      )}
    </>
  );
}

function PhotoPreview({ photo, remove }: { photo: File; remove: () => void }) {
  const [url, setUrl] = useState('');
  useEffect(() => {
    const nextUrl = URL.createObjectURL(photo);
    setUrl(nextUrl);
    return () => URL.revokeObjectURL(nextUrl);
  }, [photo]);

  return (
    <span className="tf-photo">
      {url && <img src={url} alt="" />}
      <button type="button" onClick={remove} aria-label={`Remove ${photo.name}`}>
        <Icon name="close" />
      </button>
    </span>
  );
}

function ScheduleStep({
  groups,
  form,
  update,
  errors,
}: StepProps & { groups: Map<string, Slot[]> }) {
  return (
    <>
      <h3>Choose an arrival window.</h3>
      <p className="tf-booking__intro">
        Staff will confirm this request after reviewing the job details.
      </p>
      <div className="tf-date-groups">
        {Array.from(groups.entries()).map(([date, slots]) => (
          <div className="tf-date-group" key={date}>
            <h4>{date}</h4>
            <div className="tf-slot-grid">
              {slots.map((slot) => (
                <button
                  className="tf-slot"
                  type="button"
                  key={slot.start}
                  aria-pressed={form.slot?.start === slot.start}
                  onClick={() => update('slot', slot)}
                >
                  {slot.timeLabel}
                </button>
              ))}
            </div>
          </div>
        ))}
      </div>
      {errors.slot && <p className="tf-field-error">{errors.slot}</p>}
    </>
  );
}

function ContactStep({
  form,
  update,
  errors,
  serviceName,
  areaName,
}: StepProps & { serviceName: string; areaName: string }) {
  return (
    <>
      <h3>Where should we send the confirmation?</h3>
      <p className="tf-booking__intro">
        We use these details only to coordinate this service request.
      </p>
      <dl className="tf-review">
        <div>
          <dt>Service</dt>
          <dd>{serviceName}</dd>
        </div>
        <div>
          <dt>Area</dt>
          <dd>
            {areaName} · {form.postalCode.toUpperCase()}
          </dd>
        </div>
        <div>
          <dt>Requested window</dt>
          <dd>
            {form.slot?.dateLabel} · {form.slot?.timeLabel}
          </dd>
        </div>
      </dl>
      <div className="tf-field">
        <label htmlFor="tf-name">Full name</label>
        <input
          id="tf-name"
          autoComplete="name"
          value={form.customerName}
          onChange={(event) => update('customerName', event.target.value)}
          aria-invalid={Boolean(errors.customerName)}
        />
        {errors.customerName && (
          <p className="tf-field-error">{errors.customerName}</p>
        )}
      </div>
      <div className="tf-field-grid tf-field-grid--stack">
        <div className="tf-field">
          <label htmlFor="tf-email">Email</label>
          <input
            id="tf-email"
            type="email"
            autoComplete="email"
            value={form.email}
            onChange={(event) => update('email', event.target.value)}
            aria-invalid={Boolean(errors.email)}
          />
          {errors.email && <p className="tf-field-error">{errors.email}</p>}
        </div>
        <div className="tf-field">
          <label htmlFor="tf-phone">Phone</label>
          <input
            id="tf-phone"
            type="tel"
            autoComplete="tel"
            value={form.phone}
            onChange={(event) => update('phone', event.target.value)}
            aria-invalid={Boolean(errors.phone)}
          />
          {errors.phone && <p className="tf-field-error">{errors.phone}</p>}
        </div>
      </div>
      <label className="tf-consent">
        <input
          type="checkbox"
          checked={form.consent}
          onChange={(event) => update('consent', event.target.checked)}
        />
        <span>
          I agree that TradeFlow may contact me by email or phone about this
          request. Message and data rates may apply.
        </span>
      </label>
      {errors.consent && <p className="tf-field-error">{errors.consent}</p>}
      <label className="tf-honeypot" aria-hidden="true">
        Website
        <input
          tabIndex={-1}
          autoComplete="off"
          value={form.website}
          onChange={(event) => update('website', event.target.value)}
        />
      </label>
    </>
  );
}

