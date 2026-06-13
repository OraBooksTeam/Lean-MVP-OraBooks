<?php
/**
 * DB Backup Handler
 * File: includes/class-backup.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Inventory_Backup {

    public function __construct() {
        add_action( 'init', [ $this, 'handle_export' ] );
    }

    public function handle_export() {
        // Check for specific POST action
        if ( isset( $_POST['frontend_inventory_action'] ) && $_POST['frontend_inventory_action'] === 'download_backup' ) {
            
            // 1. Verify Nonce
            if ( ! isset( $_POST['frontend_ajax_nonce'] ) || ! wp_verify_nonce( $_POST['frontend_ajax_nonce'], 'frontend_ajax_nonce' ) ) {
                wp_die( 'Security check failed. Please try again.' );
            }

            // 2. Generate Backup
            $this->generate_and_download();
        }
    }

    private function generate_and_download() {
        global $wpdb;
        $db_name = DB_NAME;
        $date = date('Y-m-d_H-i-s');
        $filename_sql = $db_name . '_backup_' . $date . '.sql';
        $filename_zip = $db_name . '_backup_' . $date . '.zip';

        // Create temp SQL file
        $tmp_dir = get_temp_dir();
        $tmp_sql_path = $tmp_dir . $filename_sql;
        $handle = fopen($tmp_sql_path, 'w+');

        if (!$handle) {
            wp_die('Cannot open temporary file for writing.');
        }

        // Header
        fwrite($handle, "-- OraBooks Database Backup (Frontend Inventory)\n");
        fwrite($handle, "-- Database: $db_name\n");
        fwrite($handle, "-- Date: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($handle, "SET time_zone = \"+00:00\";\n\n");

        // Get Tables
        $tables = $wpdb->get_col("SHOW TABLES");

        foreach ($tables as $table) {
            // Drop Table
            fwrite($handle, "-- --------------------------------------------------------\n\n");
            fwrite($handle, "--\n-- Table structure for table `$table`\n--\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            
            // Create Table
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
            fwrite($handle, $create_table[1] . ";\n\n");

            // Data
            fwrite($handle, "--\n-- Dumping data for table `$table`\n--\n\n");
            
            $limit = 1000;
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            
            if ($count > 0) {
                for ($offset = 0; $offset < $count; $offset += $limit) {
                    $rows = $wpdb->get_results("SELECT * FROM `$table` LIMIT $limit OFFSET $offset", ARRAY_N);
                    
                    if ($rows) {
                        fwrite($handle, "INSERT INTO `$table` VALUES ");
                        $values_arr = [];
                        foreach ($rows as $row) {
                            $values_line = [];
                            foreach ($row as $value) {
                                if (!isset($value)) {
                                    $values_line[] = "NULL";
                                } else {
                                    $values_line[] = "'" . esc_sql($value) . "'";
                                }
                            }
                            $values_arr[] = "(" . implode(',', $values_line) . ")";
                        }
                        fwrite($handle, implode(",\n", $values_arr) . ";\n");
                    }
                }
            }
            fwrite($handle, "\n");
        }

        fclose($handle);

        // Download Choice
        if (class_exists('ZipArchive')) {
            // ZIP Creation
            $tmp_zip_path = $tmp_dir . 'backup_' . uniqid() . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($tmp_zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                // Fallback if ZIP creation fails
                $this->download_file($tmp_sql_path, $filename_sql, 'application/sql');
            } else {
                $zip->addFile($tmp_sql_path, $filename_sql);
                $zip->close();
                @unlink($tmp_sql_path); // Remove SQL after zipping
                $this->download_file($tmp_zip_path, $filename_zip, 'application/zip');
            }
        } else {
            // Fallback: Download SQL directly
            $this->download_file($tmp_sql_path, $filename_sql, 'application/sql');
        }
    }

    private function download_file($path, $filename, $content_type) {
        if (file_exists($path)) {
            // Clean buffer to avoid whitespace issues
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($path));
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            
            readfile($path);
            
            // Cleanup
            @unlink($path);
            exit;
        } else {
            wp_die("Error: Backup file not generated.");
        }
    }
}

new Frontend_Inventory_Backup();
