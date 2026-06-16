<div class="wrap orabooks-admin orabooks-customers">
    <h1><?php _e('Customers & Invoices', 'orabooks'); ?>
        <span class="orabooks-last-updated" id="orabooks-cust-updated"></span>
    </h1>

    <div class="orabooks-tab-nav" id="orabooks-cust-tabs">
        <a href="#" class="nav-tab nav-tab-active" data-tab="customers">
            <span class="dashicons dashicons-groups"></span> <?php _e('Customers', 'orabooks'); ?>
        </a>
        <a href="#" class="nav-tab" data-tab="invoices">
            <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e('Invoices', 'orabooks'); ?>
        </a>
        <a href="#" class="nav-tab" data-tab="reports">
            <span class="dashicons dashicons-chart-bar"></span> <?php _e('Reports', 'orabooks'); ?>
        </a>
    </div>

    <!-- ============================================================== -->
    <!-- TAB: CUSTOMERS                                                  -->
    <!-- ============================================================== -->
    <div id="orabooks-tab-customers" class="orabooks-tab-content" style="display:block;">

        <div class="orabooks-dash-loading" id="orabooks-cust-loading">
            <div class="orabooks-stats-grid">
                <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-number"></div></div>
                <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-number"></div></div>
                <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-number"></div></div>
                <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-number"></div></div>
            </div>
        </div>

        <div id="orabooks-customers-content" style="display:none;">

            <!-- Customer Stats Summary -->
            <div class="orabooks-stats-grid" id="orabooks-customer-stats">
                <div class="orabooks-stat-card" id="orabooks-cust-total">
                    <div class="orabooks-stat-icon" style="--stat-color:#2271b1;"><span class="dashicons dashicons-businessman"></span></div>
                    <div>
                        <h3><?php _e('Total Customers', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-number">0</p>
                    </div>
                </div>
                <div class="orabooks-stat-card" id="orabooks-cust-active">
                    <div class="orabooks-stat-icon" style="--stat-color:#00a32a;"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div>
                        <h3><?php _e('Active', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-number">0</p>
                    </div>
                </div>
                <div class="orabooks-stat-card" id="orabooks-cust-inactive">
                    <div class="orabooks-stat-icon" style="--stat-color:#cc1818;"><span class="dashicons dashicons-dismiss"></span></div>
                    <div>
                        <h3><?php _e('Inactive', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-number">0</p>
                    </div>
                </div>
                <div class="orabooks-stat-card" id="orabooks-cust-revenue">
                    <div class="orabooks-stat-icon" style="--stat-color:#dba617;"><span class="dashicons dashicons-money-alt"></span></div>
                    <div>
                        <h3><?php _e('Total Revenue', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-number">$0</p>
                    </div>
                </div>
            </div>

            <!-- Customer Filters -->
            <div class="orabooks-filters">
                <select id="orabooks-cust-filter-active">
                    <option value=""><?php _e('All Statuses', 'orabooks'); ?></option>
                    <option value="1"><?php _e('Active Only', 'orabooks'); ?></option>
                    <option value="0"><?php _e('Inactive Only', 'orabooks'); ?></option>
                </select>
                <input type="text" id="orabooks-cust-search" placeholder="<?php _e('Search by email or notes...', 'orabooks'); ?>">
                <button class="button button-primary" id="orabooks-cust-refresh-btn"><?php _e('⟳ Refresh', 'orabooks'); ?></button>
            </div>

            <!-- Customer Table -->
            <table class="wp-list-table widefat fixed striped" id="orabooks-customers-table">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'orabooks'); ?></th>
                        <th><?php _e('Email', 'orabooks'); ?></th>
                        <th><?php _e('Status', 'orabooks'); ?></th>
                        <th><?php _e('Invoices', 'orabooks'); ?></th>
                        <th><?php _e('Total Paid', 'orabooks'); ?></th>
                        <th><?php _e('Last Payment', 'orabooks'); ?></th>
                        <th><?php _e('Actions', 'orabooks'); ?></th>
                    </tr>
                </thead>
                <tbody id="orabooks-customers-tbody">
                    <tr><td colspan="7"><?php _e('No customers found.', 'orabooks'); ?></td></tr>
                </tbody>
            </table>

            <!-- Customer Detail Panel -->
            <div id="orabooks-customer-detail" class="orabooks-detail-panel" style="display:none;">
                <div class="orabooks-detail-header">
                    <h3 id="orabooks-cust-detail-title"><?php _e('Customer Detail', 'orabooks'); ?></h3>
                    <button class="button orabooks-detail-close" id="orabooks-cust-detail-close">&times;</button>
                </div>
                <div id="orabooks-cust-detail-body"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- TAB: INVOICES                                                   -->
    <!-- ============================================================== -->
    <div id="orabooks-tab-invoices" class="orabooks-tab-content" style="display:none;">

        <div class="orabooks-export-actions">
            <button class="button button-primary" id="orabooks-inv-create-btn">
                <span class="dashicons dashicons-plus-alt2"></span> <?php _e('New Invoice', 'orabooks'); ?>
            </button>
            <span class="orabooks-export-actions-spacer"></span>
            <select id="orabooks-inv-filter-status">
                <option value=""><?php _e('All Payment Statuses', 'orabooks'); ?></option>
                <option value="unpaid"><?php _e('Unpaid', 'orabooks'); ?></option>
                <option value="partial"><?php _e('Partial', 'orabooks'); ?></option>
                <option value="paid"><?php _e('Paid', 'orabooks'); ?></option>
                <option value="overdue"><?php _e('Overdue', 'orabooks'); ?></option>
                <option value="cancelled"><?php _e('Cancelled', 'orabooks'); ?></option>
            </select>
            <select id="orabooks-inv-filter-workflow">
                <option value=""><?php _e('All Workflow', 'orabooks'); ?></option>
                <option value="draft"><?php _e('Draft', 'orabooks'); ?></option>
                <option value="sent"><?php _e('Sent', 'orabooks'); ?></option>
                <option value="posted"><?php _e('Posted', 'orabooks'); ?></option>
                <option value="cancelled"><?php _e('Cancelled', 'orabooks'); ?></option>
            </select>
            <input type="date" id="orabooks-inv-filter-from" title="<?php _e('From date', 'orabooks'); ?>">
            <input type="date" id="orabooks-inv-filter-to" title="<?php _e('To date', 'orabooks'); ?>">
            <button class="button" id="orabooks-inv-filter-btn"><?php _e('⟳ Filter', 'orabooks'); ?></button>
        </div>

        <table class="wp-list-table widefat fixed striped" id="orabooks-invoices-table">
            <thead>
                <tr>
                    <th><?php _e('Invoice #', 'orabooks'); ?></th>
                    <th><?php _e('Customer', 'orabooks'); ?></th>
                    <th><?php _e('Date', 'orabooks'); ?></th>
                    <th><?php _e('Due Date', 'orabooks'); ?></th>
                    <th><?php _e('Total', 'orabooks'); ?></th>
                    <th><?php _e('Paid', 'orabooks'); ?></th>
                    <th><?php _e('Status', 'orabooks'); ?></th>
                    <th><?php _e('Actions', 'orabooks'); ?></th>
                </tr>
            </thead>
            <tbody id="orabooks-invoices-tbody">
                <tr><td colspan="8"><?php _e('No invoices found.', 'orabooks'); ?></td></tr>
            </tbody>
        </table>

        <!-- Invoice Detail Panel -->
        <div id="orabooks-invoice-detail" class="orabooks-detail-panel" style="display:none;">
            <div class="orabooks-detail-header">
                <h3 id="orabooks-inv-detail-title"><?php _e('Invoice Detail', 'orabooks'); ?></h3>
                <button class="button orabooks-detail-close" id="orabooks-inv-detail-close">&times;</button>
            </div>
            <div id="orabooks-inv-detail-body"></div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- TAB: REPORTS                                                    -->
    <!-- ============================================================== -->
    <div id="orabooks-tab-reports" class="orabooks-tab-content" style="display:none;">
        <div class="orabooks-section-header">
            <h2><?php _e('AR & Revenue Summary', 'orabooks'); ?></h2>
            <p class="orabooks-section-description"><?php _e('Aggregated metrics across all organizations.', 'orabooks'); ?></p>
        </div>

        <div class="orabooks-stats-grid" id="orabooks-ar-stats">
            <div class="orabooks-stat-card">
                <div class="orabooks-stat-icon" style="--stat-color:#2271b1;"><span class="dashicons dashicons-chart-area"></span></div>
                <div>
                    <h3><?php _e('Total Revenue', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-ar-revenue">$0</p>
                </div>
            </div>
            <div class="orabooks-stat-card">
                <div class="orabooks-stat-icon" style="--stat-color:#cc1818;"><span class="dashicons dashicons-arrow-up-alt"></span></div>
                <div>
                    <h3><?php _e('Outstanding AR', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-ar-outstanding">$0</p>
                </div>
            </div>
            <div class="orabooks-stat-card">
                <div class="orabooks-stat-icon" style="--stat-color:#00a32a;"><span class="dashicons dashicons-yes"></span></div>
                <div>
                    <h3><?php _e('Paid Invoices', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-ar-paid">0</p>
                </div>
            </div>
            <div class="orabooks-stat-card">
                <div class="orabooks-stat-icon" style="--stat-color:#dba617;"><span class="dashicons dashicons-warning"></span></div>
                <div>
                    <h3><?php _e('Overdue', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-ar-overdue">0</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: Create Invoice                                          -->
<!-- ============================================================== -->
<div id="orabooks-invoice-modal" class="orabooks-modal" style="display:none;">
    <div class="orabooks-modal-backdrop"></div>
    <div class="orabooks-modal-content">
        <div class="orabooks-modal-header">
            <h3><?php _e('Create Invoice', 'orabooks'); ?></h3>
            <button class="orabooks-modal-close">&times;</button>
        </div>
        <div class="orabooks-modal-body">
            <form id="orabooks-invoice-form">
                <table class="form-table">
                    <tr>
                        <th><label for="inv_customer_id"><?php _e('Customer', 'orabooks'); ?> <span class="required">*</span></label></th>
                        <td>
                            <select id="inv_customer_id" name="customer_id" required>
                                <option value=""><?php _e('Select customer...', 'orabooks'); ?></option>
                            </select>
                            <p class="description"><?php _e('Select the customer to invoice.', 'orabooks'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="inv_invoice_number"><?php _e('Invoice #', 'orabooks'); ?></label></th>
                        <td>
                            <input type="text" id="inv_invoice_number" name="invoice_number" placeholder="<?php _e('Auto-generated if empty', 'orabooks'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="inv_invoice_date"><?php _e('Invoice Date', 'orabooks'); ?></label></th>
                        <td><input type="date" id="inv_invoice_date" name="invoice_date"></td>
                    </tr>
                    <tr>
                        <th><label for="inv_due_days"><?php _e('Due In (days)', 'orabooks'); ?></label></th>
                        <td>
                            <input type="number" id="inv_due_days" name="due_days" value="30" min="1" max="365">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="inv_total_amount"><?php _e('Total Amount', 'orabooks'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="number" id="inv_total_amount" name="total_amount" step="0.01" min="0.01" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="inv_tax_amount"><?php _e('Tax Amount', 'orabooks'); ?></label></th>
                        <td><input type="number" id="inv_tax_amount" name="tax_amount" step="0.01" min="0" value="0"></td>
                    </tr>
                    <tr>
                        <th><label for="inv_currency"><?php _e('Currency', 'orabooks'); ?></label></th>
                        <td>
                            <select id="inv_currency" name="currency">
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="inv_description"><?php _e('Description', 'orabooks'); ?></label></th>
                        <td><textarea id="inv_description" name="description" rows="3"></textarea></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Create Invoice', 'orabooks'); ?></button>
                    <button type="button" class="button orabooks-modal-cancel"><?php _e('Cancel', 'orabooks'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: Record Payment                                          -->
<!-- ============================================================== -->
<div id="orabooks-payment-modal" class="orabooks-modal" style="display:none;">
    <div class="orabooks-modal-backdrop"></div>
    <div class="orabooks-modal-content">
        <div class="orabooks-modal-header">
            <h3><?php _e('Record Payment', 'orabooks'); ?></h3>
            <button class="orabooks-modal-close">&times;</button>
        </div>
        <div class="orabooks-modal-body">
            <form id="orabooks-payment-form">
                <input type="hidden" id="pay_invoice_id" name="invoice_id">
                <table class="form-table">
                    <tr>
                        <th><label for="pay_invoice_number"><?php _e('Invoice', 'orabooks'); ?></label></th>
                        <td><strong id="pay_invoice_number">—</strong></td>
                    </tr>
                    <tr>
                        <th><label for="pay_amount"><?php _e('Payment Amount', 'orabooks'); ?> <span class="required">*</span></label></th>
                        <td><input type="number" id="pay_amount" name="amount" step="0.01" min="0.01" required></td>
                    </tr>
                    <tr>
                        <th><label for="pay_date"><?php _e('Payment Date', 'orabooks'); ?></label></th>
                        <td><input type="date" id="pay_date" name="payment_date"></td>
                    </tr>
                    <tr>
                        <th><label for="pay_method"><?php _e('Method', 'orabooks'); ?></label></th>
                        <td>
                            <select id="pay_method" name="payment_method">
                                <option value="bank_transfer"><?php _e('Bank Transfer', 'orabooks'); ?></option>
                                <option value="credit_card"><?php _e('Credit Card', 'orabooks'); ?></option>
                                <option value="cash"><?php _e('Cash', 'orabooks'); ?></option>
                                <option value="check"><?php _e('Check', 'orabooks'); ?></option>
                                <option value="other"><?php _e('Other', 'orabooks'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pay_reference"><?php _e('Reference', 'orabooks'); ?></label></th>
                        <td><input type="text" id="pay_reference" name="reference" placeholder="<?php _e('Check #, transaction ID...', 'orabooks'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="pay_notes"><?php _e('Notes', 'orabooks'); ?></label></th>
                        <td><textarea id="pay_notes" name="notes" rows="2"></textarea></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Record Payment', 'orabooks'); ?></button>
                    <button type="button" class="button orabooks-modal-cancel"><?php _e('Cancel', 'orabooks'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
// ==============================================================
// Admin Customers & Invoices JS (inline for admin page)
// ==============================================================
jQuery(document).ready(function($) {

    var currentTab = 'customers';
    var currentOrgId = 0;

    // ==================== INIT ====================
    // Get the first organization ID for filtering context
    $.get(orabooks_ajax.ajax_url, {
        action: 'orabooks_customer_stats',
        org_id: 0
    }, function(r) {
        if (r.success !== false) {
            loadCustomers();
            loadReports(r.data);
        }
    });

    // ==================== TAB SWITCHING ====================
    $(document).on('click', '#orabooks-cust-tabs .nav-tab', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        currentTab = tab;

        $('#orabooks-cust-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.orabooks-tab-content').hide();
        $('#orabooks-tab-' + tab).fadeIn(200);

        if (tab === 'invoices') loadInvoices();
        if (tab === 'reports') loadReports();
    });

    // ==================== CUSTOMERS ====================
    window.loadCustomers = function() {
        var $loading = $('#orabooks-cust-loading').show();
        var $content = $('#orabooks-customers-content').hide();

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_stats',
            org_id: 0
        }, function(r) {
            $('#orabooks-cust-updated').text(new Date().toLocaleTimeString());
            $loading.hide();
            $content.fadeIn(200);

            if (r.data) {
                renderCustomerStats(r.data);
            }
        });

        loadCustomerList();
    };

    function renderCustomerStats(stats) {
        $('#orabooks-cust-total .orabooks-stat-number').text(stats.total_customers || 0);
        $('#orabooks-cust-active .orabooks-stat-number').text(stats.active_customers || 0);
        $('#orabooks-cust-inactive .orabooks-stat-number').text(stats.inactive_customers || 0);
        $('#orabooks-cust-revenue .orabooks-stat-number').text('$' + (parseFloat(stats.total_revenue || 0)).toLocaleString('en-US', {minimumFractionDigits: 2}));
    }

    function loadCustomerList() {
        var isActive = $('#orabooks-cust-filter-active').val();
        var search = $('#orabooks-cust-search').val();

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customers_list',
            org_id: 0,
            is_active: isActive || null,
            search: search,
            limit: 100,
            offset: 0
        }, function(r) {
            var tbody = $('#orabooks-customers-tbody');
            tbody.empty();

            if (!r.data || !r.data.customers || r.data.customers.length === 0) {
                tbody.html('<tr><td colspan="7"><?php _e('No customers found.', 'orabooks'); ?></td></tr>');
                return;
            }

            $.each(r.data.customers, function(i, c) {
                var statusBadge = c.is_active == 1
                    ? '<span class="orabooks-badge orabooks-badge-active"><?php _e('Active', 'orabooks'); ?></span>'
                    : '<span class="orabooks-badge orabooks-badge-inactive"><?php _e('Inactive', 'orabooks'); ?></span>';

                var totalPaid = parseFloat(c.total_paid || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});

                tbody.append('<tr>' +
                    '<td>' + c.id + '</td>' +
                    '<td><a href="#" class="orabooks-cust-view" data-id="' + c.id + '">' + $('<span>').text(c.email).html() + '</a></td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + (c.invoice_count || 0) + '</td>' +
                    '<td>' + totalPaid + '</td>' +
                    '<td>' + (c.last_paid_invoice_date || '—') + '</td>' +
                    '<td>' +
                        '<button class="button button-small orabooks-cust-toggle-active" data-id="' + c.id + '" data-active="' + c.is_active + '">' +
                            (c.is_active == 1 ? '<?php _e('Deactivate', 'orabooks'); ?>' : '<?php _e('Activate', 'orabooks'); ?>') +
                        '</button> ' +
                        '<button class="button button-small orabooks-cust-view" data-id="' + c.id + '"><?php _e('View', 'orabooks'); ?></button>' +
                    '</td>' +
                    '</tr>');
            });
        });
    }

    // Filter customers
    $(document).on('change', '#orabooks-cust-filter-active', loadCustomerList);
    $(document).on('click', '#orabooks-cust-refresh-btn', loadCustomerList);

    // Search on enter
    $(document).on('keydown', '#orabooks-cust-search', function(e) {
        if (e.keyCode === 13) loadCustomerList();
    });

    // Toggle active status
    $(document).on('click', '.orabooks-cust-toggle-active', function() {
        var $btn = $(this);
        var customerId = $btn.data('id');
        var isActive = $btn.data('active') == 1 ? 0 : 1;

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_update',
            customer_id: customerId,
            is_active: isActive
        }, function(r) {
            if (r.error !== false && r.error !== true) return; // both formats
            loadCustomerList();
        });
    });

    // View customer detail
    $(document).on('click', '.orabooks-cust-view', function(e) {
        e.preventDefault();
        var customerId = $(this).data('id');
        showCustomerDetail(customerId);
    });

    function showCustomerDetail(customerId) {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_get',
            user_id: customerId
        }, function(r) {
            if (r.error !== false && r.error !== true) return;
            var c = r.data;
            var html = '<table class="form-table">' +
                '<tr><th><?php _e('ID', 'orabooks'); ?></th><td>' + c.id + '</td></tr>' +
                '<tr><th><?php _e('Email', 'orabooks'); ?></th><td>' + $('<span>').text(c.email).html() + '</td></tr>' +
                '<tr><th><?php _e('Status', 'orabooks'); ?></th><td>' + (c.is_active == 1 ? '<span class="orabooks-badge orabooks-badge-active"><?php _e('Active', 'orabooks'); ?></span>' : '<span class="orabooks-badge orabooks-badge-inactive"><?php _e('Inactive', 'orabooks'); ?></span>') + '</td></tr>' +
                '<tr><th><?php _e('Last Payment', 'orabooks'); ?></th><td>' + (c.last_paid_invoice_date || '—') + '</td></tr>' +
                '<tr><th><?php _e('Lifetime Value', 'orabooks'); ?></th><td>' + parseFloat(c.lifetime_value || 0).toLocaleString('en-US', {style:'currency', currency:'USD'}) + '</td></tr>' +
                '<tr><th><?php _e('Verified', 'orabooks'); ?></th><td>' + (c.is_email_verified == 1 ? '<?php _e('Yes', 'orabooks'); ?>' : '<?php _e('No', 'orabooks'); ?>') + '</td></tr>' +
                '<tr><th><?php _e('Notes', 'orabooks'); ?></th><td><textarea id="orabooks-cust-notes" rows="3" style="width:100%;">' + (c.notes || '') + '</textarea>' +
                '<p><button class="button orabooks-cust-save-notes" data-id="' + c.id + '"><?php _e('Save Notes', 'orabooks'); ?></button></p></td></tr>' +
                '</table>';

            $('#orabooks-cust-detail-title').text('<?php _e('Customer: ', 'orabooks'); ?>' + c.email);
            $('#orabooks-cust-detail-body').html(html);
            $('#orabooks-customer-detail').fadeIn(200);
        });
    }

    $(document).on('click', '#orabooks-cust-detail-close', function() {
        $('#orabooks-customer-detail').fadeOut(200);
    });

    $(document).on('click', '.orabooks-cust-save-notes', function() {
        var customerId = $(this).data('id');
        var notes = $('#orabooks-cust-notes').val();

        $.post(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_update',
            customer_id: customerId,
            notes: notes
        }, function(r) {
            alert('<?php _e('Notes saved.', 'orabooks'); ?>');
        });
    });

    // ==================== INVOICES ====================
    window.loadInvoices = function() {
        var status = $('#orabooks-inv-filter-status').val();
        var workflow = $('#orabooks-inv-filter-workflow').val();
        var from = $('#orabooks-inv-filter-from').val();
        var to = $('#orabooks-inv-filter-to').val();

        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_invoices_list',
            org_id: 0,
            payment_status: status,
            workflow_status: workflow,
            from_date: from,
            to_date: to,
            limit: 100,
            offset: 0
        }, function(r) {
            var tbody = $('#orabooks-invoices-tbody');
            tbody.empty();

            if (!r.data || !r.data.invoices || r.data.invoices.length === 0) {
                tbody.html('<tr><td colspan="8"><?php _e('No invoices found.', 'orabooks'); ?></td></tr>');
                return;
            }

            $.each(r.data.invoices, function(i, inv) {
                var pct = inv.total_amount > 0 ? Math.round((inv.total_paid_amount / inv.total_amount) * 100) : 0;
                var statusHtml = getInvoiceStatusHtml(inv.payment_status);
                var total = parseFloat(inv.total_amount || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});
                var paid = parseFloat(inv.total_paid_amount || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});

                tbody.append('<tr>' +
                    '<td><a href="#" class="orabooks-inv-view" data-id="' + inv.id + '">' + $('<span>').text(inv.invoice_number).html() + '</a></td>' +
                    '<td>' + $('<span>').text(inv.customer_email).html() + '</td>' +
                    '<td>' + inv.transaction_date + '</td>' +
                    '<td>' + inv.due_date + '</td>' +
                    '<td>' + total + '</td>' +
                    '<td>' + paid + ' <span class="orabooks-inv-progress">(' + pct + '%)</span></td>' +
                    '<td>' + statusHtml + '</td>' +
                    '<td>' +
                        (inv.payment_status !== 'paid' && inv.payment_status !== 'cancelled'
                            ? '<button class="button button-small orabooks-inv-pay" data-id="' + inv.id + '" data-number="' + $('<span>').text(inv.invoice_number).html() + '"><?php _e('Pay', 'orabooks'); ?></button> '
                            : '') +
                        '<button class="button button-small orabooks-inv-view" data-id="' + inv.id + '"><?php _e('View', 'orabooks'); ?></button>' +
                    '</td>' +
                    '</tr>');
            });
        });
    };

    function getInvoiceStatusHtml(status) {
        var map = {
            'unpaid': ['orabooks-badge-warning', '<?php _e('Unpaid', 'orabooks'); ?>'],
            'partial': ['orabooks-badge-info', '<?php _e('Partial', 'orabooks'); ?>'],
            'paid': ['orabooks-badge-active', '<?php _e('Paid', 'orabooks'); ?>'],
            'overdue': ['orabooks-badge-danger', '<?php _e('Overdue', 'orabooks'); ?>'],
            'cancelled': ['orabooks-badge-inactive', '<?php _e('Cancelled', 'orabooks'); ?>']
        };
        var m = map[status] || ['orabooks-badge-warning', status];
        return '<span class="orabooks-badge ' + m[0] + '">' + m[1] + '</span>';
    }

    // Invoice filters
    $(document).on('click', '#orabooks-inv-filter-btn', loadInvoices);

    // Create Invoice Modal
    $(document).on('click', '#orabooks-inv-create-btn', function() {
        // Load customers into select
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customers_list',
            org_id: 0,
            limit: 500,
            offset: 0
        }, function(r) {
            var sel = $('#inv_customer_id');
            sel.empty().append('<option value=""><?php _e('Select customer...', 'orabooks'); ?></option>');
            if (r.data && r.data.customers) {
                $.each(r.data.customers, function(i, c) {
                    sel.append('<option value="' + c.id + '">' + $('<span>').text(c.email).html() + '</option>');
                });
            }
        });

        $('#inv_invoice_date').val(new Date().toISOString().slice(0, 10));
        $('#orabooks-invoice-modal').fadeIn(200);
    });

    // Submit create invoice
    $(document).on('submit', '#orabooks-invoice-form', function(e) {
        e.preventDefault();
        var data = $(this).serialize();
        data += '&action=orabooks_invoice_create&org_id=' + (currentOrgId || 0);

        $.post(orabooks_ajax.ajax_url, data, function(r) {
            if (r.error !== false && r.error !== true) return;

            $('#orabooks-invoice-modal').fadeOut(200);
            $('#orabooks-invoice-form')[0].reset();
            loadInvoices();
            alert('<?php _e('Invoice created successfully!', 'orabooks'); ?>');
        }).fail(function() {
            alert('<?php _e('Failed to create invoice.', 'orabooks'); ?>');
        });
    });

    // View Invoice Detail
    $(document).on('click', '.orabooks-inv-view', function(e) {
        e.preventDefault();
        var invoiceId = $(this).data('id');
        showInvoiceDetail(invoiceId);
    });

    function showInvoiceDetail(invoiceId) {
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_invoice_get',
            invoice_id: invoiceId
        }, function(r) {
            if (r.error !== false && r.error !== true) return;
            var inv = r.data;
            var total = parseFloat(inv.total_amount || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});
            var paid = parseFloat(inv.paid_amount || 0).toLocaleString('en-US', {style:'currency', currency:'USD'});

            // Build payments table
            var paymentsHtml = '';
            if (inv.payments && inv.payments.length > 0) {
                paymentsHtml = '<h4><?php _e('Payments', 'orabooks'); ?></h4><table class="wp-list-table widefat fixed striped"><thead><tr><th><?php _e('Date', 'orabooks'); ?></th><th><?php _e('Amount', 'orabooks'); ?></th><th><?php _e('Method', 'orabooks'); ?></th><th><?php _e('Reference', 'orabooks'); ?></th></tr></thead><tbody>';
                $.each(inv.payments, function(i, p) {
                    paymentsHtml += '<tr><td>' + p.payment_date + '</td><td>' + parseFloat(p.amount).toLocaleString('en-US', {style:'currency', currency:'USD'}) + '</td><td>' + p.payment_method + '</td><td>' + (p.reference || '—') + '</td></tr>';
                });
                paymentsHtml += '</tbody></table>';
            }

            var html = '<table class="form-table">' +
                '<tr><th><?php _e('Invoice #', 'orabooks'); ?></th><td><strong>' + $('<span>').text(inv.invoice_number).html() + '</strong></td></tr>' +
                '<tr><th><?php _e('Customer', 'orabooks'); ?></th><td>' + $('<span>').text(inv.customer_email).html() + '</td></tr>' +
                '<tr><th><?php _e('Date', 'orabooks'); ?></th><td>' + inv.transaction_date + '</td></tr>' +
                '<tr><th><?php _e('Due Date', 'orabooks'); ?></th><td>' + inv.due_date + '</td></tr>' +
                '<tr><th><?php _e('Total', 'orabooks'); ?></th><td><strong>' + total + '</strong></td></tr>' +
                '<tr><th><?php _e('Paid', 'orabooks'); ?></th><td>' + paid + '</td></tr>' +
                '<tr><th><?php _e('Balance Due', 'orabooks'); ?></th><td><strong>' + parseFloat(inv.total_amount - inv.paid_amount).toLocaleString('en-US', {style:'currency', currency:'USD'}) + '</strong></td></tr>' +
                '<tr><th><?php _e('Status', 'orabooks'); ?></th><td>' + getInvoiceStatusHtml(inv.payment_status) + '</td></tr>' +
                '<tr><th><?php _e('Description', 'orabooks'); ?></th><td>' + (inv.description || '—') + '</td></tr>' +
                '</table>' +
                paymentsHtml;

            $('#orabooks-inv-detail-title').text('<?php _e('Invoice: ', 'orabooks'); ?>' + inv.invoice_number);
            $('#orabooks-inv-detail-body').html(html);
            $('#orabooks-invoice-detail').fadeIn(200);
        });
    }

    $(document).on('click', '#orabooks-inv-detail-close', function() {
        $('#orabooks-invoice-detail').fadeOut(200);
    });

    // Record Payment Modal
    $(document).on('click', '.orabooks-inv-pay', function() {
        var invoiceId = $(this).data('id');
        var invoiceNumber = $(this).data('number');
        $('#pay_invoice_id').val(invoiceId);
        $('#pay_invoice_number').text(invoiceNumber);
        $('#pay_date').val(new Date().toISOString().slice(0, 10));
        $('#orabooks-payment-modal').fadeIn(200);
    });

    // Submit payment
    $(document).on('submit', '#orabooks-payment-form', function(e) {
        e.preventDefault();
        var data = $(this).serialize();
        data += '&action=orabooks_invoice_record_payment&org_id=' + (currentOrgId || 0);

        $.post(orabooks_ajax.ajax_url, data, function(r) {
            if (r.error !== false && r.error !== true) return;

            $('#orabooks-payment-modal').fadeOut(200);
            $('#orabooks-payment-form')[0].reset();
            var invoiceId = $('#pay_invoice_id').val();
            showInvoiceDetail(invoiceId);
            alert('<?php _e('Payment recorded!', 'orabooks'); ?>');
        }).fail(function() {
            alert('<?php _e('Failed to record payment.', 'orabooks'); ?>');
        });
    });

    // ==================== MODAL HELPERS ====================
    $(document).on('click', '.orabooks-modal-close, .orabooks-modal-cancel, .orabooks-modal-backdrop', function() {
        $(this).closest('.orabooks-modal').fadeOut(200);
    });

    // ==================== REPORTS ====================
    window.loadReports = function(data) {
        if (data) {
            renderReports(data);
            return;
        }
        $.get(orabooks_ajax.ajax_url, {
            action: 'orabooks_customer_stats',
            org_id: 0
        }, function(r) {
            if (r.data) renderReports(r.data);
        });
    };

    function renderReports(stats) {
        $('#orabooks-ar-revenue').text('$' + parseFloat(stats.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#orabooks-ar-outstanding').text('$' + parseFloat(stats.outstanding_ar || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#orabooks-ar-paid').text(stats.paid_invoices || 0);
        $('#orabooks-ar-overdue').text(stats.overdue_invoices || 0);
    }
});
</script>
