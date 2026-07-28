<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f5ee">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#main"><?php esc_html_e('Skip to content', 'tradeflow'); ?></a>
<header class="tf-site-header">
    <div class="tf-shell tf-nav">
        <a class="tf-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('TradeFlow home', 'tradeflow'); ?>">
            <span class="tf-brand__mark"><?php echo tradeflow_icon('bolt'); ?></span>
            <span>TradeFlow</span>
        </a>
        <nav aria-label="<?php esc_attr_e('Primary navigation', 'tradeflow'); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => 'tf-nav__links',
                'fallback_cb' => static function (): void {
                    echo '<ul class="tf-nav__links"><li><a href="' . esc_url(home_url('/#services')) . '">Services</a></li><li><a href="' . esc_url(home_url('/#process')) . '">How it works</a></li><li><a href="' . esc_url(home_url('/#areas')) . '">Service areas</a></li></ul>';
                },
            ]);
            ?>
        </nav>
        <div class="tf-nav__actions">
            <a class="tf-nav__phone" href="tel:<?php echo esc_attr(tradeflow_phone_href(tradeflow_default_phone())); ?>"><?php echo esc_html(tradeflow_default_phone()); ?></a>
            <a class="tf-button" href="#booking"><?php esc_html_e('Get a free quote', 'tradeflow'); ?><?php echo tradeflow_icon('arrow'); ?></a>
            <button class="tf-menu-button" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="<?php esc_attr_e('Open navigation', 'tradeflow'); ?>"><?php echo tradeflow_icon('menu'); ?></button>
        </div>
    </div>
</header>
<div class="tf-mobile-menu" id="mobile-menu">
    <nav aria-label="<?php esc_attr_e('Mobile navigation', 'tradeflow'); ?>">
        <ul><li><a href="<?php echo esc_url(home_url('/#services')); ?>">Services</a></li><li><a href="<?php echo esc_url(home_url('/#process')); ?>">How it works</a></li><li><a href="<?php echo esc_url(home_url('/#areas')); ?>">Service areas</a></li></ul>
        <a class="tf-button" href="#booking"><?php esc_html_e('Get a free quote', 'tradeflow'); ?></a>
    </nav>
</div>
<main id="main">
