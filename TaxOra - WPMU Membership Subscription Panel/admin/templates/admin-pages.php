<?php
/**
 * Frontend Pages Admin Template
 */

// Get current data
$pages = $this->get_all_pages();
?>

<div class="taxora-admin-pages">
    <div class="taxora-admin-header">
        <h1>Frontend Pages</h1>
        <div class="taxora-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=taxora-pages&action=add'); ?>" class="button button-primary">Add New Page</a>
            <a href="<?php echo admin_url('admin.php?page=taxora-settings'); ?>" class="button button-secondary">Settings</a>
        </div>
    </div>
    
    <div class="taxora-admin-stats">
        <div class="taxora-stat-item">
            <span class="taxora-stat-label">Total Pages:</span>
            <span class="taxora-stat-value"><?php echo count($pages); ?></span>
        </div>
        <div class="taxora-stat-item">
            <span class="taxora-stat-label">Active Pages:</span>
            <span class="taxora-stat-value"><?php echo count(array_filter($pages, function($page) { return $page->is_active; })); ?></span>
        </div>
    </div>
    
    <div class="taxora-pages-container">
        <?php if (empty($pages)): ?>
            <div class="taxora-no-data">
                <p>No frontend pages found. <a href="<?php echo admin_url('admin.php?page=taxora-pages&action=add'); ?>">Create your first page</a>.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped taxora-pages-table">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-checkbox"><input type="checkbox" id="taxora-select-all-pages"></th>
                        <th scope="col" class="manage-column column-name">Page Title</th>
                        <th scope="col" class="manage-column column-slug">URL Slug</th>
                        <th scope="col" class="manage-column column-type">Type</th>
                        <th scope="col" class="manage-column column-status">Status</th>
                        <th scope="col" class="manage-column column-date">Created</th>
                        <th scope="col" class="manage-column column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="page_ids[]" value="<?php echo $page->id; ?>">
                            </th>
                            <td class="name-column">
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=taxora-pages&action=edit&id=' . $page->id); ?>">
                                        <?php echo esc_html($page->title); ?>
                                    </a>
                                </strong>
                            </td>
                            <td class="slug-column">
                                <code><?php echo esc_html($page->slug); ?></code>
                            </td>
                            <td class="type-column">
                                <span class="taxora-page-type taxora-page-type-<?php echo $page->type; ?>">
                                    <?php echo ucfirst($page->type); ?>
                                </span>
                            </td>
                            <td class="status-column">
                                <span class="taxora-status taxora-status-<?php echo $page->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $page->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="date-column">
                                <?php echo date('M j, Y', strtotime($page->created_at)); ?>
                            </td>
                            <td class="actions-column">
                                <div class="taxora-action-buttons">
                                    <a href="<?php echo admin_url('admin.php?page=taxora-pages&action=edit&id=' . $page->id); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo home_url('/' . $page->slug); ?>" class="button button-small" target="_blank">View</a>
                                    <a href="<?php echo admin_url('admin.php?page=taxora-pages&action=delete&id=' . $page->id); ?>" class="button button-small button-danger" onclick="return confirm('Are you sure you want to delete this page?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="taxora-admin-footer">
        <p><strong>Page Types:</strong> Landing Page, Pricing Page, Registration Page, Login Page, Dashboard, Upgrade Page, etc.</p>
        <p><strong>Tip:</strong> Use shortcodes like [taxora_landing], [taxora_pricing], [taxora_register] to display these pages on your site.</p>
    </div>
</div>

<?php
// Helper methods
class Taxora_Pages_Helper {
    
    public function get_all_pages() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        return $wpdb->get_results("SELECT * FROM {$prefix}taxora_pages ORDER BY created_at DESC");
    }
    
    public function get_total_subscriptions_count() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}taxora_subscriptions WHERE status = 'active'");
        return $count ? $count : 0;
    }
}

$pages_helper = new Taxora_Pages_Helper();
