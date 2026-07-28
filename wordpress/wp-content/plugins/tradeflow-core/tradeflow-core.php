<?php
/**
 * Plugin Name: TradeFlow Core
 * Description: Service areas, bookings, attribution, staff workflow, and Elementor integration for TradeFlow.
 * Version: 1.0.0
 * Author: TradeFlow
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: tradeflow
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TRADEFLOW_VERSION', '1.0.0');
define('TRADEFLOW_FILE', __FILE__);
define('TRADEFLOW_PATH', plugin_dir_path(__FILE__));
define('TRADEFLOW_URL', plugin_dir_url(__FILE__));

require_once TRADEFLOW_PATH . 'includes/class-tradeflow-post-types.php';
require_once TRADEFLOW_PATH . 'includes/class-tradeflow-installer.php';
require_once TRADEFLOW_PATH . 'includes/class-tradeflow-validator.php';
require_once TRADEFLOW_PATH . 'includes/class-tradeflow-lead-repository.php';
require_once TRADEFLOW_PATH . 'includes/class-tradeflow-notifications.php';
require_once TRADEFLOW_PATH . 'includes/class-tradeflow-rest-controller.php';
require_once TRADEFLOW_PATH . 'includes/class-tradeflow-admin.php';

register_activation_hook(__FILE__, ['TradeFlow_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['TradeFlow_Installer', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    TradeFlow_Post_Types::init();
    TradeFlow_REST_Controller::init();
    TradeFlow_Admin::init();
    TradeFlow_Notifications::init();
});

add_shortcode('tradeflow_booking', static function (array $attributes = []): string {
    $attributes = shortcode_atts(
        [
            'service' => '',
            'area' => '',
            'heading' => __('Request your free quote', 'tradeflow'),
        ],
        $attributes,
        'tradeflow_booking'
    );

    if (function_exists('tradeflow_enqueue_booking_assets')) {
        tradeflow_enqueue_booking_assets();
    }

    return sprintf(
        '<div class="tf-booking-root" data-service="%1$s" data-area="%2$s" data-heading="%3$s"></div>',
        esc_attr($attributes['service']),
        esc_attr($attributes['area']),
        esc_attr($attributes['heading'])
    );
});

add_action('elementor/widgets/register', static function ($widgets_manager): void {
    if (!class_exists('\Elementor\Widget_Base')) {
        return;
    }

    require_once TRADEFLOW_PATH . 'includes/class-tradeflow-elementor-widget.php';
    $widgets_manager->register(new TradeFlow_Elementor_Widget());
});

