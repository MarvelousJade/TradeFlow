<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TradeFlow_Installer
{
    public static function activate(): void
    {
        self::create_tables();
        TradeFlow_Post_Types::register();
        TradeFlow_Post_Types::register_rewrites();
        self::seed_content();
        update_option('tradeflow_db_version', TRADEFLOW_VERSION);
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    private static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $leads = $wpdb->prefix . 'tradeflow_leads';
        $appointments = $wpdb->prefix . 'tradeflow_appointments';

        dbDelta("CREATE TABLE {$leads} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reference varchar(24) NOT NULL,
            public_token char(64) NOT NULL,
            customer_name varchar(190) NOT NULL,
            email varchar(190) NOT NULL,
            phone varchar(40) NOT NULL,
            service_id bigint(20) unsigned NOT NULL,
            area_id bigint(20) unsigned NOT NULL,
            postal_code varchar(12) NOT NULL,
            details text NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'new',
            technician varchar(190) NOT NULL DEFAULT '',
            appointment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            photo_ids text NULL,
            landing_page text NULL,
            referrer text NULL,
            utm_source varchar(190) NOT NULL DEFAULT '',
            utm_medium varchar(190) NOT NULL DEFAULT '',
            utm_campaign varchar(190) NOT NULL DEFAULT '',
            utm_content varchar(190) NOT NULL DEFAULT '',
            utm_term varchar(190) NOT NULL DEFAULT '',
            gclid varchar(190) NOT NULL DEFAULT '',
            dedupe_key char(64) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reference (reference),
            UNIQUE KEY public_token (public_token),
            UNIQUE KEY dedupe_key (dedupe_key),
            KEY status_created (status, created_at),
            KEY service_area (service_id, area_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$appointments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            starts_at datetime NOT NULL,
            ends_at datetime NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'requested',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY lead_id (lead_id),
            KEY starts_at (starts_at),
            KEY status_starts (status, starts_at)
        ) {$charset};");
    }

    private static function seed_content(): void
    {
        if (get_posts(['post_type' => 'tf_service', 'numberposts' => 1, 'fields' => 'ids'])) {
            return;
        }

        $services = [
            [
                'title' => 'Drain repair',
                'excerpt' => 'Fast diagnosis and lasting repairs for blocked, slow, or damaged drains.',
                'content' => 'From stubborn kitchen clogs to damaged main lines, our licensed technicians diagnose the cause, explain the options, and leave the work area clean.',
                'meta' => ['tf_duration' => 120, 'tf_base_price' => 149, 'tf_icon' => 'drain'],
            ],
            [
                'title' => 'Water heater service',
                'excerpt' => 'Repair, replacement, and maintenance for reliable hot water.',
                'content' => 'We service tank and tankless systems with clear recommendations based on safety, efficiency, and the expected life of your equipment.',
                'meta' => ['tf_duration' => 180, 'tf_base_price' => 189, 'tf_icon' => 'heater'],
            ],
            [
                'title' => 'Leak detection',
                'excerpt' => 'Pinpoint hidden leaks before they become expensive damage.',
                'content' => 'Our technicians use non-invasive diagnostics to locate supply, fixture, and concealed plumbing leaks.',
                'meta' => ['tf_duration' => 120, 'tf_base_price' => 129, 'tf_icon' => 'drop'],
            ],
        ];

        foreach ($services as $service) {
            wp_insert_post([
                'post_type' => 'tf_service',
                'post_status' => 'publish',
                'post_title' => $service['title'],
                'post_excerpt' => $service['excerpt'],
                'post_content' => $service['content'],
                'meta_input' => $service['meta'],
            ]);
        }

        $areas = [
            [
                'title' => 'Toronto',
                'content' => 'Serving Toronto homes and small businesses with scheduled and priority plumbing appointments.',
                'meta' => [
                    'tf_city' => 'Toronto',
                    'tf_postal_prefixes' => 'M1,M2,M3,M4,M5,M6,M8,M9',
                    'tf_phone' => '(416) 555-0147',
                ],
            ],
            [
                'title' => 'Mississauga',
                'content' => 'Local plumbing service across Mississauga, from Clarkson to Malton.',
                'meta' => [
                    'tf_city' => 'Mississauga',
                    'tf_postal_prefixes' => 'L4T,L4V,L4W,L4X,L4Y,L4Z,L5A,L5B,L5C,L5E,L5G,L5H,L5J,L5K,L5L,L5M,L5N,L5R,L5V,L5W',
                    'tf_phone' => '(905) 555-0182',
                ],
            ],
            [
                'title' => 'Oakville',
                'content' => 'Dependable residential plumbing appointments throughout Oakville.',
                'meta' => [
                    'tf_city' => 'Oakville',
                    'tf_postal_prefixes' => 'L6H,L6J,L6K,L6L,L6M',
                    'tf_phone' => '(905) 555-0129',
                ],
            ],
        ];

        foreach ($areas as $area) {
            wp_insert_post([
                'post_type' => 'tf_service_area',
                'post_status' => 'publish',
                'post_title' => $area['title'],
                'post_content' => $area['content'],
                'meta_input' => $area['meta'],
            ]);
        }

        update_option('tradeflow_technicians', ['Alex Morgan', 'Jamie Chen', 'Sam Rivera']);
    }
}

