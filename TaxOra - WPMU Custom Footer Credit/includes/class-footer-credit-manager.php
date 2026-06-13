<?php
/**
 * Footer Credit Manager Class
 * 
 * Handles hiding default theme footer credits and displaying custom ones
 * for Divi and Extra themes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TaxOra_Custom_Footer_Credit {

    private static $instance = null;
    private $network_footer_credit = 'taxora_network_footer_credit';
    private $subsite_footer_credit = 'taxora_subsite_footer_credit';
    private $hide_divi_option = 'taxora_footer_credit_hide_divi';
    private $hide_extra_option = 'taxora_footer_credit_hide_extra';
    private $use_network_credit = 'taxora_use_network_footer_credit';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Frontend hooks
        add_action( 'wp_head', array( $this, 'hide_theme_footer_credit_css' ) );
        add_filter( 'et_footer_credit', array( $this, 'hide_divi_footer_credit' ), 10, 1 );
        add_filter( 'et_extra_footer_credit', array( $this, 'hide_extra_footer_credit' ), 10, 1 );
        
        // Additional Extra theme hooks for comprehensive coverage
        add_action( 'et_extra_footer_bottom', array( $this, 'override_extra_footer_bottom' ), 1 );
        add_filter( 'extra_footer_credits', '__return_empty_string', 999 );

        // Custom footer credit display
        add_action( 'wp_footer', array( $this, 'display_custom_footer_credit' ), 1 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_footer_styles' ) );

        // Admin hooks
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'network_admin_menu', array( $this, 'add_network_admin_menu' ) );

        // Divi theme customizer hooks
        add_action( 'customize_register', array( $this, 'customize_footer_credit' ) );
        
        // Hook into Extra theme's footer template
        if ( $this->is_extra_theme_active() ) {
            add_action( 'wp_footer', array( $this, 'remove_extra_footer_widgets' ), 0 );
        }
    }

    /**
     * Check if Extra theme is active
     */
    private function is_extra_theme_active() {
        $theme = wp_get_theme();
        return ( 'Extra' === $theme->name || 'Extra' === $theme->parent_theme );
    }

    /**
     * Check if Divi theme is active
     */
    private function is_divi_theme_active() {
        $theme = wp_get_theme();
        return ( 'Divi' === $theme->name || 'Divi' === $theme->parent_theme );
    }

    /**
     * Hide Divi footer credit via filter
     */
    public function hide_divi_footer_credit( $credit ) {
        // Only hide on main site in multisite
        if ( is_multisite() && ! is_main_site() ) {
            return $credit;
        }

        $hide_divi = get_option( $this->hide_divi_option, 'yes' );
        
        if ( 'yes' === $hide_divi ) {
            return '';
        }
        
        return $credit;
    }

    /**
     * Enqueue footer styles
     */
    public function enqueue_footer_styles() {
        wp_enqueue_style( 
            'taxora-footer-credit', 
            TAXORA_FOOTER_CREDIT_URL . 'assets/css/footer-credit.css', 
            array(), 
            TAXORA_FOOTER_CREDIT_VERSION 
        );
    }

    /**
     * Hide Extra footer credit via filter
     */
    public function hide_extra_footer_credit( $credit ) {
        // Only hide on main site in multisite
        if ( is_multisite() && ! is_main_site() ) {
            return $credit;
        }

        $hide_extra = get_option( $this->hide_extra_option, 'yes' );
        
        if ( 'yes' === $hide_extra ) {
            return '';
        }
        
        return $credit;
    }

    /**
     * Override Extra theme footer bottom section
     * This prevents Extra theme from displaying footer credits
     */
    public function override_extra_footer_bottom() {
        // Only override on main site in multisite
        if ( is_multisite() && ! is_main_site() ) {
            return;
        }

        $hide_extra = get_option( $this->hide_extra_option, 'yes' );
        
        if ( 'yes' === $hide_extra ) {
            // Remove all actions that might output footer credits
            remove_all_actions( 'et_extra_footer_bottom', 10 );
            
            // Add JavaScript to hide any dynamically added footer credits
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    var footerCredits = document.querySelectorAll(".extra-footer-credit, #footer-bottom, #footer-info");
                    footerCredits.forEach(function(el) {
                        if (el) el.style.display = "none";
                    });
                });
            </script>';
        }
    }

    /**
     * Remove Extra theme footer credit widgets
     * Targets the specific footer widget that contains credits
     */
    public function remove_extra_footer_widgets() {
        // Only remove on main site in multisite
        if ( is_multisite() && ! is_main_site() ) {
            return;
        }

        $hide_extra = get_option( $this->hide_extra_option, 'yes' );
        
        if ( 'yes' === $hide_extra ) {
            // Unregister footer credit widgets
            if ( is_active_widget( false, false, 'text', true ) ) {
                add_filter( 'widget_text', array( $this, 'filter_extra_widget_text' ), 999 );
            }
        }
    }

    /**
     * Filter Extra theme widget text to remove credits
     */
    public function filter_extra_widget_text( $text ) {
        // Remove common Extra theme credit phrases
        $patterns = array(
            '/Designed by\s+<a[^>]*>Elegant Themes<\/a>/i',
            '/Powered by\s+<a[^>]*>WordPress<\/a>/i',
            '/<a[^>]*href=["\'][^"\']*elegantthemes\.com[^"\']*["\'][^>]*>.*?<\/a>/i',
        );
        
        foreach ( $patterns as $pattern ) {
            $text = preg_replace( $pattern, '', $text );
        }
        
        return $text;
    }

    /**
     * Add CSS to hide default footer credits
     * Fallback method in case filters don't work
     */
    public function hide_theme_footer_credit_css() {
        // Only hide theme footer credits on main site in multisite
        if ( is_multisite() && ! is_main_site() ) {
            return;
        }

        $hide_divi = get_option( $this->hide_divi_option, 'yes' );
        $hide_extra = get_option( $this->hide_extra_option, 'yes' );

        if ( 'yes' === $hide_divi || 'yes' === $hide_extra ) {
            echo '<style id="taxora-hide-footer-credit">' . "\n";
            
            // Hide Divi footer elements
            if ( 'yes' === $hide_divi ) {
                echo '.footer-credit { display: none !important; }' . "\n";
                echo '.et-footer-bottom .clearfix .footer-credits { display: none !important; }' . "\n";
                echo '.et_footer .et-footer-bottom .clearfix:last-child { display: none !important; }' . "\n";
            }
            
            // Hide Extra footer elements - Comprehensive coverage
            if ( 'yes' === $hide_extra ) {
                echo '.extra-footer-credit { display: none !important; }' . "\n";
                echo '.extra_bottom_footer .extra-footer-credits { display: none !important; }' . "\n";
                echo '#footer-bottom { display: none !important; }' . "\n";
                echo '#footer-info { display: none !important; }' . "\n";
                echo '.footer-credits { display: none !important; }' . "\n";
                echo '.bottom-footer-info { display: none !important; }' . "\n";
                echo '.extra_bottom_footer .footer-widget:last-child { display: none !important; }' . "\n";
                echo '.extra_bottom_footer .et-social-icons + p { display: none !important; }' . "\n";
                echo '#main-footer .footer-widget #text-2 { display: none !important; }' . "\n";
            }
            
            echo '</style>' . "\n";
        }
    }

    /**
     * Display custom footer credit
     */
    public function display_custom_footer_credit() {
        // Only show footer credit on main site in multisite
        if ( is_multisite() && ! is_main_site() ) {
            return;
        }

        $use_network = is_multisite() ? get_site_option( $this->use_network_credit, 'yes' ) : 'no';
        
        if ( is_multisite() && 'yes' === $use_network ) {
            $footer_text = get_site_option( $this->network_footer_credit );
        } else {
            $footer_text = get_option( $this->subsite_footer_credit );
        }

        if ( empty( $footer_text ) ) {
            $footer_text = 'Copyright © ' . date( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. All rights reserved.';
        }

        $footer_text = apply_filters( 'taxora_footer_credit_text', $footer_text );

        echo '<div id="taxora-custom-footer-credit" class="taxora-footer-credit">' . "\n";
        echo wp_kses_post( wpautop( $footer_text ) );
        echo '</div>' . "\n";
    }

    /**
     * Add submenu page to the admin menu
     */
    public function add_admin_menu() {
        if ( is_multisite() && ! is_network_admin() ) {
            return;
        }

        if ( ! is_multisite() ) {
            add_options_page(
                esc_html__( 'Custom Footer Credit', 'taxora-wpmu-custom-footer-credit' ),
                esc_html__( 'Custom Footer Credit', 'taxora-wpmu-custom-footer-credit' ),
                'manage_options',
                'taxora-footer-credit',
                array( $this, 'render_admin_page' )
            );
        }
    }

    /**
     * Add network admin menu for multisite
     */
    public function add_network_admin_menu() {
        if ( ! is_multisite() ) {
            return;
        }

        add_submenu_page(
            'settings.php',
            esc_html__( 'Custom Footer Credit', 'taxora-wpmu-custom-footer-credit' ),
            esc_html__( 'Custom Footer Credit', 'taxora-wpmu-custom-footer-credit' ),
            'manage_network_options',
            'taxora-network-footer-credit',
            array( $this, 'render_network_admin_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Single site settings
        register_setting( 'taxora_footer_credit_settings', $this->subsite_footer_credit );
        register_setting( 'taxora_footer_credit_settings', $this->hide_divi_option );
        register_setting( 'taxora_footer_credit_settings', $this->hide_extra_option );
    }

    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'taxora-wpmu-custom-footer-credit' ) );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Custom Footer Credit Settings', 'taxora-wpmu-custom-footer-credit' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'taxora_footer_credit_settings' ); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo esc_attr( $this->hide_divi_option ); ?>">
                                <?php esc_html_e( 'Hide Divi Theme Footer Credit', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                id="<?php echo esc_attr( $this->hide_divi_option ); ?>" 
                                name="<?php echo esc_attr( $this->hide_divi_option ); ?>" 
                                value="yes" 
                                <?php checked( get_option( $this->hide_divi_option ), 'yes' ); ?> />
                            <label for="<?php echo esc_attr( $this->hide_divi_option ); ?>">
                                <?php esc_html_e( 'Enable to hide the default Divi theme footer credit', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo esc_attr( $this->hide_extra_option ); ?>">
                                <?php esc_html_e( 'Hide Extra Theme Footer Credit', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                id="<?php echo esc_attr( $this->hide_extra_option ); ?>" 
                                name="<?php echo esc_attr( $this->hide_extra_option ); ?>" 
                                value="yes" 
                                <?php checked( get_option( $this->hide_extra_option ), 'yes' ); ?> />
                            <label for="<?php echo esc_attr( $this->hide_extra_option ); ?>">
                                <?php esc_html_e( 'Enable to hide the default Extra theme footer credit', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo esc_attr( $this->subsite_footer_credit ); ?>">
                                <?php esc_html_e( 'Custom Footer Credit Text', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </th>
                        <td>
                            <textarea 
                                id="<?php echo esc_attr( $this->subsite_footer_credit ); ?>"
                                name="<?php echo esc_attr( $this->subsite_footer_credit ); ?>"
                                rows="5"
                                cols="50"
                                class="large-text"><?php 
                                echo esc_textarea( get_option( $this->subsite_footer_credit ) ); 
                            ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'HTML tags are allowed. Use this to set custom footer credit text.', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </p>
                            <p class="description">
                                <?php esc_html_e( 'Available tags: b, i, em, strong, a, br, p', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render network admin settings page
     */
    public function render_network_admin_page() {
        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'taxora-wpmu-custom-footer-credit' ) );
        }

        // Handle form submission for network settings
        if ( isset( $_POST['taxora_network_footer_nonce'] ) && wp_verify_nonce( $_POST['taxora_network_footer_nonce'], 'taxora_network_footer_credit' ) ) {
            
            $use_network = isset( $_POST[ $this->use_network_credit ] ) ? 'yes' : 'no';
            update_site_option( $this->use_network_credit, $use_network );

            $network_credit = isset( $_POST[ $this->network_footer_credit ] ) 
                ? wp_kses_post( $_POST[ $this->network_footer_credit ] ) 
                : '';
            update_site_option( $this->network_footer_credit, $network_credit );

            $hide_divi = isset( $_POST[ $this->hide_divi_option ] ) ? 'yes' : 'no';
            update_site_option( $this->hide_divi_option, $hide_divi );

            $hide_extra = isset( $_POST[ $this->hide_extra_option ] ) ? 'yes' : 'no';
            update_site_option( $this->hide_extra_option, $hide_extra );

            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Settings saved successfully!', 'taxora-wpmu-custom-footer-credit' ); ?></p>
            </div>
            <?php
        }

        $use_network = get_site_option( $this->use_network_credit, 'yes' );
        $network_credit = get_site_option( $this->network_footer_credit );
        $hide_divi = get_site_option( $this->hide_divi_option, 'yes' );
        $hide_extra = get_site_option( $this->hide_extra_option, 'yes' );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Network Custom Footer Credit Settings', 'taxora-wpmu-custom-footer-credit' ); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'taxora_network_footer_credit', 'taxora_network_footer_nonce' ); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo esc_attr( $this->use_network_credit ); ?>">
                                <?php esc_html_e( 'Use Network-Wide Footer Credit', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                id="<?php echo esc_attr( $this->use_network_credit ); ?>" 
                                name="<?php echo esc_attr( $this->use_network_credit ); ?>" 
                                value="yes" 
                                <?php checked( $use_network, 'yes' ); ?> />
                            <label for="<?php echo esc_attr( $this->use_network_credit ); ?>">
                                <?php esc_html_e( 'Enable to use network-wide footer credit instead of per-site settings', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo esc_attr( $this->hide_divi_option ); ?>">
                                <?php esc_html_e( 'Hide Divi Theme Footer Credit', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                id="<?php echo esc_attr( $this->hide_divi_option ); ?>" 
                                name="<?php echo esc_attr( $this->hide_divi_option ); ?>" 
                                value="yes" 
                                <?php checked( $hide_divi, 'yes' ); ?> />
                            <label for="<?php echo esc_attr( $this->hide_divi_option ); ?>">
                                <?php esc_html_e( 'Enable to hide the default Divi theme footer credit across the network', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo esc_attr( $this->hide_extra_option ); ?>">
                                <?php esc_html_e( 'Hide Extra Theme Footer Credit', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                id="<?php echo esc_attr( $this->hide_extra_option ); ?>" 
                                name="<?php echo esc_attr( $this->hide_extra_option ); ?>" 
                                value="yes" 
                                <?php checked( $hide_extra, 'yes' ); ?> />
                            <label for="<?php echo esc_attr( $this->hide_extra_option ); ?>">
                                <?php esc_html_e( 'Enable to hide the default Extra theme footer credit across the network', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo esc_attr( $this->network_footer_credit ); ?>">
                                <?php esc_html_e( 'Network Footer Credit Text', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </label>
                        </th>
                        <td>
                            <textarea 
                                id="<?php echo esc_attr( $this->network_footer_credit ); ?>"
                                name="<?php echo esc_attr( $this->network_footer_credit ); ?>"
                                rows="5"
                                cols="50"
                                class="large-text"><?php 
                                echo esc_textarea( $network_credit ); 
                            ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'HTML tags are allowed. Use this to set custom footer credit text for all sites in the network.', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </p>
                            <p class="description">
                                <?php esc_html_e( 'Available tags: b, i, em, strong, a, br, p', 'taxora-wpmu-custom-footer-credit' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Customize footer credit in Divi Customizer
     */
    public function customize_footer_credit( $wp_customize ) {
        // This hook allows integration with Divi's customizer if needed
        // Currently using GET/POST options is sufficient
    }
}
