<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TradeFlow_Post_Types
{
    public static function init(): void
    {
        add_action('init', [self::class, 'register']);
        add_action('init', [self::class, 'register_rewrites']);
        add_action('add_meta_boxes', [self::class, 'meta_boxes']);
        add_action('save_post_tf_service', [self::class, 'save_service_meta']);
        add_action('save_post_tf_service_area', [self::class, 'save_area_meta']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_filter('template_include', [self::class, 'location_template']);
    }

    public static function meta_boxes(): void
    {
        add_meta_box(
            'tradeflow-service-details',
            __('TradeFlow service details', 'tradeflow'),
            [self::class, 'service_meta_box'],
            'tf_service',
            'side'
        );
        add_meta_box(
            'tradeflow-area-details',
            __('TradeFlow coverage', 'tradeflow'),
            [self::class, 'area_meta_box'],
            'tf_service_area',
            'normal'
        );
    }

    public static function service_meta_box(WP_Post $post): void
    {
        wp_nonce_field('tradeflow_save_meta', 'tradeflow_meta_nonce');
        $duration = (int) get_post_meta($post->ID, 'tf_duration', true);
        $price = (float) get_post_meta($post->ID, 'tf_base_price', true);
        $icon = (string) get_post_meta($post->ID, 'tf_icon', true);
        ?>
        <p><label for="tf_duration"><strong><?php esc_html_e('Typical duration (minutes)', 'tradeflow'); ?></strong></label><br><input id="tf_duration" name="tf_duration" type="number" min="30" step="30" value="<?php echo esc_attr($duration); ?>" class="widefat"></p>
        <p><label for="tf_base_price"><strong><?php esc_html_e('Assessment price', 'tradeflow'); ?></strong></label><br><input id="tf_base_price" name="tf_base_price" type="number" min="0" step="1" value="<?php echo esc_attr($price); ?>" class="widefat"></p>
        <p><label for="tf_icon"><strong><?php esc_html_e('Icon', 'tradeflow'); ?></strong></label><br><select id="tf_icon" name="tf_icon" class="widefat"><?php foreach (['wrench', 'drain', 'heater', 'drop'] as $option) : ?><option value="<?php echo esc_attr($option); ?>" <?php selected($icon, $option); ?>><?php echo esc_html(ucfirst($option)); ?></option><?php endforeach; ?></select></p>
        <?php
    }

    public static function area_meta_box(WP_Post $post): void
    {
        wp_nonce_field('tradeflow_save_meta', 'tradeflow_meta_nonce');
        ?>
        <p><label for="tf_city"><strong><?php esc_html_e('City', 'tradeflow'); ?></strong></label><br><input id="tf_city" name="tf_city" value="<?php echo esc_attr(get_post_meta($post->ID, 'tf_city', true)); ?>" class="widefat"></p>
        <p><label for="tf_postal_prefixes"><strong><?php esc_html_e('Eligible postal prefixes', 'tradeflow'); ?></strong></label><br><input id="tf_postal_prefixes" name="tf_postal_prefixes" value="<?php echo esc_attr(get_post_meta($post->ID, 'tf_postal_prefixes', true)); ?>" class="widefat"><small><?php esc_html_e('Comma-separated, for example: M4, M5, M6', 'tradeflow'); ?></small></p>
        <p><label for="tf_phone"><strong><?php esc_html_e('Local phone', 'tradeflow'); ?></strong></label><br><input id="tf_phone" name="tf_phone" type="tel" value="<?php echo esc_attr(get_post_meta($post->ID, 'tf_phone', true)); ?>" class="widefat"></p>
        <?php
    }

    public static function save_service_meta(int $post_id): void
    {
        if (!self::can_save_meta($post_id)) {
            return;
        }
        update_post_meta($post_id, 'tf_duration', max(30, absint($_POST['tf_duration'] ?? 120)));
        update_post_meta($post_id, 'tf_base_price', max(0, (float) ($_POST['tf_base_price'] ?? 0)));
        $icon = sanitize_key(wp_unslash($_POST['tf_icon'] ?? 'wrench'));
        update_post_meta($post_id, 'tf_icon', in_array($icon, ['wrench', 'drain', 'heater', 'drop'], true) ? $icon : 'wrench');
    }

    public static function save_area_meta(int $post_id): void
    {
        if (!self::can_save_meta($post_id)) {
            return;
        }
        update_post_meta($post_id, 'tf_city', sanitize_text_field(wp_unslash($_POST['tf_city'] ?? '')));
        update_post_meta($post_id, 'tf_postal_prefixes', strtoupper(sanitize_text_field(wp_unslash($_POST['tf_postal_prefixes'] ?? ''))));
        update_post_meta($post_id, 'tf_phone', sanitize_text_field(wp_unslash($_POST['tf_phone'] ?? '')));
    }

    private static function can_save_meta(int $post_id): bool
    {
        return !wp_is_post_autosave($post_id)
            && !wp_is_post_revision($post_id)
            && isset($_POST['tradeflow_meta_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tradeflow_meta_nonce'])), 'tradeflow_save_meta')
            && current_user_can('edit_post', $post_id);
    }

    public static function register(): void
    {
        register_post_type('tf_service', [
            'labels' => [
                'name' => __('Services', 'tradeflow'),
                'singular_name' => __('Service', 'tradeflow'),
                'add_new_item' => __('Add service', 'tradeflow'),
                'edit_item' => __('Edit service', 'tradeflow'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-hammer',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
            'rewrite' => ['slug' => 'service', 'with_front' => false],
            'has_archive' => 'services',
        ]);

        register_post_type('tf_service_area', [
            'labels' => [
                'name' => __('Service areas', 'tradeflow'),
                'singular_name' => __('Service area', 'tradeflow'),
                'add_new_item' => __('Add service area', 'tradeflow'),
                'edit_item' => __('Edit service area', 'tradeflow'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-location-alt',
            'supports' => ['title', 'editor', 'excerpt', 'revisions'],
            'rewrite' => ['slug' => 'service-area', 'with_front' => false],
            'has_archive' => false,
        ]);

        self::register_meta();
    }

    private static function register_meta(): void
    {
        $definitions = [
            'tf_service' => [
                'tf_duration' => ['type' => 'integer', 'default' => 120],
                'tf_base_price' => ['type' => 'number', 'default' => 0],
                'tf_icon' => ['type' => 'string', 'default' => 'wrench'],
            ],
            'tf_service_area' => [
                'tf_city' => ['type' => 'string', 'default' => ''],
                'tf_postal_prefixes' => ['type' => 'string', 'default' => ''],
                'tf_phone' => ['type' => 'string', 'default' => ''],
            ],
        ];

        foreach ($definitions as $post_type => $fields) {
            foreach ($fields as $key => $schema) {
                register_post_meta($post_type, $key, [
                    'type' => $schema['type'],
                    'single' => true,
                    'default' => $schema['default'],
                    'show_in_rest' => true,
                    'sanitize_callback' => $schema['type'] === 'string' ? 'sanitize_text_field' : null,
                    'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
                ]);
            }
        }
    }

    public static function register_rewrites(): void
    {
        add_rewrite_rule(
            '^services/([^/]+)/([^/]+)/?$',
            'index.php?tf_service_slug=$matches[1]&tf_area_slug=$matches[2]',
            'top'
        );
    }

    public static function query_vars(array $vars): array
    {
        $vars[] = 'tf_service_slug';
        $vars[] = 'tf_area_slug';
        return $vars;
    }

    public static function location_template(string $template): string
    {
        if (!get_query_var('tf_service_slug') || !get_query_var('tf_area_slug')) {
            return $template;
        }

        $location_template = locate_template('page-service-area.php');
        return $location_template ?: $template;
    }
}
