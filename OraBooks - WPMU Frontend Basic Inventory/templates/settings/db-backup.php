<?php
/**
 * DB Backup Settings Page
 * File: templates/settings/db-backup.php
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="bg-white rounded-lg shadow-lg p-8 max-w-2xl mx-auto mt-10 text-center">
    <div class="mb-6">
        <i class="fa-solid fa-database text-6xl text-blue-600"></i>
    </div>
    
    <h1 class="text-3xl font-bold text-gray-800 mb-4">Database Backup</h1>
    
    <p class="text-gray-600 mb-8 text-lg">
        Click the button below to generate and download a full SQL backup of your database in a ZIP archive.
    </p>
    
    <form method="post">
        <?php wp_nonce_field( 'frontend_inventory_backup_action', 'frontend_inventory_backup_nonce' ); ?>
        <input type="hidden" name="frontend_inventory_action" value="download_backup">
        
        <button type="submit" class="inline-flex items-center justify-center px-8 py-4 text-base font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-colors shadow-md">
            <i class="fa-solid fa-download mr-2"></i> Download SQL Backup
        </button>
    </form>
    
    <p class="mt-6 text-sm text-gray-400">
        The backup will be downloaded automatically to your device.
    </p>
</div>
