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
                <input type="hidden" id="inv_org_id" name="org_id" value="0">
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
                <input type="hidden" id="pay_org_id" name="org_id" value="0">
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
