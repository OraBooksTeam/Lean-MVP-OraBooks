<?php
/**
 * Print Labels AJAX handlers for the Accounting module
 */
if (!defined('ABSPATH')) exit;

class OBN_Print_Labels {

    public static function init() {
        add_action('wp_ajax_obn_search_items_for_labels', [__CLASS__, 'handle_obn_search_items_for_labels']);
        add_action('wp_ajax_nopriv_obn_search_items_for_labels', [__CLASS__, 'handle_obn_search_items_for_labels']);
        add_action('wp_ajax_obn_print_labels', [__CLASS__, 'handle_print_labels']);
        add_action('wp_ajax_nopriv_obn_print_labels', [__CLASS__, 'handle_print_labels']);
    }

    public static function handle_obn_search_items_for_labels() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

        $sql = "SELECT id, item_code, item_name, sales_price, sku FROM $table WHERE status = 1 AND (item_name LIKE %s OR item_code LIKE %s OR sku LIKE %s) LIMIT 30";
        $like = '%' . $wpdb->esc_like($search) . '%';
        $items = $wpdb->get_results($wpdb->prepare($sql, $like, $like, $like));

        $results = [];
        foreach ($items as $item) {
            $results[] = [
                'id'          => $item->id,
                'item_code'   => $item->item_code,
                'item_name'   => $item->item_name,
                'sales_price' => $item->sales_price,
                'sku'         => $item->sku,
                'selected'    => in_array($item->id, isset($_GET['selected']) ? array_map('intval', (array)$_GET['selected']) : []),
            ];
        }
        wp_send_json($results);
    }

    public static function handle_print_labels() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        $items = isset($_POST['items']) ? (array) $_POST['items'] : [];
        if (empty($items)) {
            wp_send_json_error('No items selected.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $ids = array_map('intval', $items);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $items_data = $wpdb->get_results($wpdb->prepare(
            "SELECT id, item_code, item_name, sales_price, sku FROM $table WHERE id IN ($placeholders) AND status = 1",
            $ids
        ));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print Labels</title>
            <style>
                @page { margin: 10mm; }
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
                .label-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; padding: 10px; }
                .label-item { border: 1px solid #333; padding: 10px; text-align: center; page-break-inside: avoid; }
                .label-item .item-name { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
                .label-item .item-code { font-size: 11px; color: #666; margin-bottom: 2px; }
                .label-item .item-price { font-size: 13px; color: #000; }
                .label-item .item-sku { font-size: 10px; color: #999; }
                @media print {
                    body { margin: 0; padding: 0; }
                    .label-grid { gap: 5px; padding: 5px; }
                }
            </style>
        </head>
        <body>
            <div class="label-grid">
                <?php foreach ($items_data as $item): ?>
                <div class="label-item">
                    <div class="item-name"><?php echo esc_html($item->item_name); ?></div>
                    <div class="item-code"><?php echo esc_html($item->item_code); ?></div>
                    <div class="item-price">$<?php echo esc_html(number_format($item->sales_price, 2)); ?></div>
                    <?php if (!empty($item->sku)): ?>
                    <div class="item-sku">SKU: <?php echo esc_html($item->sku); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <script>
                window.onload = function() { window.print(); }
            </script>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }
}

OBN_Print_Labels::init();
