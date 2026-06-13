<?php
namespace WPFD\Core;

/**
 * Centralized Error Handler for WP Frontend Dashboard
 * Provides consistent error logging and user-friendly error messages
 */
class ErrorHandler {
    
    /**
     * Log error with context
     */
    public static function log_error($message, $context = [], $level = 'error') {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'blog_id' => get_current_blog_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        
        // Log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WPFD] ' . wp_json_encode($log_entry));
        }
        
        // Also log to custom log file if possible
        self::log_to_file($log_entry);
    }
    
    /**
     * Log to custom file
     */
    private static function log_to_file($log_entry) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wpfd-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_file = $log_dir . '/dashboard-' . date('Y-m-d') . '.log';
        
        $log_line = sprintf(
            "[%s] %s: %s %s\n",
            $log_entry['timestamp'],
            strtoupper($log_entry['level']),
            $log_entry['message'],
            !empty($log_entry['context']) ? '' : json_encode($log_entry['context'])
        );
        
        // Rotate logs if they get too large (1MB)
        if (file_exists($log_file) && filesize($log_file) > 1024 * 1024) {
            $backup_file = str_replace('.log', '-old.log', $log_file);
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }
            rename($log_file, $backup_file);
        }
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Handle REST API errors consistently
     */
    public static function handle_rest_error($error_message, $error_code = 'internal_error', $status_code = 500, $context = []) {
        self::log_error($error_message, $context, 'error');
        
        return new \WP_Error($error_code, $error_message, [
            'status' => $status_code,
            'logged' => true
        ]);
    }
    
    /**
     * Handle AJAX errors consistently
     */
    public static function handle_ajax_error($error_message, $context = []) {
        self::log_error($error_message, $context, 'error');
        
        wp_send_json_error([
            'message' => $error_message,
            'logged' => true
        ]);
    }
    
    /**
     * Handle exceptions consistently
     */
    public static function handle_exception($exception, $context = []) {
        $error_message = sprintf(
            'Exception: %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        
        self::log_error($error_message, array_merge($context, [
            'exception_trace' => $exception->getTraceAsString()
        ]), 'critical');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return new \WP_Error('exception', $exception->getMessage(), [
                'status' => 500,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
        } else {
            return new \WP_Error('internal_error', 'An internal error occurred. Please try again.', [
                'status' => 500
            ]);
        }
    }
    
    /**
     * Get error statistics (for admin dashboard)
     */
    public static function get_error_stats($hours = 24) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wpfd-logs';
        
        if (!file_exists($log_dir)) {
            return ['total_errors' => 0, 'error_types' => []];
        }
        
        $cutoff_time = time() - ($hours * 3600);
        $error_stats = ['total_errors' => 0, 'error_types' => []];
        
        // Scan recent log files
        $files = glob($log_dir . '/dashboard-*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                continue;
            }
            
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                
                $error_stats['total_errors']++;
                
                // Extract error type from log line
                if (preg_match('/\[(ERROR|CRITICAL|WARNING|NOTICE)\]/', $line, $matches)) {
                    $error_type = strtolower($matches[1]);
                    $error_stats['error_types'][$error_type] = ($error_stats['error_types'][$error_type] ?? 0) + 1;
                }
            }
        }
        
        return $error_stats;
    }
    
    /**
     * Clean up old log files
     */
    public static function cleanup_old_logs($days = 7) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wpfd-logs';
        
        if (!file_exists($log_dir)) {
            return;
        }
        
        $cutoff_time = time() - ($days * 86400);
        $files = glob($log_dir . '/dashboard-*.log*');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}
