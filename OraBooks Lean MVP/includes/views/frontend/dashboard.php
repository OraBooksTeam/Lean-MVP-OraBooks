<div class="orabooks-dashboard">
    <h2><?php esc_html_e('Dashboard', 'orabooks'); ?></h2>
    <div id="orabooks-dashboard-content">
        <p><?php esc_html_e('Welcome to OraBooks. Use the menu to manage customers, invoices, reports, and more.', 'orabooks'); ?></p>
        <ul class="orabooks-dashboard-links">
            <li><a href="<?php echo esc_url(orabooks_get_frontend_page_url('customers')); ?>"><?php esc_html_e('Customers', 'orabooks'); ?></a></li>
            <li><a href="<?php echo esc_url(orabooks_get_frontend_page_url('invoices')); ?>"><?php esc_html_e('Invoices', 'orabooks'); ?></a></li>
            <li><a href="<?php echo esc_url(orabooks_get_frontend_page_url('chart-of-accounts')); ?>"><?php esc_html_e('Chart of Accounts', 'orabooks'); ?></a></li>
            <li><a href="<?php echo esc_url(orabooks_get_frontend_page_url('reports')); ?>"><?php esc_html_e('Reports', 'orabooks'); ?></a></li>
            <li><a href="<?php echo esc_url(orabooks_get_frontend_page_url('notifications')); ?>"><?php esc_html_e('Notifications', 'orabooks'); ?></a></li>
            <li><a href="<?php echo esc_url(orabooks_get_frontend_page_url('profile')); ?>"><?php esc_html_e('Profile', 'orabooks'); ?></a></li>
        </ul>
    </div>
</div>
