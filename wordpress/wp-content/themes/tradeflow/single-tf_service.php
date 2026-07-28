<?php
get_header();
the_post();
$service = get_post();
$areas = get_posts(['post_type' => 'tf_service_area', 'post_status' => 'publish', 'numberposts' => 20]);
?>
<section class="tf-area-banner">
    <div class="tf-shell">
        <p class="tf-breadcrumbs"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'tradeflow'); ?></a> / <?php esc_html_e('Services', 'tradeflow'); ?></p>
        <div class="tf-area-banner__grid">
            <div><p class="tf-eyebrow"><?php esc_html_e('TradeFlow service', 'tradeflow'); ?></p><h1><?php the_title(); ?></h1><p class="tf-area-banner__lead"><?php echo esc_html(get_the_excerpt()); ?></p></div>
            <div class="tf-area-facts"><div class="tf-area-fact"><span><?php esc_html_e('Assessment from', 'tradeflow'); ?></span><strong>$<?php echo esc_html(number_format_i18n((float) get_post_meta(get_the_ID(), 'tf_base_price', true))); ?></strong></div><div class="tf-area-fact"><span><?php esc_html_e('Request fee', 'tradeflow'); ?></span><strong><?php esc_html_e('Free', 'tradeflow'); ?></strong></div></div>
        </div>
    </div>
</section>
<section class="tf-section tf-section--white">
    <div class="tf-shell tf-service-detail">
        <div class="tf-service-detail__content"><p class="tf-eyebrow"><?php esc_html_e('About the service', 'tradeflow'); ?></p><h2><?php esc_html_e('Careful work. Clear next steps.', 'tradeflow'); ?></h2><?php the_content(); ?></div>
        <div><h2 class="tf-section-title" style="font-size:2.2rem"><?php esc_html_e('Choose your local team.', 'tradeflow'); ?></h2><div class="tf-areas-grid" style="grid-template-columns:1fr;margin-top:25px"><?php foreach ($areas as $area) : ?><a class="tf-area-card" href="<?php echo esc_url(tradeflow_location_url($service, $area)); ?>"><span><?php esc_html_e('Service available', 'tradeflow'); ?></span><h3><?php echo esc_html($area->post_title); ?> →</h3><p><?php echo esc_html(get_post_meta($area->ID, 'tf_phone', true)); ?></p></a><?php endforeach; ?></div></div>
    </div>
</section>
<?php get_footer(); ?>

