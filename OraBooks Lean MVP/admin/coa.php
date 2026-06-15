<div class="wrap orabooks-admin">
    <h1><?php _e('Chart of Accounts', 'orabooks'); ?></h1>
    <div class="orabooks-filters">
        <select id="coa-org-select">
            <option value=""><?php _e('Select Organization...', 'orabooks'); ?></option>
        </select>
        <select id="coa-filter-type">
            <option value=""><?php _e('All Types', 'orabooks'); ?></option>
            <option value="asset"><?php _e('Asset', 'orabooks'); ?></option>
            <option value="liability"><?php _e('Liability', 'orabooks'); ?></option>
            <option value="equity"><?php _e('Equity', 'orabooks'); ?></option>
            <option value="revenue"><?php _e('Revenue', 'orabooks'); ?></option>
            <option value="expense"><?php _e('Expense', 'orabooks'); ?></option>
        </select>
        <button class="button" onclick="orabooksLoadCoA()"><?php _e('Load', 'orabooks'); ?></button>
    </div>

    <div class="orabooks-coa-export-actions" style="margin:16px 0;padding:12px 16px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:4px;display:flex;gap:8px;align-items:center;">
        <span style="font-weight:600;color:#1d2327;"><?php _e('Export:', 'orabooks'); ?></span>
        <button class="button button-primary orabooks-coa-export-trigger" data-export-type="coa" data-format="csv"><?php _e('Export CSV (Async)', 'orabooks'); ?></button>
        <button class="button orabooks-coa-export-trigger" data-export-type="coa" data-format="pdf"><?php _e('Export PDF (Async)', 'orabooks'); ?></button>
        <span style="color:#666;font-size:12px;margin-left:8px;">📁 <?php _e('Exports are async — you\'ll get a notification when ready.', 'orabooks'); ?></span>
    </div>

    <div id="orabooks-coa-export-msg" class="orabooks-message" style="display:none;"></div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Code', 'orabooks'); ?></th>
                <th><?php _e('Name', 'orabooks'); ?></th>
                <th><?php _e('Type', 'orabooks'); ?></th>
                <th><?php _e('Normal Balance', 'orabooks'); ?></th>
                <th><?php _e('System', 'orabooks'); ?></th>
                <th><?php _e('Active', 'orabooks'); ?></th>
            </tr>
        </thead>
        <tbody id="orabooks-coa-table-body">
            <tr><td colspan="6"><?php _e('Select an organization and click Load.', 'orabooks'); ?></td></tr>
        </tbody>
    </table>
</div>
