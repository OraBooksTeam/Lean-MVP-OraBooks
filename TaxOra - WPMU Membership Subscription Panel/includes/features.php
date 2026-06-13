<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Feature Assignment Management
 *
 * Integrates with OraBooks_Tier_Features as the source of truth for per-plan feature access.
 * Addon plugins register features via the 'orabooks_available_features' filter.
 * User access is determined by their assigned level/plan mapped through tier features.
 */

// Ensure tier features class is loaded
if ( ! class_exists( 'OraBooks_Tier_Features' ) ) {
    $tier_features_path = TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-tier-features.php';
    if ( file_exists( $tier_features_path ) ) {
        require_once $tier_features_path;
    }
}

/**
 * Get features assigned to a specific level from the database
 */
function orabooks_get_level_features( $level_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->orabooks_feature_assignments} WHERE level_id = %d",
        $level_id
    ) );
}

/**
 * Resolve a user level identifier to a tier key string.
 * Handles both numeric level IDs (stored in user meta) and string tier keys.
 *
 * @param mixed $level_identifier Numeric level ID or string tier key.
 * @return string|null Tier key string or null if not resolvable.
 */
function orabooks_resolve_level_to_tier_key( $level_identifier ) {
    if ( empty( $level_identifier ) ) {
        return null;
    }

    // If it's already a non-numeric string tier key, return it directly.
    if ( ! is_numeric( $level_identifier ) ) {
        return sanitize_text_field( $level_identifier );
    }

    // It's a numeric level ID - look up the level in the database.
    global $wpdb;
    orabooks_handle_multisite_tables();

    $level = $wpdb->get_row( $wpdb->prepare(
        "SELECT build_guide_level_key, name, price, mode FROM {$wpdb->orabooks_levels} WHERE id = %d",
        intval( $level_identifier )
    ) );

    if ( $level && ! empty( $level->build_guide_level_key ) ) {
        return sanitize_text_field( $level->build_guide_level_key );
    }

    // Fallback: guess tier key from level data.
    if ( $level && function_exists( 'orabooks_guess_tier_key_from_level' ) ) {
        $guessed = orabooks_guess_tier_key_from_level( $level );
        if ( class_exists( 'OraBooks_Tier_Features' ) ) {
            return OraBooks_Tier_Features::resolve_alias( $guessed );
        }
        return $guessed;
    }

    return null;
}

/**
 * Check if a user has access to a specific feature
 * Uses OraBooks_Tier_Features as the source of truth (not just DB assignments).
 *
 * @param int    $user_id User ID to check.
 * @param string $feature_key Feature identifier.
 * @return bool Whether user can access the feature.
 */
function orabooks_user_has_feature_access( $user_id, $feature_key ) {
    // Get user's assigned level identifier (can be numeric ID or string tier key).
    $level_identifier = get_user_meta( $user_id, 'orabooks_level', true );
    if ( ! $level_identifier ) {
        return false;
    }

    // Resolve numeric level ID to string tier key.
    $tier_key = orabooks_resolve_level_to_tier_key( $level_identifier );
    if ( ! $tier_key ) {
        return false;
    }

    // Delegate to OraBooks_Tier_Features as source of truth.
    if ( class_exists( 'OraBooks_Tier_Features' ) ) {
        return OraBooks_Tier_Features::has_feature_access( $tier_key, $feature_key );
    }

    // Fallback: check database feature assignments table using numeric level ID.
    global $wpdb;
    $count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->orabooks_feature_assignments} WHERE level_id = %d AND feature_key = %s AND access_type != 'none'",
        intval( $level_identifier ), $feature_key
    ) );

    return $count > 0;
}

/**
 * Get all available features from addon plugins
 *
 * Features come from addons via the 'orabooks_available_features' filter.
 *
 * @return array Available features from addons.
 */
function orabooks_get_available_features() {
    $features = array();

    // Ensure addons are registered before getting them.
    do_action( 'orabooks_register_addons' );

    /**
     * Filter: orabooks_available_features
     * Addon plugins register their features here.
     */
    $features = apply_filters( 'orabooks_available_features', $features );

    error_log( 'OraBooks Debug: available features keys: ' . print_r( array_keys( $features ), true ) );

    return $features;
}

/**
 * Default access levels for feature / tier assignment UIs.
 *
 * @return array
 */
