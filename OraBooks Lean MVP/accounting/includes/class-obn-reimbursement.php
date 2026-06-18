<?php
/**
 * Main Reimbursement Module Controller
 */

if (!defined('ABSPATH'))
    exit;

require_once plugin_dir_path(__FILE__) . 'reimbursement/class-reimbursement-repository.php';
require_once plugin_dir_path(__FILE__) . 'reimbursement/class-reimbursement-service.php';

class OBN_Reimbursement
{
    private $service;
    private $repository;

    public function __construct()
    {
        $this->repository = new OBN_Reimbursement_Repository();
        $this->service = new OBN_Reimbursement_Service($this->repository);

        $this->init_hooks();
    }

    private function init_hooks()
    {
        // AJAX handlers
        add_action('wp_ajax_obn_save_reimbursement', [$this, 'ajax_save_reimbursement']);
        add_action('wp_ajax_nopriv_obn_save_reimbursement', [$this, 'ajax_save_reimbursement']);

        add_action('wp_ajax_obn_submit_reimbursement', [$this, 'ajax_submit_reimbursement']);
        add_action('wp_ajax_nopriv_obn_submit_reimbursement', [$this, 'ajax_submit_reimbursement']);

        add_action('wp_ajax_obn_approve_reimbursement', [$this, 'ajax_approve_reimbursement']);
        add_action('wp_ajax_nopriv_obn_approve_reimbursement', [$this, 'ajax_approve_reimbursement']);

        add_action('wp_ajax_obn_pay_reimbursement', [$this, 'ajax_pay_reimbursement']);
        add_action('wp_ajax_nopriv_obn_pay_reimbursement', [$this, 'ajax_pay_reimbursement']);

        add_action('wp_ajax_obn_reject_reimbursement', [$this, 'ajax_reject_reimbursement']);
        add_action('wp_ajax_nopriv_obn_reject_reimbursement', [$this, 'ajax_reject_reimbursement']);

        add_action('wp_ajax_obn_get_reimbursement', [$this, 'ajax_get_reimbursement']);
        add_action('wp_ajax_nopriv_obn_get_reimbursement', [$this, 'ajax_get_reimbursement']);

        add_action('wp_ajax_obn_delete_reimbursement', [$this, 'ajax_delete_reimbursement']);
        add_action('wp_ajax_nopriv_obn_delete_reimbursement', [$this, 'ajax_delete_reimbursement']);

        // Handle Sidebar registration
        // Sidebar registration is handled by OBN_Activator
        // add_action('init', [$this, 'ensure_sidebar_items'], 30);
    }

