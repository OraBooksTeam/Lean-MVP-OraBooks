<div class="wrap orabooks-admin">
    <?php
    echo OraBooks_Views::render('frontend/ajax-dashboard', [
        'title' => __('CSV Imports', 'orabooks'),
        'ajax_action' => 'orabooks_csv_imports_dashboard',
        'description' => __('Upload and monitor CSV import jobs.', 'orabooks'),
    ]);
    ?>
</div>
