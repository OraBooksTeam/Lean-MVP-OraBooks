<?php
namespace WPFD\Core;

class Activator {
    public static function activate() {
        // Check if Router class exists before using it
        if (class_exists('WPFD\Core\Router')) {
            (new Router)->register();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('wpfd_activated', true);
    }
    
    public static function deactivate() {
        // Flush rewrite rules on deactivation
        flush_rewrite_rules();
        
        // Remove activation flag
        delete_option('wpfd_activated');
    }
}
