<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TradeFlow_Elementor_Widget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'tradeflow-booking';
    }

    public function get_title(): string
    {
        return __('TradeFlow Booking', 'tradeflow');
    }

    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    public function get_categories(): array
    {
        return ['general'];
    }

    protected function register_controls(): void
    {
        $services = ['' => __('Auto-detect from page', 'tradeflow')];
        foreach (get_posts(['post_type' => 'tf_service', 'numberposts' => 100]) as $service) {
            $services[$service->post_name] = $service->post_title;
        }
        $areas = ['' => __('Auto-detect from page', 'tradeflow')];
        foreach (get_posts(['post_type' => 'tf_service_area', 'numberposts' => 100]) as $area) {
            $areas[$area->post_name] = $area->post_title;
        }

        $this->start_controls_section('content', ['label' => __('Booking form', 'tradeflow')]);
        $this->add_control('heading', [
            'label' => __('Heading', 'tradeflow'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('Request your free quote', 'tradeflow'),
        ]);
        $this->add_control('service', [
            'label' => __('Service', 'tradeflow'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $services,
            'default' => '',
        ]);
        $this->add_control('area', [
            'label' => __('Service area', 'tradeflow'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $areas,
            'default' => '',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        echo do_shortcode(sprintf(
            '[tradeflow_booking heading="%s" service="%s" area="%s"]',
            esc_attr($settings['heading']),
            esc_attr($settings['service']),
            esc_attr($settings['area'])
        ));
    }
}