function orabooks_get_feature_access_levels() {
    return apply_filters( 'orabooks_feature_access_levels', array( 'full', 'limited', 'readonly', 'none' ) );
}

/**
 * Build a merged feature catalog for tier assignment (addons + tier defaults).
 *
 * @param string $tier_key Tier identifier.
 * @return array
 */
function orabooks_get_features_for_tier_assignment( $tier_key ) {
    $features = orabooks_get_available_features();
    $default_levels = orabooks_get_feature_access_levels();

    if ( class_exists( 'OraBooks_Tier_Features' ) ) {
        $tier_restrictions = OraBooks_Tier_Features::get_tier_restrictions( $tier_key );
        foreach ( $tier_restrictions as $feature_key => $restriction ) {
            if ( ! isset( $features[ $feature_key ] ) ) {
                $features[ $feature_key ] = array(
                    'name' => ucwords( str_replace( '_', ' ', $feature_key ) ),
                    'description' => '',
                    'icon' => '📦',
                    'category' => 'tier',
                    'access_levels' => $default_levels,
                );
            } elseif ( empty( $features[ $feature_key ]['access_levels'] ) ) {
                $features[ $feature_key ]['access_levels'] = $default_levels;
            }
        }
    }

    foreach ( $features as $feature_key => $feature ) {
        if ( empty( $features[ $feature_key ]['access_levels'] ) ) {
            $features[ $feature_key ]['access_levels'] = $default_levels;
        }
    }

    return $features;
}

/**
 * Guess tier key from a membership level row when build_guide_level_key is empty.
 *
 * @param object $level Level database row.
 * @return string|null
 */
function orabooks_guess_tier_key_from_level( $level ) {
    if ( ! empty( $level->build_guide_level_key ) ) {
        return $level->build_guide_level_key;
    }

    $name_lower = strtolower( (string) $level->name );
    $price = isset( $level->price ) ? (float) $level->price : 0;

    if ( strpos( $name_lower, 'enterprise' ) !== false ) {
        return 'enterprise';
    }
    if ( strpos( $name_lower, 'free' ) !== false || $price == 0 ) {
        return 'free';
    }
    if ( strpos( $name_lower, 'starter' ) !== false ) {
        if ( strpos( $name_lower, 'law' ) !== false ) {
            return 'law_starter';
        }
        if ( strpos( $name_lower, 'faith' ) !== false ) {
            return 'faith_starter';
        }
        return 'starter';
    }
    if ( strpos( $name_lower, 'standard' ) !== false ) {
        if ( strpos( $name_lower, 'law' ) !== false ) {
            return 'law_standard';
        }
        if ( strpos( $name_lower, 'faith' ) !== false ) {
            return 'faith_standard';
        }
        return 'standard';
    }
    if ( strpos( $name_lower, 'pro' ) !== false ) {
        return 'pro';
    }

    if ( $price <= 3.5 ) {
        return 'faith_starter';
    }
    if ( $price <= 5 ) {
        return 'starter';
    }
    if ( $price <= 10 ) {
        return 'standard';
    }
    if ( $price <= 15 ) {
        return 'pro';
    }

    return 'enterprise';
}

/**
 * Apply tier restrictions to feature_assignments for all matching membership levels/plans.
 *
 * @param string $tier_key Tier identifier.
 * @return int Number of levels updated.
 */