    public function ajax_save_reimbursement()
    {
        check_ajax_referer('obn_reimbursement_nonce', 'security');
        if (!is_user_logged_in())
            wp_send_json_error('Unauthorized');

        try {
            $data = [
                'id' => intval($_POST['id'] ?? 0),
                'employee_id' => get_current_user_id(),
                'date' => sanitize_text_field($_POST['date'] ?? current_time('Y-m-d')),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'created_by' => get_current_user_id()
            ];

            $items_raw = $_POST['items'] ?? [];
            $items = [];
            foreach ($items_raw as $item) {
                $items[] = [
                    'date' => sanitize_text_field($item['date']),
                    'category_id' => intval($item['category_id']),
                    'description' => sanitize_textarea_field($item['description']),
                    'amount' => floatval($item['amount'])
                ];
            }

            // Handle file uploads if any
            $attachments = [];
            if (!empty($_FILES['receipts'])) {
                $attachments = $this->handle_uploads($_FILES['receipts']);
            }

            $id = $this->service->create_reimbursement($data, $items, $attachments);
            wp_send_json_success(['id' => $id, 'message' => 'Reimbursement saved as draft.']);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_submit_reimbursement()
    {
        check_ajax_referer('obn_reimbursement_nonce', 'security');
        $id = intval($_POST['id']);
        try {
            $this->service->submit($id);
            wp_send_json_success(['message' => 'Reimbursement submitted for approval.']);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_approve_reimbursement()
    {
        check_ajax_referer('obn_reimbursement_nonce', 'security');
        // Role check: Only manager/admin
        $auth = new OBN_Auth();
        if (!$auth->can_access_accounting())
            wp_send_json_error('Unauthorized');

        $id = intval($_POST['id']);
        $expense_acc = intval($_POST['expense_account_id']);
        $payable_acc = intval($_POST['payable_account_id']);

        try {
            $this->service->approve($id, $expense_acc, $payable_acc);
            wp_send_json_success(['message' => 'Reimbursement approved and journal entry created.']);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_pay_reimbursement()
    {
        check_ajax_referer('obn_reimbursement_nonce', 'security');
        $auth = new OBN_Auth();
        if (!$auth->can_access_accounting())
            wp_send_json_error('Unauthorized');

        $id = intval($_POST['id']);
        $payment_acc = intval($_POST['payment_account_id']);
        $payable_acc = intval($_POST['payable_account_id']);

        try {
            $this->service->process_payment($id, $payment_acc, $payable_acc);
            wp_send_json_success(['message' => 'Payment processed and journal entry created.']);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_reject_reimbursement()
    {
        check_ajax_referer('obn_reimbursement_nonce', 'security');
        $id = intval($_POST['id']);
        $note = sanitize_textarea_field($_POST['note']);
        try {
            $this->service->reject($id, $note);
            wp_send_json_success(['message' => 'Reimbursement rejected.']);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_get_reimbursement()
    {
        check_ajax_referer('obn_reimbursement_nonce', 'security');
        $id = intval($_POST['id']);
        $data = $this->repository->find_by_id($id);
        if ($data) {
            wp_send_json_success($data);
        }
        wp_send_json_error('Not found');
    }

    public function ajax_delete_reimbursement()
    {
        check_ajax_referer('obn_reimbursement_nonce', 'security');
        // Only draft can be deleted
        $id = intval($_POST['id']);
        global $wpdb;
        $reimbursement = $this->repository->find_by_id($id);
        if (!$reimbursement)
            wp_send_json_error('Reimbursement not found.');
        if ($reimbursement->status !== 'Draft')
            wp_send_json_error('Only drafts can be deleted.');
        try {
            $this->service->assert_reimbursement_modifiable($id, $reimbursement);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), 409);
        }

        $wpdb->delete($wpdb->prefix . 'orabooks_reimbursements', ['id' => $id]);
        $wpdb->delete($wpdb->prefix . 'orabooks_reimbursement_items', ['reimbursement_id' => $id]);
        wp_send_json_success('Deleted');
    }

    private function handle_uploads($files)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $attachments = [];
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key]
                ];
                $upload = wp_handle_upload($file, ['test_form' => false]);
                if (isset($upload['url'])) {
                    $attachments[] = [
                        'file_url' => $upload['url'],
                        'file_name' => $name,
                        'file_type' => $files['type'][$key]
                    ];
                }
            }
        }
        return $attachments;
    }

    public function ensure_sidebar_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_sidebar';

        // Check for Reimbursement parent
        $parent_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'reimbursements', 'accounting'));
        if (!$parent_id) {
            $wpdb->insert($table_name, [
                "module" => "accounting",
                "parent" => 0,
                "menu_title" => "Reimbursements",
                "menu_slug" => "reimbursements",
                "icon" => "fa-solid fa-hand-holding-dollar",
                "sort_order" => 8,
                "status" => 1
            ]);
            $parent_id = $wpdb->insert_id;
        }

        $items = [
            ['menu_title' => 'My Reimbursements', 'menu_slug' => 'reimbursement-list', 'icon' => 'fa-solid fa-list', 'sort_order' => 1],
            ['menu_title' => 'New Request', 'menu_slug' => 'reimbursement-add', 'icon' => 'fa-solid fa-plus-circle', 'sort_order' => 2],
            ['menu_title' => 'Pending Approvals', 'menu_slug' => 'reimbursement-approvals', 'icon' => 'fa-solid fa-user-check', 'sort_order' => 3],
        ];

        foreach ($items as $item) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s", $item['menu_slug'], 'accounting'));
            if (!$exists) {
                $wpdb->insert($table_name, [
                    "module" => "accounting",
                    "parent" => $parent_id,
                    "menu_title" => $item['menu_title'],
                    "menu_slug" => $item['menu_slug'],
                    "icon" => $item['icon'],
                    "sort_order" => $item['sort_order'],
                    "status" => 1
                ]);
            }
        }
    }
}

new OBN_Reimbursement();
