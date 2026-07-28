<?php

if (!defined('ABSPATH') && !defined('TRADEFLOW_TESTING')) {
    exit;
}

final class TradeFlow_Validator
{
    public const STATUSES = ['new', 'confirmed', 'assigned', 'en_route', 'completed', 'cancelled'];

    public static function normalize_postal_code(string $postal_code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $postal_code));
    }

    public static function normalize_phone(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }

    public static function postal_code_is_eligible(string $postal_code, string $prefix_list): bool
    {
        $normalized = self::normalize_postal_code($postal_code);
        if (strlen($normalized) < 3) {
            return false;
        }

        $prefixes = array_filter(array_map(
            static fn (string $prefix): string => self::normalize_postal_code($prefix),
            explode(',', $prefix_list)
        ));

        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function dedupe_key(array $data, ?string $date_bucket = null): string
    {
        $parts = [
            strtolower(trim((string) ($data['email'] ?? ''))),
            self::normalize_phone((string) ($data['phone'] ?? '')),
            abs((int) ($data['service_id'] ?? 0)),
            abs((int) ($data['area_id'] ?? 0)),
            $date_bucket ?? gmdate('Y-m-d'),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public static function validate(array $data): array
    {
        $errors = [];
        $name = trim((string) ($data['customer_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = self::normalize_phone((string) ($data['phone'] ?? ''));
        $postal = self::normalize_postal_code((string) ($data['postal_code'] ?? ''));
        $details = trim((string) ($data['details'] ?? ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['customer_name'] = 'Enter your full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        if (strlen($postal) < 3 || strlen($postal) > 7) {
            $errors['postal_code'] = 'Enter a valid postal code.';
        }
        if (mb_strlen($details) < 12 || mb_strlen($details) > 2000) {
            $errors['details'] = 'Add at least 12 characters about the work needed.';
        }
        if (empty($data['service_id']) || empty($data['area_id'])) {
            $errors['service'] = 'Choose a service and service area.';
        }
        if (empty($data['slot_start']) || empty($data['slot_end'])) {
            $errors['slot'] = 'Choose an appointment window.';
        } elseif (!self::valid_slot((string) $data['slot_start'], (string) $data['slot_end'])) {
            $errors['slot'] = 'That appointment window is no longer available.';
        }

        return $errors;
    }

    public static function valid_slot(string $start, string $end): bool
    {
        try {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $start_at = new DateTimeImmutable($start, $timezone);
            $end_at = new DateTimeImmutable($end, $timezone);
            $now = new DateTimeImmutable('now', $timezone);
        } catch (Exception) {
            return false;
        }

        $duration = $end_at->getTimestamp() - $start_at->getTimestamp();
        return $start_at > $now
            && $start_at < $now->modify('+15 days')
            && in_array($duration, [4 * HOUR_IN_SECONDS], true)
            && in_array((int) $start_at->format('G'), [8, 12], true)
            && (int) $start_at->format('N') < 7;
    }
}