function orabooks_sync_tier_restrictions_to_levels( $tier_key ) {
    if ( ! class_exists( 'OraBooks_Tier_Features' ) ) {
        return 0;
    }

    global $wpdb;
    orabooks_handle_multisite_tables();

    $canonical = OraBooks_Tier_Features::resolve_alias( $tier_key );
    $tier_restrictions = OraBooks_Tier_Features::get_tier_restrictions( $canonical );
    $user_id = get_current_user_id();
    $synced = 0;

    $levels = $wpdb->get_results( "SELECT id, name, price, mode, build_guide_level_key FROM {$wpdb->orabooks_levels}" );
    if ( empty( $levels ) ) {
        return 0;
    }

    foreach ( $levels as $level ) {
        $level_tier = ! empty( $level->build_guide_level_key )
            ? $level->build_guide_level_key
            : orabooks_guess_tier_key_from_level( $level );

        if ( OraBooks_Tier_Features::resolve_alias( $level_tier ) !== $canonical ) {
            continue;
        }

        $wpdb->delete( $wpdb->orabooks_feature_assignments, array( 'level_id' => $level->id ) );

        foreach ( $tier_restrictions as $feature_key => $restriction ) {
            if ( ! is_array( $restriction ) ) {
                continue;
            }

            $enabled = ! empty( $restriction['enabled'] );
            $access_type = $enabled ? ( $restriction['access_level'] ?? 'full' ) : 'none';
            $limit = isset( $restriction['limit'] ) ? $restriction['limit'] : null;

            $wpdb->insert(
                $wpdb->orabooks_feature_assignments,
                array(
                    'level_id' => $level->id,
                    'feature_key' => $feature_key,
                    'feature_name' => ucwords( str_replace( '_', ' ', $feature_key ) ),
                    'access_type' => $access_type,
                    'mode' => $level->mode,
                    'settings' => wp_json_encode(
                        array(
                            'available' => $enabled,
                            'limit' => $limit,
                        )
                    ),
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                    'created_at' => current_time( 'mysql' ),
                )
            );
        }

        do_action( 'orabooks_feature_assignment_updated', $level->id );
        $synced++;
    }

    return $synced;
}

/**
 * Check if a user can access a feature (alias for orabooks_user_has_feature_access)
 */
function orabooks_check_feature_access( $user_id, $feature_key ) {
    return orabooks_user_has_feature_access( $user_id, $feature_key );
}

/**
 * CRITICAL: orabooks_is_feature_enabled()
 *
 * This function is called by ALL addon plugins to check if a feature is enabled
 * for the current user. Without this function, addons cannot determine access.
 *
 * @param string   $feature_key Feature identifier (e.g. 'accounting', 'inventory').
 * @param int|null $user_id User ID (defaults to current user).
 * @return bool Whether the feature is enabled for the user.
 */
function orabooks_is_feature_enabled( $feature_key, $user_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id ) {
        return false;
    }

    // Administrators always have access.
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    return orabooks_user_has_feature_access( $user_id, $feature_key );
}

/**
 * Get all features enabled for a specific user
 *
 * @param int $user_id User ID.
 * @return array Feature keys the user can access.
 */
function orabooks_get_user_enabled_features( $user_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id ) {
        return array();
    }

    $level_id = get_user_meta( $user_id, 'orabooks_level', true );
    if ( ! $level_id ) {
        return array();
    }

    $level_key = orabooks_resolve_level_to_tier_key( $level_id );
    if ( ! $level_key ) {
        return array();
    }

    if ( class_exists( 'OraBooks_Tier_Features' ) ) {
        $restrictions = OraBooks_Tier_Features::get_tier_restrictions( $level_key );
        $enabled = array();

        foreach ( $restrictions as $feature_key => $restriction ) {
            if ( $restriction['enabled'] && $restriction['access_level'] !== 'none' ) {
                $enabled[] = $feature_key;
            }
        }

        return $enabled;
    }

    return array();
}

// Hook for other plugins to register features.
add_action( 'orabooks_register_features', 'orabooks_register_default_features' );
// add_filter( 'orabooks_available_features', 'orabooks_add_tier_features_to_available_list', 5 );

function orabooks_register_default_features() {
    // Default features are defined in OraBooks_Tier_Features.
    // Other plugins can use this hook to add their own features.
}

/**
 * Add features defined in OraBooks_Tier_Features to the available features list
 */
function orabooks_add_tier_features_to_available_list( $features ) {
    if ( ! class_exists( 'OraBooks_Tier_Features' ) ) {
        return $features;
    }

    $all_keys = OraBooks_Tier_Features::get_all_feature_keys();
    $default_levels = orabooks_get_feature_access_levels();

    foreach ( $all_keys as $key ) {
        if ( ! isset( $features[ $key ] ) ) {
            $features[ $key ] = array(
                'name' => ucwords( str_replace( '_', ' ', $key ) ),
                'description' => 'Core feature mapping from tier definitions.',
                'icon' => '📦',
                'category' => 'core',
                'access_levels' => $default_levels,
            );
        }
    }

    return $features;
}

// Feature integration system.
function orabooks_register_feature_integration( $feature_key, $callback ) {
    add_action( 'orabooks_feature_' . $feature_key . '_loaded', $callback );
}

function orabooks_load_feature_integration( $feature_key ) {
    do_action( 'orabooks_feature_' . $feature_key . '_loaded' );
}
