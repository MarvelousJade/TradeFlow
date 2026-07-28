<?php
get_header();
$services = get_posts(['post_type' => 'tf_service', 'post_status' => 'publish', 'numberposts' => 6, 'orderby' => 'menu_order title']);
$areas = get_posts(['post_type' => 'tf_service_area', 'post_status' => 'publish', 'numberposts' => 6, 'orderby' => 'menu_order title']);
$default_area = $areas[0] ?? null;
?>
<section class="tf-hero">
    <div class="tf-shell tf-hero__grid">
        <div>
            <p class="tf-eyebrow"><?php esc_html_e('Local help, without the runaround', 'tradeflow'); ?></p>
            <h1><?php esc_html_e('Book the fix.', 'tradeflow'); ?><br><em><?php esc_html_e('Get on with it.', 'tradeflow'); ?></em></h1>
            <p class="tf-hero__lead"><?php esc_html_e('Tell us what is happening, choose an arrival window, and get a clear confirmation from a trusted local technician.', 'tradeflow'); ?></p>
            <div class="tf-hero__actions">
                <a class="tf-button" href="#booking"><?php esc_html_e('Request a free quote', 'tradeflow'); ?><?php echo tradeflow_icon('arrow'); ?></a>
                <a class="tf-button tf-button--ghost" href="#services"><?php esc_html_e('Explore services', 'tradeflow'); ?></a>
            </div>
            <p class="tf-hero__note"><strong><?php esc_html_e('No call-centre maze.', 'tradeflow'); ?></strong> <?php esc_html_e('Your request goes straight to the local service team.', 'tradeflow'); ?></p>
            <div class="tf-trust-row">
                <div class="tf-avatars" aria-hidden="true"><span class="tf-avatar">AM</span><span class="tf-avatar">JC</span><span class="tf-avatar">SR</span></div>
                <p><span class="tf-stars" aria-label="<?php esc_attr_e('Five stars', 'tradeflow'); ?>">★★★★★</span><br><strong><?php esc_html_e('Local, licensed technicians', 'tradeflow'); ?></strong></p>
            </div>
        </div>
        <div class="tf-hero-visual" aria-label="<?php esc_attr_e('TradeFlow appointment availability preview', 'tradeflow'); ?>">
            <div class="tf-hero-visual__backdrop">
                <div class="tf-technician" aria-hidden="true">
                    <div class="tf-technician__figure"><span class="tf-technician__head"></span><span class="tf-technician__body"></span></div>
                    <strong><?php esc_html_e('Jamie, service technician', 'tradeflow'); ?></strong>
                    <p><?php esc_html_e('Licensed · Background checked · Local', 'tradeflow'); ?></p>
                </div>
            </div>
            <div class="tf-availability-card">
                <div class="tf-availability-card__top">
                    <div><p><?php esc_html_e('Next availability', 'tradeflow'); ?></p><strong><?php esc_html_e('Tomorrow', 'tradeflow'); ?></strong></div>
                    <span class="tf-live"><?php esc_html_e('Open', 'tradeflow'); ?></span>
                </div>
                <div class="tf-slot-mini"><span>8 am – 12 pm</span><span>12 pm – 4 pm</span></div>
            </div>
            <div class="tf-job-card">
                <div class="tf-job-card__row">
                    <span class="tf-job-card__icon"><?php echo tradeflow_icon('check'); ?></span>
                    <div><p><?php esc_html_e('Request confirmed', 'tradeflow'); ?></p><strong><?php esc_html_e('Drain repair · Toronto', 'tradeflow'); ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="tf-feature-band" aria-label="<?php esc_attr_e('Service commitments', 'tradeflow'); ?>">
    <div class="tf-shell tf-feature-band__grid">
        <div class="tf-feature"><?php echo tradeflow_icon('calendar'); ?><div><strong><?php esc_html_e('Convenient windows', 'tradeflow'); ?></strong><span><?php esc_html_e('Choose a time that works', 'tradeflow'); ?></span></div></div>
        <div class="tf-feature"><?php echo tradeflow_icon('camera'); ?><div><strong><?php esc_html_e('Photo-first quotes', 'tradeflow'); ?></strong><span><?php esc_html_e('Show us what you see', 'tradeflow'); ?></span></div></div>
        <div class="tf-feature"><?php echo tradeflow_icon('shield'); ?><div><strong><?php esc_html_e('Qualified technicians', 'tradeflow'); ?></strong><span><?php esc_html_e('Licensed and insured', 'tradeflow'); ?></span></div></div>
        <div class="tf-feature"><?php echo tradeflow_icon('pin'); ?><div><strong><?php esc_html_e('Truly local service', 'tradeflow'); ?></strong><span><?php esc_html_e('Teams near your neighbourhood', 'tradeflow'); ?></span></div></div>
    </div>
</section>

