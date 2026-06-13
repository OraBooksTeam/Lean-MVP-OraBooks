<?php

if ( ! class_exists( 'OraBooks_Cache_Manager' ) ) {
    class OraBooks_Cache_Manager {
        
        private $redis;
        private $cache_prefix = 'orabooks_';
        private $default_ttl = 3600; // 1 hour
        
        public function __construct() {
            $this->init_redis();
        }
        
        private function init_redis() {
            // Try to connect to Redis if available
            if ( class_exists( 'Redis' ) ) {
                try {
                    $this->redis = new Redis();
                    $redis_host = defined( 'ORABOOKS_REDIS_HOST' ) ? ORABOOKS_REDIS_HOST : '127.0.0.1';
                    $redis_port = defined( 'ORABOOKS_REDIS_PORT' ) ? ORABOOKS_REDIS_PORT : 6379;
                    $redis_pass = defined( 'ORABOOKS_REDIS_PASSWORD' ) ? ORABOOKS_REDIS_PASSWORD : null;
                    
                    $this->redis->connect( $redis_host, $redis_port );
                    
                    if ( $redis_pass ) {
                        $this->redis->auth( $redis_pass );
                    }
                    
                    // Test connection
                    $this->redis->ping();
                    
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Redis connection failed: ' . $e->getMessage() );
                    $this->redis = null;
                }
            }
        }
        
        public function get_user_features( $user_id ) {
            $key = $this->cache_prefix . "user_features_{$user_id}";
            
            // Try Redis first
            if ( $this->redis ) {
                try {
                    $cached = $this->redis->get( $key );
                    if ( $cached ) {
                        return json_decode( $cached, true );
                    }
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Redis get failed: ' . $e->getMessage() );
                }
            }
            
            // Fallback to WordPress cache
            $cached = wp_cache_get( $key, 'orabooks' );
            if ( $cached !== false ) {
                return $cached;
            }
            
            // Fetch from database
            $features = $this->fetch_features_from_db( $user_id );
            
            // Cache the result
            $this->set( $key, $features, $this->default_ttl );
            
            return $features;
        }
        
        public function get_user_subscription( $user_id ) {
            $key = $this->cache_prefix . "user_subscription_{$user_id}";
            
            // Try Redis first
            if ( $this->redis ) {
                try {
                    $cached = $this->redis->get( $key );
                    if ( $cached ) {
                        return json_decode( $cached, true );
                    }
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Redis get failed: ' . $e->getMessage() );
                }
            }
            
            // Fallback to WordPress cache
            $cached = wp_cache_get( $key, 'orabooks' );
            if ( $cached !== false ) {
                return $cached;
            }
            
            // Fetch from database
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $subscription = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->orabooks_subscriptions} 
                 WHERE user_id = %d AND status = 'active' 
                 ORDER BY ends_at DESC LIMIT 1",
                $user_id
            ) );
            
            // Cache the result
            $this->set( $key, $subscription, $this->default_ttl );
            
            return $subscription;
        }
        
        public function get_level_features( $level_id ) {
            $key = $this->cache_prefix . "level_features_{$level_id}";
            
            // Try Redis first
            if ( $this->redis ) {
                try {
                    $cached = $this->redis->get( $key );
                    if ( $cached ) {
                        return json_decode( $cached, true );
                    }
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Redis get failed: ' . $e->getMessage() );
                }
            }
            
            // Fallback to WordPress cache
            $cached = wp_cache_get( $key, 'orabooks' );
            if ( $cached !== false ) {
                return $cached;
            }
            
            // Fetch from database
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $features = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->orabooks_feature_assignments} 
                 WHERE level_id = %d",
                $level_id
            ) );
            
            // Cache the result
            $this->set( $key, $features, $this->default_ttl );
            
            return $features;
        }
        
        private function fetch_features_from_db( $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Get user's active subscription
            $subscription = $wpdb->get_row( $wpdb->prepare(
                "SELECT level_id FROM {$wpdb->orabooks_subscriptions} 
                 WHERE user_id = %d AND status = 'active' 
                 ORDER BY ends_at DESC LIMIT 1",
                $user_id
            ) );
            
            if ( ! $subscription ) {
                return [];
            }
            
            // Get features for this level
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->orabooks_feature_assignments} 
                 WHERE level_id = %d",
                $subscription->level_id
            ) );
        }
        
        public function set( $key, $value, $ttl = null ) {
            $ttl = $ttl ?: $this->default_ttl;
            
            // Try Redis first
            if ( $this->redis ) {
                try {
                    $this->redis->setex( $key, $ttl, json_encode( $value ) );
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Redis set failed: ' . $e->getMessage() );
                }
            }
            
            // Fallback to WordPress cache
            wp_cache_set( $key, $value, 'orabooks', $ttl );
        }
        
        public function delete( $key ) {
            // Try Redis first
            if ( $this->redis ) {
                try {
                    $this->redis->del( $key );
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Redis delete failed: ' . $e->getMessage() );
                }
            }
            
            // Fallback to WordPress cache
            wp_cache_delete( $key, 'orabooks' );
        }
        
        public function invalidate_user_cache( $user_id ) {
            $keys = [
                $this->cache_prefix . "user_features_{$user_id}",
                $this->cache_prefix . "user_subscription_{$user_id}"
            ];
            
            foreach ( $keys as $key ) {
                $this->delete( $key );
            }
        }
        
        public function invalidate_level_cache( $level_id ) {
            $key = $this->cache_prefix . "level_features_{$level_id}";
            $this->delete( $key );
        }
        
        public function clear_all_cache() {
            // Clear all OraBooks cache
            if ( $this->redis ) {
                try {
                    $keys = $this->redis->keys( $this->cache_prefix . '*' );
                    if ( ! empty( $keys ) ) {
                        $this->redis->del( $keys );
                    }
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Redis clear all failed: ' . $e->getMessage() );
                }
            }
            
            // Clear WordPress cache
            wp_cache_flush();
        }
        
        public function get_cache_stats() {
            $stats = [
                'redis_connected' => ! ! $this->redis,
                'wp_cache_enabled' => wp_using_ext_object_cache(),
                'cache_prefix' => $this->cache_prefix
            ];
            
            if ( $this->redis ) {
                try {
                    $info = $this->redis->info();
                    $stats['redis_memory'] = $info['used_memory_human'] ?? 'N/A';
                    $stats['redis_keys'] = $info['db0']['keys'] ?? 0;
                } catch ( Exception $e ) {
                    error_log( 'OraBooks Redis stats failed: ' . $e->getMessage() );
                }
            }
            
            return $stats;
        }
        
        // Singleton pattern
        private static $instance = null;
        
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }
}

// Helper functions for global access
function orabooks_cache() {
    return OraBooks_Cache_Manager::get_instance();
}

function orabooks_get_user_features( $user_id ) {
    return orabooks_cache()->get_user_features( $user_id );
}

function orabooks_get_user_subscription( $user_id ) {
    return orabooks_cache()->get_user_subscription( $user_id );
}

function orabooks_invalidate_user_cache( $user_id ) {
    orabooks_cache()->invalidate_user_cache( $user_id );
}

// Hook cache invalidation
add_action( 'orabooks_subscription_updated', function( $subscription_id, $user_id ) {
    orabooks_invalidate_user_cache( $user_id );
}, 10, 2 );

add_action( 'orabooks_subscription_created', function( $subscription_id, $user_id ) {
    orabooks_invalidate_user_cache( $user_id );
}, 10, 2 );

add_action( 'orabooks_subscription_cancelled', function( $subscription_id, $user_id ) {
    orabooks_invalidate_user_cache( $user_id );
}, 10, 2 );

add_action( 'orabooks_feature_assignment_updated', function( $level_id ) {
    orabooks_cache()->invalidate_level_cache( $level_id );
}, 10, 1 );
