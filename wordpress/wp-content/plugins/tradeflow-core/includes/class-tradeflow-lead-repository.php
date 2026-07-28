<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TradeFlow_Lead_Repository
{
    private wpdb $db;
    private string $leads_table;
    private string $appointments_table;

    public function __construct(?wpdb $db = null)
    {
        global $wpdb;
        $this->db = $db ?? $wpdb;
        $this->leads_table = $this->db->prefix . 'tradeflow_leads';
        $this->appointments_table = $this->db->prefix . 'tradeflow_appointments';
    }

    public function create(array $data): array|WP_Error
    {
        $now = current_time('mysql');
        $reference = 'TF-' . strtoupper(wp_generate_password(8, false, false));
        $token = hash('sha256', wp_generate_uuid4() . wp_salt('auth'));
        $dedupe_key = TradeFlow_Validator::dedupe_key($data, wp_date('Y-m-d'));

        $this->db->query('START TRANSACTION');

        // A duplicate is an expected business-rule conflict. Suppress wpdb's
        // debug output so the REST layer can still send the correct 409 status.
        $previous_suppression = $this->db->suppress_errors(true);
        $inserted = $this->db->insert($this->leads_table, [
            'reference' => $reference,
            'public_token' => $token,
            'customer_name' => $data['customer_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'service_id' => $data['service_id'],
            'area_id' => $data['area_id'],
            'postal_code' => $data['postal_code'],
            'details' => $data['details'],
            'status' => 'new',
            'photo_ids' => wp_json_encode($data['photo_ids'] ?? []),
            'landing_page' => $data['landing_page'] ?? '',
            'referrer' => $data['referrer'] ?? '',
            'utm_source' => $data['utm_source'] ?? '',
            'utm_medium' => $data['utm_medium'] ?? '',
            'utm_campaign' => $data['utm_campaign'] ?? '',
            'utm_content' => $data['utm_content'] ?? '',
            'utm_term' => $data['utm_term'] ?? '',
            'gclid' => $data['gclid'] ?? '',
            'dedupe_key' => $dedupe_key,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $insert_error = $this->db->last_error;
        $this->db->suppress_errors($previous_suppression);

        if (!$inserted) {
            $this->db->query('ROLLBACK');
            if (str_contains(strtolower($insert_error), 'duplicate')) {
                return new WP_Error(
                    'duplicate_lead',
                    __('We already received this request today. Check your email for the confirmation or call us if the details changed.', 'tradeflow'),
                    ['status' => 409]
                );
            }
            return new WP_Error('lead_insert_failed', __('We could not save your request. Please try again.', 'tradeflow'), ['status' => 500]);
        }

        $lead_id = (int) $this->db->insert_id;
        $appointment_inserted = $this->db->insert($this->appointments_table, [
            'lead_id' => $lead_id,
            'starts_at' => get_date_from_gmt(gmdate('Y-m-d H:i:s', strtotime($data['slot_start']))),
            'ends_at' => get_date_from_gmt(gmdate('Y-m-d H:i:s', strtotime($data['slot_end']))),
            'status' => 'requested',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!$appointment_inserted) {
            $this->db->query('ROLLBACK');
            return new WP_Error('appointment_insert_failed', __('That time could not be reserved. Please choose another.', 'tradeflow'), ['status' => 409]);
        }

        $appointment_id = (int) $this->db->insert_id;
        $updated = $this->db->update(
            $this->leads_table,
            ['appointment_id' => $appointment_id],
            ['id' => $lead_id],
            ['%d'],
            ['%d']
        );

        if ($updated === false) {
            $this->db->query('ROLLBACK');
            return new WP_Error('lead_link_failed', __('We could not finish your request. Please try again.', 'tradeflow'), ['status' => 500]);
        }

        $this->db->query('COMMIT');
        return [
            'id' => $lead_id,
            'reference' => $reference,
            'token' => $token,
            'status' => 'new',
            'appointment_id' => $appointment_id,
        ];
    }

    public function find_public(string $token): ?array
    {
        $sql = $this->db->prepare(
            "SELECT l.reference, l.customer_name, l.status, l.technician,
                    l.service_id, l.area_id, a.starts_at, a.ends_at
             FROM {$this->leads_table} l
             INNER JOIN {$this->appointments_table} a ON a.id = l.appointment_id
             WHERE l.public_token = %s",
            $token
        );
        $lead = $this->db->get_row($sql, ARRAY_A);
        return $lead ?: null;
    }

    public function find(int $id): ?array
    {
        $sql = $this->db->prepare(
            "SELECT l.*, a.starts_at, a.ends_at, a.status AS appointment_status
             FROM {$this->leads_table} l
             LEFT JOIN {$this->appointments_table} a ON a.id = l.appointment_id
             WHERE l.id = %d",
            $id
        );
        $lead = $this->db->get_row($sql, ARRAY_A);
        return $lead ?: null;
    }

    public function update_workflow(int $id, string $status, string $technician): bool
    {
        if (!in_array($status, TradeFlow_Validator::STATUSES, true)) {
            return false;
        }

        $this->db->query('START TRANSACTION');
        $lead_updated = $this->db->update(
            $this->leads_table,
            [
                'status' => $status,
                'technician' => $technician,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        $appointment_status = match ($status) {
            'confirmed', 'assigned', 'en_route' => 'confirmed',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'requested',
        };

        $appointment_updated = $this->db->query($this->db->prepare(
            "UPDATE {$this->appointments_table} a
             INNER JOIN {$this->leads_table} l ON l.appointment_id = a.id
             SET a.status = %s, a.updated_at = %s
             WHERE l.id = %d",
            $appointment_status,
            current_time('mysql'),
            $id
        ));

        if ($lead_updated === false || $appointment_updated === false) {
            $this->db->query('ROLLBACK');
            return false;
        }

        $this->db->query('COMMIT');
        return true;
    }

    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], TradeFlow_Validator::STATUSES, true)) {
            $where[] = 'l.status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $like = '%' . $this->db->esc_like($filters['search']) . '%';
            $where[] = '(l.reference LIKE %s OR l.customer_name LIKE %s OR l.email LIKE %s OR l.phone LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $query = "SELECT l.*, a.starts_at, a.ends_at
                  FROM {$this->leads_table} l
                  LEFT JOIN {$this->appointments_table} a ON a.id = l.appointment_id
                  WHERE " . implode(' AND ', $where) . '
                  ORDER BY l.created_at DESC
                  LIMIT 100';

        if ($params) {
            $query = $this->db->prepare($query, ...$params);
        }

        return $this->db->get_results($query, ARRAY_A) ?: [];
    }

    public function metrics(): array
    {
        $today = current_time('Y-m-d');
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT status, COUNT(*) AS total
                 FROM {$this->leads_table}
                 WHERE created_at >= %s
                 GROUP BY status",
                $today . ' 00:00:00'
            ),
            ARRAY_A
        );
        $metrics = array_fill_keys(TradeFlow_Validator::STATUSES, 0);
        foreach ($rows as $row) {
            $metrics[$row['status']] = (int) $row['total'];
        }
        return $metrics;
    }
}
