<?php
/**
 * Custom Signup Validation
 * Allows hyphens in site names (subdomains)
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wpmu_validate_blog_signup', 'orabooks_allow_hyphens_in_site_name');

function orabooks_allow_hyphens_in_site_name($result) {
    $blogname = $result['blogname'];
    $errors = $result['errors'];

    // Only proceed if there is a blogname error
    if ( $errors->get_error_code( 'blogname' ) ) {
        // Check if the blogname contains valid characters including hyphens
        // And ensure it doesn't contain INVALID characters (anything other than a-z0-9-)
        if ( ! preg_match( '/[^a-z0-9-]+/', $blogname ) ) {
            // It only contains a-z, 0-9, and hyphens.
            
            // Now check if the specific error message is present
            $messages = $errors->get_error_messages( 'blogname' );
            foreach ( $messages as $message ) {
                if ( $message === __( 'Site names can only contain lowercase letters (a-z) and numbers.' ) ) {
                    // This is the error we want to remove.
                    
                    // Reconstruct the WP_Error object to remove this specific error
                    $codes = $errors->get_error_codes();
                    $new_errors = new WP_Error();
                    
                    foreach ( $codes as $code ) {
                        $msgs = $errors->get_error_messages( $code );
                        foreach ( $msgs as $msg ) {
                            if ( $code === 'blogname' && $msg === __( 'Site names can only contain lowercase letters (a-z) and numbers.' ) ) {
                                // Skip this error
                                continue;
                            }
                            $new_errors->add( $code, $msg );
                        }
                    }
                    
                    // Check for start/end hyphens which are generally bad practice
                    if (substr($blogname, 0, 1) === '-' || substr($blogname, -1) === '-') {
                        $new_errors->add('blogname', __('Site names cannot start or end with a hyphen.'));
                    }
                    
                    $result['errors'] = $new_errors;
                }
            }
        }
    }
    
    return $result;
}
