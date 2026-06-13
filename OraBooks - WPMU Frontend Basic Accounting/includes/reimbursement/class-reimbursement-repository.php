<?php
/**
 * Reimbursement Repository
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Reimbursement_Repository {
    private $wpdb;
    private $table_reimbursements;
    private $table_items;
    private $table_attachments;
    private $table_logs;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_reimbursements = $wpdb->prefix . 'orabooks_reimbursements';
        $this->table_items = $wpdb->prefix . 'orabooks_reimbursement_items';
        $this->table_attachments = $wpdb->prefix . 'orabooks_reimbursement_attachments';
        $this->table_logs = $wpdb->prefix . 'orabooks_reimbursement_logs';
    }

    public function create_tables() {
        // Table creation moved to OBN_Activator::activate()
    }

    public function find_all($args = []) {
        $defaults = [
            'status' => '',
            'employee_id' => 0,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'id',
            'order' => 'DESC'
        ];
        $params = wp_parse_args($args, $defaults);
        
        $where = " WHERE 1=1 ";
        if (!empty($params['status'])) {
            $where .= $this->wpdb->prepare(" AND status = %s ", $params['status']);
        }
        if ($params['employee_id'] > 0) {
            $where .= $this->wpdb->prepare(" AND employee_id = %d ", $params['employee_id']);
        }

        $query = "SELECT * FROM {$this->table_reimbursements} $where ORDER BY {$params['orderby']} {$params['order']} LIMIT %d OFFSET %d";
        return $this->wpdb->get_results($this->wpdb->prepare($query, $params['limit'], $params['offset']));
    }

    public function find_by_id($id) {
        $reimbursement = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_reimbursements} WHERE id = %d", $id));
        if ($reimbursement) {
            $reimbursement->items = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_items} WHERE reimbursement_id = %d", $id));
            $reimbursement->attachments = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_attachments} WHERE reimbursement_id = %d", $id));
            $reimbursement->logs = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table_logs} WHERE reimbursement_id = %d ORDER BY created_at DESC", $id));
        }
        return $reimbursement;
    }

    public function save($data, $items = [], $attachments = []) {
        $this->wpdb->query('START TRANSACTION');

        if (isset($data['id']) && $reimbursement_id = intval($data['id'])) {
            $this->wpdb->update($this->table_reimbursements, $data, ['id' => $reimbursement_id]);
        } else {
            $this->wpdb->insert($this->table_reimbursements, $data);
            $reimbursement_id = $this->wpdb->insert_id;
        }

        if (!$reimbursement_id) {
            $this->wpdb->query('ROLLBACK');
            return false;
        }

        // Handle items
        if (!empty($items)) {
            $this->wpdb->delete($this->table_items, ['reimbursement_id' => $reimbursement_id]);
            foreach ($items as $item) {
                $item['reimbursement_id'] = $reimbursement_id;
                $this->wpdb->insert($this->table_items, $item);
            }
        }

        // Handle attachments
        if (!empty($attachments)) {
            foreach ($attachments as $att) {
                $att['reimbursement_id'] = $reimbursement_id;
                $this->wpdb->insert($this->table_attachments, $att);
            }
        }

        $this->wpdb->query('COMMIT');
        return $reimbursement_id;
    }

    public function update_status($id, $status, $user_id, $note = '') {
        $data = ['status' => $status];
        if ($status === 'Approved') {
            $data['approved_by'] = $user_id;
            $data['approved_at'] = current_time('mysql');
        } elseif ($status === 'Paid') {
            $data['paid_by'] = $user_id;
            $data['paid_at'] = current_time('mysql');
            $data['payment_status'] = 'Paid';
        }

        $updated = $this->wpdb->update($this->table_reimbursements, $data, ['id' => $id]);
        if ($updated) {
            $this->add_log($id, $user_id, $status, $note);
        }
        return $updated;
    }

    public function add_log($reimbursement_id, $user_id, $action, $note = '') {
        return $this->wpdb->insert($this->table_logs, [
            'reimbursement_id' => $reimbursement_id,
            'user_id' => $user_id,
            'action' => $action,
            'note' => $note
        ]);
    }
}
