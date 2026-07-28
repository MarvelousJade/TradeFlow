<?php
get_header();
the_post();
$area = get_post();
$services = get_posts(['post_type' => 'tf_service', 'post_status' => 'publish', 'numberposts' => 20]);
?>
<section class="tf-area-banner"><div class="tf-shell"><p class="tf-eyebrow"><?php esc_html_e('Service area', 'tradeflow'); ?></p><h1><?php echo esc_html(sprintf(__('TradeFlow in %s.', 'tradeflow'), get_the_title())); ?></h1><p class="tf-area-banner__lead"><?php the_excerpt(); ?></p></div></section>
<section class="tf-section"><div class="tf-shell"><p class="tf-eyebrow"><?php esc_html_e('Available locally', 'tradeflow'); ?></p><h2 class="tf-section-title"><?php esc_html_e('Choose the help you need.', 'tradeflow'); ?></h2><div class="tf-services-grid" style="margin-top:45px"><?php foreach ($services as $index => $service) : ?><a class="tf-service-card" href="<?php echo esc_url(tradeflow_location_url($service, $area)); ?>"><span class="tf-service-card__icon"><?php echo tradeflow_icon((string) get_post_meta($service->ID, 'tf_icon', true)); ?></span><span class="tf-service-card__number"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span><h3><?php echo esc_html($service->post_title); ?></h3><p><?php echo esc_html($service->post_excerpt); ?></p><span class="tf-service-card__link"><?php esc_html_e('Book locally', 'tradeflow'); ?><?php echo tradeflow_icon('arrow'); ?></span></a><?php endforeach; ?></div></div></section>
<?php get_footer(); ?>

