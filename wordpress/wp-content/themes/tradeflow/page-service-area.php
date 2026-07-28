<?php
$service_slug = sanitize_title((string) get_query_var('tf_service_slug'));
$area_slug = sanitize_title((string) get_query_var('tf_area_slug'));
$service = get_page_by_path($service_slug, OBJECT, 'tf_service');
$area = get_page_by_path($area_slug, OBJECT, 'tf_service_area');

if (!$service || !$area) {
    status_header(404);
    nocache_headers();
    include get_404_template();
    return;
}

get_header();
$phone = (string) get_post_meta($area->ID, 'tf_phone', true) ?: tradeflow_default_phone();
$base_price = (float) get_post_meta($service->ID, 'tf_base_price', true);
$duration = (int) get_post_meta($service->ID, 'tf_duration', true);
?>
<section class="tf-area-banner">
    <div class="tf-shell">
        <p class="tf-breadcrumbs"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'tradeflow'); ?></a> / <a href="<?php echo esc_url(get_permalink($service)); ?>"><?php echo esc_html($service->post_title); ?></a> / <?php echo esc_html($area->post_title); ?></p>
        <div class="tf-area-banner__grid">
            <div>
                <p class="tf-eyebrow"><?php echo esc_html(sprintf(__('Serving %s', 'tradeflow'), $area->post_title)); ?></p>
                <h1><?php echo esc_html($service->post_title); ?><br><em><?php echo esc_html(sprintf(__('in %s.', 'tradeflow'), $area->post_title)); ?></em></h1>
                <p class="tf-area-banner__lead"><?php echo esc_html($service->post_excerpt); ?> <?php esc_html_e('Book a convenient window with a local TradeFlow technician.', 'tradeflow'); ?></p>
                <a class="tf-button tf-button--light" href="#booking"><?php esc_html_e('Check local availability', 'tradeflow'); ?><?php echo tradeflow_icon('arrow'); ?></a>
            </div>
            <div class="tf-area-facts">
                <div class="tf-area-fact"><span><?php esc_html_e('Local booking line', 'tradeflow'); ?></span><strong><?php echo esc_html($phone); ?></strong></div>
                <div class="tf-area-fact"><span><?php esc_html_e('Typical visit', 'tradeflow'); ?></span><strong><?php echo esc_html(round($duration / 60)); ?>–<?php echo esc_html(round($duration / 60) + 1); ?> <?php esc_html_e('hours', 'tradeflow'); ?></strong></div>
                <div class="tf-area-fact"><span><?php esc_html_e('Request fee', 'tradeflow'); ?></span><strong><?php esc_html_e('Free', 'tradeflow'); ?></strong></div>
            </div>
        </div>
    </div>
</section>

<section class="tf-section tf-section--white">
    <div class="tf-shell tf-service-detail">
        <div class="tf-service-detail__content">
            <p class="tf-eyebrow"><?php esc_html_e('Local service details', 'tradeflow'); ?></p>
            <h2><?php echo esc_html(sprintf(__('%s help without the guesswork.', 'tradeflow'), $area->post_title)); ?></h2>
            <div><?php echo wp_kses_post(wpautop($service->post_content)); ?></div>
            <ul class="tf-check-list">
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Upfront review before dispatch', 'tradeflow'); ?></li>
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Photos and job notes sent to the technician', 'tradeflow'); ?></li>
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Email confirmation and status updates', 'tradeflow'); ?></li>
            </ul>
        </div>
        <aside class="tf-detail-panel">
            <h3><?php esc_html_e('At a glance', 'tradeflow'); ?></h3>
            <dl>
                <div><dt><?php esc_html_e('Service', 'tradeflow'); ?></dt><dd><?php echo esc_html($service->post_title); ?></dd></div>
                <div><dt><?php esc_html_e('Coverage', 'tradeflow'); ?></dt><dd><?php echo esc_html($area->post_title); ?></dd></div>
                <div><dt><?php esc_html_e('Assessment from', 'tradeflow'); ?></dt><dd>$<?php echo esc_html(number_format_i18n($base_price)); ?></dd></div>
                <div><dt><?php esc_html_e('Arrival windows', 'tradeflow'); ?></dt><dd><?php esc_html_e('4 hours', 'tradeflow'); ?></dd></div>
            </dl>
        </aside>
    </div>
</section>

<section class="tf-section tf-booking-section" id="booking">
    <div class="tf-shell tf-booking-layout">
        <div class="tf-booking-copy">
            <p class="tf-eyebrow"><?php esc_html_e('Local availability', 'tradeflow'); ?></p>
            <h2><?php echo esc_html(sprintf(__('Book %s service.', 'tradeflow'), $area->post_title)); ?></h2>
            <p><?php esc_html_e('We will check your postal code, collect the job details, and route the request to the right local team.', 'tradeflow'); ?></p>
            <ul class="tf-booking-benefits">
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Location eligibility checked instantly', 'tradeflow'); ?></li>
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Choose from live request windows', 'tradeflow'); ?></li>
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Confirmation sent by email', 'tradeflow'); ?></li>
            </ul>
        </div>
        <div class="tf-booking-root" data-service="<?php echo esc_attr($service->post_name); ?>" data-area="<?php echo esc_attr($area->post_name); ?>" data-heading="<?php echo esc_attr(sprintf(__('Request %s in %s', 'tradeflow'), $service->post_title, $area->post_title)); ?>"></div>
    </div>
</section>

<section class="tf-section">
    <div class="tf-shell">
        <p class="tf-eyebrow"><?php esc_html_e('More nearby services', 'tradeflow'); ?></p>
        <h2 class="tf-section-title"><?php echo esc_html(sprintf(__('Also available in %s.', 'tradeflow'), $area->post_title)); ?></h2>
        <div class="tf-services-grid" style="margin-top:45px">
            <?php
            $related = get_posts(['post_type' => 'tf_service', 'post__not_in' => [$service->ID], 'numberposts' => 3]);
            foreach ($related as $index => $item) :
                $icon = (string) get_post_meta($item->ID, 'tf_icon', true) ?: 'wrench';
                ?>
                <a class="tf-service-card" href="<?php echo esc_url(tradeflow_location_url($item, $area)); ?>">
                    <span class="tf-service-card__icon"><?php echo tradeflow_icon($icon); ?></span>
                    <span class="tf-service-card__number"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                    <h3><?php echo esc_html($item->post_title); ?></h3>
                    <p><?php echo esc_html($item->post_excerpt); ?></p>
                    <span class="tf-service-card__link"><?php esc_html_e('View local service', 'tradeflow'); ?><?php echo tradeflow_icon('arrow'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php get_footer(); ?>