<section class="tf-section" id="services">
    <div class="tf-shell">
        <div class="tf-heading-row">
            <div><p class="tf-eyebrow"><?php esc_html_e('What we handle', 'tradeflow'); ?></p><h2 class="tf-section-title"><?php esc_html_e('The essential fixes, done right.', 'tradeflow'); ?></h2></div>
            <p class="tf-section-intro"><?php esc_html_e('Straight answers, careful work, and a booking process designed around your day.', 'tradeflow'); ?></p>
        </div>
        <div class="tf-services-grid">
            <?php foreach ($services as $index => $service) :
                $icon = (string) get_post_meta($service->ID, 'tf_icon', true) ?: 'wrench';
                $url = $default_area ? tradeflow_location_url($service, $default_area) : get_permalink($service);
                ?>
                <a class="tf-service-card" href="<?php echo esc_url($url); ?>">
                    <span class="tf-service-card__icon"><?php echo tradeflow_icon($icon); ?></span>
                    <span class="tf-service-card__number"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                    <h3><?php echo esc_html($service->post_title); ?></h3>
                    <p><?php echo esc_html($service->post_excerpt); ?></p>
                    <span class="tf-service-card__link"><?php esc_html_e('See service details', 'tradeflow'); ?><?php echo tradeflow_icon('arrow'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="tf-section tf-section--dark" id="process">
    <div class="tf-shell">
        <p class="tf-eyebrow"><?php esc_html_e('Simple by design', 'tradeflow'); ?></p>
        <h2 class="tf-section-title"><?php esc_html_e('From “something’s wrong” to someone’s on it.', 'tradeflow'); ?></h2>
        <p class="tf-section-intro"><?php esc_html_e('A clear handoff at every step, with email updates so you are never left guessing.', 'tradeflow'); ?></p>
        <div class="tf-process-grid">
            <div class="tf-process-step"><span>01</span><h3><?php esc_html_e('Tell us what you see', 'tradeflow'); ?></h3><p><?php esc_html_e('Choose a service, add a few details, and attach photos if they help explain the issue.', 'tradeflow'); ?></p></div>
            <div class="tf-process-step"><span>02</span><h3><?php esc_html_e('Pick an arrival window', 'tradeflow'); ?></h3><p><?php esc_html_e('Select a convenient four-hour window from current local availability.', 'tradeflow'); ?></p></div>
            <div class="tf-process-step"><span>03</span><h3><?php esc_html_e('Get a real confirmation', 'tradeflow'); ?></h3><p><?php esc_html_e('Our staff reviews the request, assigns a technician, and keeps you updated.', 'tradeflow'); ?></p></div>
        </div>
    </div>
</section>

<section class="tf-section tf-booking-section" id="booking">
    <div class="tf-shell tf-booking-layout">
        <div class="tf-booking-copy">
            <p class="tf-eyebrow"><?php esc_html_e('Get started', 'tradeflow'); ?></p>
            <h2><?php esc_html_e('A better service visit starts here.', 'tradeflow'); ?></h2>
            <p><?php esc_html_e('Share the basics now. A local coordinator will review everything before confirming your appointment.', 'tradeflow'); ?></p>
            <ul class="tf-booking-benefits">
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Free request, no obligation', 'tradeflow'); ?></li>
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Takes about two minutes', 'tradeflow'); ?></li>
                <li><?php echo tradeflow_icon('check'); ?><?php esc_html_e('Your details stay with the service team', 'tradeflow'); ?></li>
            </ul>
        </div>
        <div class="tf-booking-root" data-service="" data-area="<?php echo esc_attr($default_area?->post_name ?? ''); ?>" data-heading="<?php esc_attr_e('Request your free quote', 'tradeflow'); ?>"></div>
    </div>
</section>

<section class="tf-section" id="areas">
    <div class="tf-shell">
        <p class="tf-eyebrow"><?php esc_html_e('Service areas', 'tradeflow'); ?></p>
        <h2 class="tf-section-title"><?php esc_html_e('Nearby when you need us.', 'tradeflow'); ?></h2>
        <p class="tf-section-intro"><?php esc_html_e('Local landing pages connect every request to the right coverage team and phone number.', 'tradeflow'); ?></p>
        <div class="tf-areas-grid">
            <?php foreach ($areas as $area) :
                $service = $services[0] ?? null;
                ?>
                <a class="tf-area-card" href="<?php echo esc_url($service ? tradeflow_location_url($service, $area) : get_permalink($area)); ?>">
                    <span><?php esc_html_e('Now serving', 'tradeflow'); ?></span>
                    <h3><?php echo esc_html($area->post_title); ?></h3>
                    <p><?php echo esc_html(get_post_meta($area->ID, 'tf_phone', true)); ?> →</p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="tf-section tf-section--white">
    <div class="tf-shell">
        <div class="tf-cta">
            <div><h2><?php esc_html_e('Ready to get the problem off your list?', 'tradeflow'); ?></h2><p><?php esc_html_e('Request a quote online or talk to the local team.', 'tradeflow'); ?></p></div>
            <div class="tf-cta__actions">
                <a class="tf-button tf-button--light" href="#booking"><?php esc_html_e('Start your request', 'tradeflow'); ?><?php echo tradeflow_icon('arrow'); ?></a>
                <a class="tf-button" href="tel:<?php echo esc_attr(preg_replace('/\D+/', '', tradeflow_default_phone())); ?>"><?php esc_html_e('Call', 'tradeflow'); ?> <?php echo esc_html(tradeflow_default_phone()); ?></a>
            </div>
        </div>
    </div>
</section>
<?php get_footer(); ?>

