<?php

if (!defined('ABSPATH')) {
    exit;
}

function tradeflow_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', ['height' => 64, 'width' => 220, 'flex-height' => true, 'flex-width' => true]);
    register_nav_menus(['primary' => __('Primary navigation', 'tradeflow')]);
}
add_action('after_setup_theme', 'tradeflow_setup');

function tradeflow_enqueue_assets(): void
{
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('tradeflow-style', get_stylesheet_uri(), [], $version);
    wp_enqueue_script('tradeflow-site', get_template_directory_uri() . '/assets/site.js', [], $version, true);
}
add_action('wp_enqueue_scripts', 'tradeflow_enqueue_assets');

function tradeflow_enqueue_booking_assets(): void
{
    static $enqueued = false;
    if ($enqueued) {
        return;
    }

    $manifest_path = get_template_directory() . '/dist/.vite/manifest.json';
    if (!file_exists($manifest_path)) {
        return;
    }

    $manifest = json_decode((string) file_get_contents($manifest_path), true);
    $entry = $manifest['main.tsx'] ?? reset($manifest);
    if (!$entry || empty($entry['file'])) {
        return;
    }

    $base = get_template_directory_uri() . '/dist/';
    foreach ($entry['css'] ?? [] as $index => $css) {
        wp_enqueue_style('tradeflow-booking-' . $index, $base . $css, ['tradeflow-style'], TRADEFLOW_VERSION);
    }
    wp_enqueue_script('tradeflow-booking', $base . $entry['file'], [], TRADEFLOW_VERSION, true);
    wp_localize_script('tradeflow-booking', 'tradeFlowConfig', [
        'restUrl' => esc_url_raw(rest_url('tradeflow/v1')),
        'pageUrl' => esc_url_raw(home_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'))),
        'siteName' => get_bloginfo('name'),
    ]);
    $enqueued = true;
}

add_action('wp_enqueue_scripts', static function (): void {
    if (is_front_page() || is_singular('tf_service') || get_query_var('tf_service_slug')) {
        tradeflow_enqueue_booking_assets();
    }
}, 20);

add_filter('script_loader_tag', static function (string $tag, string $handle): string {
    if ($handle === 'tradeflow-booking') {
        return str_replace('<script ', '<script type="module" ', $tag);
    }
    return $tag;
}, 10, 2);

function tradeflow_icon(string $name, string $class = ''): string
{
    $icons = [
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'shield' => '<path d="M12 3 5 6v5c0 4.6 3 8 7 10 4-2 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>',
        'camera' => '<path d="M5 7h3l1.5-2h5L16 7h3a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/><circle cx="12" cy="13" r="3"/>',
        'pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5-5L12 3.6 9.6 6 7.3 3.7a4 4 0 0 0 5 5l7 7a2 2 0 0 1-2.8 2.8l-7-7"/>',
        'drop' => '<path d="M12 2s7 7.2 7 13a7 7 0 0 1-14 0c0-5.8 7-13 7-13Z"/><path d="M9 16c.5 1.2 1.5 2 3 2"/>',
        'drain' => '<path d="M4 6h16M6 10h12M8 14h8M10 18h4"/><path d="M12 2v4"/>',
        'heater' => '<rect x="6" y="2" width="12" height="20" rx="5"/><path d="M9 7h6M10 18h4"/><circle cx="12" cy="12" r="2"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'bolt' => '<path d="m13 2-8 12h7l-1 8 8-12h-7l1-8Z"/>',
    ];
    $path = $icons[$name] ?? $icons['wrench'];
    return sprintf(
        '<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        esc_attr($class),
        $path
    );
}

function tradeflow_location_url(WP_Post|int $service, WP_Post|int $area): string
{
    $service = get_post($service);
    $area = get_post($area);
    if (!$service || !$area) {
        return home_url('/');
    }
    return home_url('/services/' . $service->post_name . '/' . $area->post_name . '/');
}

function tradeflow_default_phone(): string
{
    $area = get_page_by_path('toronto', OBJECT, 'tf_service_area');
    if (!$area) {
        $areas = get_posts(['post_type' => 'tf_service_area', 'numberposts' => 1]);
        $area = $areas[0] ?? null;
    }
    return $area ? ((string) get_post_meta($area->ID, 'tf_phone', true) ?: '(416) 555-0147') : '(416) 555-0147';
}

function tradeflow_phone_href(string $phone): string
{
    return (string) preg_replace('/\D+/', '', $phone);
}

function tradeflow_seo_meta(): void
{
    $service_slug = get_query_var('tf_service_slug');
    $area_slug = get_query_var('tf_area_slug');
    if (!$service_slug || !$area_slug) {
        if (is_front_page()) {
            echo '<meta name="description" content="' . esc_attr__('Book trusted local plumbing service with clear arrival windows, photo uploads, and confirmation from TradeFlow.', 'tradeflow') . '">' . "\n";
            echo '<link rel="canonical" href="' . esc_url(home_url('/')) . '">' . "\n";
        }
        return;
    }

    $service = get_page_by_path($service_slug, OBJECT, 'tf_service');
    $area = get_page_by_path($area_slug, OBJECT, 'tf_service_area');
    if (!$service || !$area) {
        return;
    }

    $title = sprintf('%s in %s | TradeFlow', $service->post_title, $area->post_title);
    $description = sprintf(
        'Book trusted %s in %s. Choose an arrival window, upload photos, and receive confirmation from TradeFlow.',
        strtolower($service->post_title),
        $area->post_title
    );
    $canonical = tradeflow_location_url($service, $area);
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => $service->post_title,
        'description' => wp_strip_all_tags($service->post_excerpt),
        'areaServed' => ['@type' => 'City', 'name' => $area->post_title],
        'provider' => [
            '@type' => 'LocalBusiness',
            'name' => 'TradeFlow',
            'telephone' => get_post_meta($area->ID, 'tf_phone', true),
            'url' => home_url('/'),
        ],
        'url' => $canonical,
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'tradeflow_seo_meta', 2);

add_filter('pre_get_document_title', static function (string $title): string {
    $service_slug = get_query_var('tf_service_slug');
    $area_slug = get_query_var('tf_area_slug');
    if (!$service_slug || !$area_slug) {
        return $title;
    }
    $service = get_page_by_path($service_slug, OBJECT, 'tf_service');
    $area = get_page_by_path($area_slug, OBJECT, 'tf_service_area');
    return ($service && $area) ? sprintf('%s in %s | TradeFlow', $service->post_title, $area->post_title) : $title;
});
