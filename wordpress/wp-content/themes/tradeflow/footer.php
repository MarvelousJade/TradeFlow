</main>
<footer class="tf-site-footer">
    <div class="tf-shell">
        <div class="tf-footer-grid">
            <div class="tf-footer-intro">
                <a class="tf-brand" href="<?php echo esc_url(home_url('/')); ?>">
                    <span class="tf-brand__mark"><?php echo tradeflow_icon('bolt'); ?></span>
                    <span>TradeFlow</span>
                </a>
                <p><?php esc_html_e('Clear estimates, convenient arrival windows, and local technicians who respect your home.', 'tradeflow'); ?></p>
                <a class="tf-footer-contact" href="tel:<?php echo esc_attr(tradeflow_phone_href(tradeflow_default_phone())); ?>"><?php echo esc_html(tradeflow_default_phone()); ?></a>
            </div>
            <div class="tf-footer-column">
                <h2><?php esc_html_e('Services', 'tradeflow'); ?></h2>
                <ul>
                    <?php foreach (get_posts(['post_type' => 'tf_service', 'numberposts' => 5]) as $service) : ?>
                        <li><a href="<?php echo esc_url(get_permalink($service)); ?>"><?php echo esc_html($service->post_title); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="tf-footer-column">
                <h2><?php esc_html_e('Areas', 'tradeflow'); ?></h2>
                <ul>
                    <?php foreach (get_posts(['post_type' => 'tf_service_area', 'numberposts' => 5]) as $area) : ?>
                        <li><a href="<?php echo esc_url(get_permalink($area)); ?>"><?php echo esc_html($area->post_title); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="tf-footer-column">
                <h2><?php esc_html_e('Company', 'tradeflow'); ?></h2>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/#process')); ?>"><?php esc_html_e('How it works', 'tradeflow'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Staff login', 'tradeflow'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>"><?php esc_html_e('Privacy', 'tradeflow'); ?></a></li>
                </ul>
            </div>
        </div>
        <div class="tf-footer-bottom">
            <span>&copy; <?php echo esc_html(wp_date('Y')); ?> TradeFlow. <?php esc_html_e('All rights reserved.', 'tradeflow'); ?></span>
            <span><?php esc_html_e('Built for dependable local service.', 'tradeflow'); ?></span>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
