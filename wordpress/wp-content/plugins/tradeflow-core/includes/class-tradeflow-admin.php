<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TradeFlow_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_tf_update_lead', [self::class, 'update_lead']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('wp_dashboard_setup', [self::class, 'dashboard_widget']);
    }

    public static function menu(): void
    {
        add_menu_page(
            __('TradeFlow leads', 'tradeflow'),
            __('TradeFlow', 'tradeflow'),
            'edit_posts',
            'tradeflow-leads',
            [self::class, 'render'],
            'dashicons-calendar-alt',
            26
        );
    }

    public static function assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_tradeflow-leads') {
            return;
        }
        wp_enqueue_style('tradeflow-admin', TRADEFLOW_URL . 'assets/admin.css', [], TRADEFLOW_VERSION);
    }

    public static function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to view leads.', 'tradeflow'));
        }

        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $repository = new TradeFlow_Lead_Repository();
        $leads = $repository->list(['status' => $status, 'search' => $search]);
        $metrics = $repository->metrics();
        $technicians = (array) get_option('tradeflow_technicians', []);
        ?>
        <div class="wrap tf-admin">
            <header class="tf-admin__header">
                <div>
                    <p class="tf-admin__eyebrow"><?php esc_html_e('Operations', 'tradeflow'); ?></p>
                    <h1><?php esc_html_e('Service requests', 'tradeflow'); ?></h1>
                </div>
                <p><?php echo esc_html(wp_date('l, F j')); ?></p>
            </header>

            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Request updated and customer notification queued.', 'tradeflow'); ?></p></div>
            <?php endif; ?>

            <section class="tf-metrics" aria-label="<?php esc_attr_e('Today at a glance', 'tradeflow'); ?>">
                <div><strong><?php echo esc_html($metrics['new']); ?></strong><span><?php esc_html_e('New today', 'tradeflow'); ?></span></div>
                <div><strong><?php echo esc_html($metrics['confirmed'] + $metrics['assigned']); ?></strong><span><?php esc_html_e('Scheduled', 'tradeflow'); ?></span></div>
                <div><strong><?php echo esc_html($metrics['en_route']); ?></strong><span><?php esc_html_e('En route', 'tradeflow'); ?></span></div>
                <div><strong><?php echo esc_html($metrics['completed']); ?></strong><span><?php esc_html_e('Completed', 'tradeflow'); ?></span></div>
            </section>

            <form class="tf-filters" method="get">
                <input type="hidden" name="page" value="tradeflow-leads">
                <label>
                    <span class="screen-reader-text"><?php esc_html_e('Filter by status', 'tradeflow'); ?></span>
                    <select name="status">
                        <option value=""><?php esc_html_e('All statuses', 'tradeflow'); ?></option>
                        <?php foreach (TradeFlow_Validator::STATUSES as $option) : ?>
                            <option value="<?php echo esc_attr($option); ?>" <?php selected($status, $option); ?>><?php echo esc_html(self::status_label($option)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e('Search leads', 'tradeflow'); ?></span>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Name, email or reference', 'tradeflow'); ?>">
                </label>
                <button class="button"><?php esc_html_e('Filter', 'tradeflow'); ?></button>
            </form>

            <div class="tf-lead-list">
                <?php if (!$leads) : ?>
                    <div class="tf-empty"><span class="dashicons dashicons-clipboard"></span><h2><?php esc_html_e('No requests found', 'tradeflow'); ?></h2><p><?php esc_html_e('New quote requests will appear here.', 'tradeflow'); ?></p></div>
                <?php endif; ?>
                <?php foreach ($leads as $lead) : self::lead_card($lead, $technicians); endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function lead_card(array $lead, array $technicians): void
    {
        $photos = array_filter(array_map('absint', (array) json_decode((string) $lead['photo_ids'], true)));
        ?>
        <article class="tf-lead">
            <div class="tf-lead__summary">
                <span class="tf-status tf-status--<?php echo esc_attr($lead['status']); ?>"><?php echo esc_html(self::status_label($lead['status'])); ?></span>
                <p class="tf-lead__reference"><?php echo esc_html($lead['reference']); ?> · <?php echo esc_html(human_time_diff(strtotime($lead['created_at']), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'tradeflow'); ?></p>
                <h2><?php echo esc_html(get_the_title((int) $lead['service_id'])); ?></h2>
                <p><?php echo esc_html(get_the_title((int) $lead['area_id'])); ?> · <?php echo esc_html($lead['postal_code']); ?></p>
                <p class="tf-lead__slot"><?php echo esc_html(wp_date('D, M j · g a', strtotime($lead['starts_at']))); ?>–<?php echo esc_html(wp_date('g a', strtotime($lead['ends_at']))); ?></p>
            </div>
            <div class="tf-lead__customer">
                <h3><?php echo esc_html($lead['customer_name']); ?></h3>
                <a href="mailto:<?php echo esc_attr($lead['email']); ?>"><?php echo esc_html($lead['email']); ?></a>
                <a href="tel:<?php echo esc_attr(TradeFlow_Validator::normalize_phone($lead['phone'])); ?>"><?php echo esc_html($lead['phone']); ?></a>
                <p><?php echo nl2br(esc_html($lead['details'])); ?></p>
                <?php if ($photos) : ?>
                    <div class="tf-lead__photos">
                        <?php foreach ($photos as $photo_id) : echo wp_get_attachment_image($photo_id, 'thumbnail', false, ['loading' => 'lazy']); endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="tf-lead__source">
                <h3><?php esc_html_e('Attribution', 'tradeflow'); ?></h3>
                <dl>
                    <dt><?php esc_html_e('Campaign', 'tradeflow'); ?></dt><dd><?php echo esc_html($lead['utm_campaign'] ?: 'Direct / none'); ?></dd>
                    <dt><?php esc_html_e('Source', 'tradeflow'); ?></dt><dd><?php echo esc_html(trim($lead['utm_source'] . ' / ' . $lead['utm_medium'], ' /') ?: 'Direct'); ?></dd>
                    <dt><?php esc_html_e('Landing page', 'tradeflow'); ?></dt><dd><a href="<?php echo esc_url($lead['landing_page']); ?>" target="_blank" rel="noopener"><?php echo esc_html(wp_parse_url($lead['landing_page'], PHP_URL_PATH) ?: '/'); ?></a></dd>
                </dl>
            </div>
            <form class="tf-lead__actions" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="tf_update_lead">
                <input type="hidden" name="lead_id" value="<?php echo esc_attr($lead['id']); ?>">
                <?php wp_nonce_field('tf_update_lead_' . $lead['id']); ?>
                <label><?php esc_html_e('Technician', 'tradeflow'); ?>
                    <select name="technician">
                        <option value=""><?php esc_html_e('Unassigned', 'tradeflow'); ?></option>
                        <?php foreach ($technicians as $technician) : ?>
                            <option value="<?php echo esc_attr($technician); ?>" <?php selected($lead['technician'], $technician); ?>><?php echo esc_html($technician); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php esc_html_e('Status', 'tradeflow'); ?>
                    <select name="status">
                        <?php foreach (TradeFlow_Validator::STATUSES as $status) : ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($lead['status'], $status); ?>><?php echo esc_html(self::status_label($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button button-primary"><?php esc_html_e('Save & notify', 'tradeflow'); ?></button>
            </form>
        </article>
        <?php
    }

    public static function update_lead(): void
    {
        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        if (!current_user_can('edit_posts') || !check_admin_referer('tf_update_lead_' . $lead_id)) {
            wp_die(esc_html__('Invalid request.', 'tradeflow'));
        }

        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'new';
        $technician = isset($_POST['technician']) ? sanitize_text_field(wp_unslash($_POST['technician'])) : '';
        $allowed_technicians = (array) get_option('tradeflow_technicians', []);
        if ($technician !== '' && !in_array($technician, $allowed_technicians, true)) {
            wp_die(esc_html__('Invalid technician.', 'tradeflow'));
        }

        $repository = new TradeFlow_Lead_Repository();
        $before = $repository->find($lead_id);
        if (!$before || !$repository->update_workflow($lead_id, $status, $technician)) {
            wp_die(esc_html__('The request could not be updated.', 'tradeflow'));
        }

        $after = $repository->find($lead_id);
        TradeFlow_Notifications::send_status_update($after, $before['status']);
        wp_safe_redirect(add_query_arg(['page' => 'tradeflow-leads', 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    public static function dashboard_widget(): void
    {
        if (current_user_can('edit_posts')) {
            wp_add_dashboard_widget('tradeflow_today', __('TradeFlow today', 'tradeflow'), [self::class, 'render_dashboard_widget']);
        }
    }

    public static function render_dashboard_widget(): void
    {
        $metrics = (new TradeFlow_Lead_Repository())->metrics();
        printf(
            '<p><strong style="font-size:30px">%1$d</strong><br>%2$s</p><p>%3$d %4$s · %5$d %6$s</p><p><a class="button button-primary" href="%7$s">%8$s</a></p>',
            esc_html($metrics['new']),
            esc_html__('new requests today', 'tradeflow'),
            esc_html($metrics['assigned'] + $metrics['confirmed']),
            esc_html__('scheduled', 'tradeflow'),
            esc_html($metrics['en_route']),
            esc_html__('en route', 'tradeflow'),
            esc_url(admin_url('admin.php?page=tradeflow-leads')),
            esc_html__('Open dispatch board', 'tradeflow')
        );
    }

    private static function status_label(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}

