<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TradeFlow_REST_Controller
{
    private const NAMESPACE = 'tradeflow/v1';

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'health'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/bootstrap', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'bootstrap'],
            'permission_callback' => '__return_true',
            'args' => [
                'service' => [
                    'sanitize_callback' => static fn ($value): string => sanitize_title((string) $value),
                ],
                'area' => [
                    'sanitize_callback' => static fn ($value): string => sanitize_title((string) $value),
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/leads', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_lead'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/eligibility', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'eligibility'],
            'permission_callback' => '__return_true',
            'args' => [
                'area_id' => ['required' => true, 'sanitize_callback' => 'absint'],
                'postal_code' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/leads/(?P<token>[a-f0-9]{64})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'lead_status'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function health(): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $database_check = $wpdb->get_var('SELECT 1');
        if ((string) $database_check !== '1') {
            return new WP_Error(
                'database_unavailable',
                __('The database is unavailable.', 'tradeflow'),
                ['status' => 503]
            );
        }

        $response = new WP_REST_Response([
            'status' => 'ok',
            'database' => 'connected',
            'version' => TRADEFLOW_VERSION,
        ], 200);
        $response->header('Cache-Control', 'no-store, max-age=0');
        return $response;
    }

    public static function bootstrap(WP_REST_Request $request): WP_REST_Response
    {
        $service_slug = sanitize_title((string) $request->get_param('service'));
        $area_slug = sanitize_title((string) $request->get_param('area'));
        $services = self::posts_for_widget('tf_service', $service_slug);
        $areas = self::posts_for_widget('tf_service_area', $area_slug);

        return new WP_REST_Response([
            'nonce' => wp_create_nonce('wp_rest'),
            'services' => $services,
            'areas' => $areas,
            'slots' => self::available_slots(),
            'maxPhotos' => 3,
            'maxPhotoBytes' => 5 * MB_IN_BYTES,
        ], 200);
    }

    private static function posts_for_widget(string $post_type, string $selected_slug): array
    {
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => 100,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
        ]);

        return array_map(static function (WP_Post $post) use ($post_type, $selected_slug): array {
            $item = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'slug' => $post->post_name,
                'summary' => $post->post_excerpt,
                'selected' => $selected_slug === $post->post_name,
            ];
            if ($post_type === 'tf_service') {
                $item['duration'] = (int) get_post_meta($post->ID, 'tf_duration', true);
                $item['basePrice'] = (float) get_post_meta($post->ID, 'tf_base_price', true);
                $item['icon'] = get_post_meta($post->ID, 'tf_icon', true) ?: 'wrench';
            } else {
                $item['city'] = get_post_meta($post->ID, 'tf_city', true);
                $item['phone'] = get_post_meta($post->ID, 'tf_phone', true);
            }
            return $item;
        }, $posts);
    }

    private static function available_slots(): array
    {
        $timezone = wp_timezone();
        $cursor = new DateTimeImmutable('tomorrow 08:00', $timezone);
        $slots = [];

        while (count($slots) < 12) {
            if ((int) $cursor->format('N') < 7) {
                foreach ([8, 12] as $hour) {
                    $start = $cursor->setTime($hour, 0);
                    $end = $start->modify('+4 hours');
                    $slots[] = [
                        'start' => $start->format(DateTimeInterface::ATOM),
                        'end' => $end->format(DateTimeInterface::ATOM),
                        'dateLabel' => wp_date('D, M j', $start->getTimestamp()),
                        'timeLabel' => wp_date('g a', $start->getTimestamp()) . ' – ' . wp_date('g a', $end->getTimestamp()),
                    ];
                }
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $slots;
    }

    public static function create_lead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!self::valid_nonce($request)) {
            return new WP_Error('invalid_nonce', __('Your booking session expired. Refresh the page and try again.', 'tradeflow'), ['status' => 403]);
        }
        if ((string) $request->get_param('website') !== '') {
            return new WP_Error('spam_detected', __('Unable to process this request.', 'tradeflow'), ['status' => 400]);
        }
        if (!self::within_rate_limit()) {
            return new WP_Error('rate_limited', __('Too many attempts. Please wait ten minutes or call us.', 'tradeflow'), ['status' => 429]);
        }

        $data = [
            'customer_name' => sanitize_text_field((string) $request->get_param('customer_name')),
            'email' => sanitize_email((string) $request->get_param('email')),
            'phone' => sanitize_text_field((string) $request->get_param('phone')),
            'service_id' => absint($request->get_param('service_id')),
            'area_id' => absint($request->get_param('area_id')),
            'postal_code' => TradeFlow_Validator::normalize_postal_code((string) $request->get_param('postal_code')),
            'details' => sanitize_textarea_field((string) $request->get_param('details')),
            'slot_start' => sanitize_text_field((string) $request->get_param('slot_start')),
            'slot_end' => sanitize_text_field((string) $request->get_param('slot_end')),
        ];

        $errors = TradeFlow_Validator::validate($data);
        if ($errors) {
            return new WP_Error('validation_failed', __('Check the highlighted fields.', 'tradeflow'), ['status' => 422, 'fields' => $errors]);
        }
        if (get_post_type($data['service_id']) !== 'tf_service' || get_post_status($data['service_id']) !== 'publish') {
            return new WP_Error('invalid_service', __('That service is not available.', 'tradeflow'), ['status' => 422]);
        }
        if (get_post_type($data['area_id']) !== 'tf_service_area' || get_post_status($data['area_id']) !== 'publish') {
            return new WP_Error('invalid_area', __('That service area is not available.', 'tradeflow'), ['status' => 422]);
        }

        $prefixes = (string) get_post_meta($data['area_id'], 'tf_postal_prefixes', true);
        if (!TradeFlow_Validator::postal_code_is_eligible($data['postal_code'], $prefixes)) {
            return new WP_Error(
                'outside_service_area',
                __('That postal code is outside this service area. Choose another area or call us to confirm coverage.', 'tradeflow'),
                ['status' => 422, 'fields' => ['postal_code' => __('We do not currently serve this postal code.', 'tradeflow')]]
            );
        }

        $photo_ids = self::handle_photos();
        if (is_wp_error($photo_ids)) {
            return $photo_ids;
        }

        $data['photo_ids'] = $photo_ids;
        foreach (['landing_page', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid'] as $field) {
            $value = (string) $request->get_param($field);
            $data[$field] = in_array($field, ['landing_page', 'referrer'], true)
                ? esc_url_raw($value)
                : sanitize_text_field($value);
        }

        $repository = new TradeFlow_Lead_Repository();
        $lead = $repository->create($data);
        if (is_wp_error($lead)) {
            return $lead;
        }

        TradeFlow_Notifications::send_confirmation(array_merge($data, $lead));
        return new WP_REST_Response([
            'reference' => $lead['reference'],
            'status' => $lead['status'],
            'statusUrl' => rest_url(self::NAMESPACE . '/leads/' . $lead['token']),
            'message' => __('Your request is in. We will email you after a staff member confirms the appointment.', 'tradeflow'),
        ], 201);
    }

    public static function eligibility(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $area_id = absint($request->get_param('area_id'));
        $postal_code = TradeFlow_Validator::normalize_postal_code((string) $request->get_param('postal_code'));
        if (get_post_type($area_id) !== 'tf_service_area' || get_post_status($area_id) !== 'publish') {
            return new WP_Error('invalid_area', __('Choose a valid service area.', 'tradeflow'), ['status' => 422]);
        }
        if (strlen($postal_code) < 3) {
            return new WP_Error('invalid_postal_code', __('Enter a valid postal code.', 'tradeflow'), ['status' => 422]);
        }

        $eligible = TradeFlow_Validator::postal_code_is_eligible(
            $postal_code,
            (string) get_post_meta($area_id, 'tf_postal_prefixes', true)
        );
        $area_name = get_the_title($area_id);

        return new WP_REST_Response([
            'eligible' => $eligible,
            'area' => $area_name,
            'message' => $eligible
                ? sprintf(__('Great — this address is in our %s service area.', 'tradeflow'), $area_name)
                : sprintf(__('This postal code is outside our %s coverage area.', 'tradeflow'), $area_name),
        ], 200);
    }

    public static function lead_status(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $repository = new TradeFlow_Lead_Repository();
        $lead = $repository->find_public((string) $request['token']);
        if (!$lead) {
            return new WP_Error('not_found', __('Request not found.', 'tradeflow'), ['status' => 404]);
        }

        return new WP_REST_Response([
            'reference' => $lead['reference'],
            'customerFirstName' => explode(' ', $lead['customer_name'])[0],
            'status' => $lead['status'],
            'technician' => $lead['technician'],
            'service' => get_the_title((int) $lead['service_id']),
            'area' => get_the_title((int) $lead['area_id']),
            'slot' => [
                'start' => mysql_to_rfc3339($lead['starts_at']),
                'end' => mysql_to_rfc3339($lead['ends_at']),
            ],
        ], 200);
    }

    private static function valid_nonce(WP_REST_Request $request): bool
    {
        $nonce = (string) $request->get_header('X-WP-Nonce');
        return $nonce !== '' && (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    private static function within_rate_limit(): bool
    {
        $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ((string) getenv('RENDER') === 'true' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded_addresses = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidate = trim($forwarded_addresses[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $address = $candidate;
            }
        }
        $address = sanitize_text_field($address);
        $key = 'tf_rate_' . hash('sha256', $address);
        $attempts = (int) get_transient($key);
        $limit = wp_get_environment_type() === 'local' ? 100 : 10;
        if ($attempts >= $limit) {
            return false;
        }
        set_transient($key, $attempts + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private static function handle_photos(): array|WP_Error
    {
        if (empty($_FILES['photos']['name'])) {
            return [];
        }

        $files = $_FILES['photos'];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        if (count($names) > 3) {
            return new WP_Error('too_many_photos', __('Upload no more than three photos.', 'tradeflow'), ['status' => 422]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $ids = [];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        foreach (array_keys($names) as $index) {
            $file = [
                'name' => is_array($files['name']) ? $files['name'][$index] : $files['name'],
                'type' => is_array($files['type']) ? $files['type'][$index] : $files['type'],
                'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$index] : $files['tmp_name'],
                'error' => is_array($files['error']) ? $files['error'][$index] : $files['error'],
                'size' => is_array($files['size']) ? $files['size'][$index] : $files['size'],
            ];

            $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            $detected_type = (string) ($checked['type'] ?? '');
            if ((int) $file['size'] > 5 * MB_IN_BYTES || !in_array($detected_type, $allowed, true)) {
                return new WP_Error('invalid_photo', __('Photos must be JPG, PNG, or WebP files under 5 MB.', 'tradeflow'), ['status' => 422]);
            }
            $file['type'] = $detected_type;

            $id = media_handle_sideload($file, 0, __('TradeFlow booking photo', 'tradeflow'));
            if (is_wp_error($id)) {
                return new WP_Error('photo_upload_failed', __('One of the photos could not be uploaded.', 'tradeflow'), ['status' => 422]);
            }
            $ids[] = (int) $id;
        }

        return $ids;
    }
}
