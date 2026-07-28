<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TradeFlow_Notifications
{
    public static function init(): void
    {
        add_filter('wp_mail_content_type', static fn (): string => 'text/html');
    }

    public static function send_confirmation(array $lead): void
    {
        $service = get_the_title((int) $lead['service_id']);
        $area = get_the_title((int) $lead['area_id']);
        $subject = sprintf(__('We received request %s', 'tradeflow'), $lead['reference']);
        $message = self::layout(
            __('Your request is in', 'tradeflow'),
            sprintf(
                '<p>Hi %1$s,</p><p>We received your request for <strong>%2$s</strong> in %3$s. Your requested arrival window is <strong>%4$s</strong>.</p><p>Reference: <strong>%5$s</strong></p><p>We will confirm the appointment after a quick review.</p>',
                esc_html($lead['customer_name']),
                esc_html($service),
                esc_html($area),
                esc_html(self::format_window($lead['slot_start'], $lead['slot_end'])),
                esc_html($lead['reference'])
            )
        );

        wp_mail($lead['email'], $subject, $message);

        $admin_email = get_option('admin_email');
        wp_mail(
            $admin_email,
            sprintf(__('New TradeFlow request: %s', 'tradeflow'), $lead['reference']),
            self::layout(
                __('New service request', 'tradeflow'),
                sprintf(
                    '<p><strong>%1$s</strong> requested %2$s in %3$s.</p><p>%4$s · %5$s</p><p><a href="%6$s">Review in WordPress</a></p>',
                    esc_html($lead['customer_name']),
                    esc_html($service),
                    esc_html($area),
                    esc_html($lead['email']),
                    esc_html($lead['phone']),
                    esc_url(admin_url('admin.php?page=tradeflow-leads'))
                )
            )
        );
    }

    public static function send_status_update(array $lead, string $previous_status): void
    {
        if ($previous_status === $lead['status']) {
            return;
        }

        $labels = [
            'confirmed' => ['Appointment confirmed', 'Your requested appointment window is confirmed.'],
            'assigned' => ['Technician assigned', sprintf('%s has been assigned to your request.', $lead['technician'])],
            'en_route' => ['Your technician is on the way', sprintf('%s is heading to your location.', $lead['technician'])],
            'completed' => ['Service complete', 'Thanks for choosing TradeFlow. Your service request is marked complete.'],
            'cancelled' => ['Appointment update', 'Your service request has been cancelled. Reply to this email if you need help rebooking.'],
        ];

        if (!isset($labels[$lead['status']])) {
            return;
        }

        [$subject, $copy] = $labels[$lead['status']];
        wp_mail(
            $lead['email'],
            sprintf('%s — %s', $subject, $lead['reference']),
            self::layout(
                esc_html($subject),
                sprintf(
                    '<p>Hi %1$s,</p><p>%2$s</p><p>Reference: <strong>%3$s</strong></p>',
                    esc_html($lead['customer_name']),
                    esc_html($copy),
                    esc_html($lead['reference'])
                )
            )
        );
    }

    private static function format_window(string $start, string $end): string
    {
        return wp_date('D, M j · g a', strtotime($start)) . '–' . wp_date('g a', strtotime($end));
    }

    private static function layout(string $heading, string $content): string
    {
        return sprintf(
            '<div style="background:#f3f5ef;padding:32px;font-family:Arial,sans-serif;color:#13231d"><div style="max-width:560px;margin:auto;background:#fff;padding:32px;border-radius:16px"><p style="font-weight:800;letter-spacing:.08em;color:#187c5a">TRADEFLOW</p><h1 style="font-size:26px">%1$s</h1>%2$s</div></div>',
            $heading,
            $content
        );
    }
}

