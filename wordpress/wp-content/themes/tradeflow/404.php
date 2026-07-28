<?php get_header(); ?>
<div class="tf-content">
    <p class="tf-eyebrow">404</p>
    <h1><?php esc_html_e('This page needs a different route.', 'tradeflow'); ?></h1>
    <p class="tf-section-intro"><?php esc_html_e('The page may have moved. Head home to choose a service or start a request.', 'tradeflow'); ?></p>
    <p><a class="tf-button" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to TradeFlow', 'tradeflow'); ?><?php echo tradeflow_icon('arrow'); ?></a></p>
</div>
<?php get_footer(); ?>

