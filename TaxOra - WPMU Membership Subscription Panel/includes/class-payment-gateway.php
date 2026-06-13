<?php
if ( ! defined( 'ABSPATH' ) ) exit;

abstract class OraBooks_Payment_Gateway {
    protected $id;
    protected $title;
    protected $description;
    protected $icon;

    public function __construct() {
        if (empty($this->id)) {
            $this->id = 'orabooks_gateway';
        }
        if (empty($this->title)) {
            $this->title = 'Default Gateway';
        }
    }

    public function get_title() {
        return $this->title;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_description() {
        return isset($this->description) ? $this->description : '';
    }

    /**
     * Whether the gateway is available for use.
     * Gateways can override this to perform credential checks, mode checks, etc.
     * Default: available.
     *
     * @return bool
     */
    public function is_available() {
        return true;
    }

    /**
     * Output admin options for this gateway. Gateways should override when they
     * need to render settings fields in the Payments admin tab.
     */
    public function admin_options() {
        // Default: nothing to render
    }

    /**
     * Return an array of settings field definitions for saving. Gateways should
     * return an array of arrays with at least an 'id' key.
     *
     * @return array
     */
    public function get_settings_fields() {
        return array();
    }

    /**
     * Handle frontend callback/return for this gateway. Gateways can override.
     */
    public function handle_callback() {
        // Default: no callback handling
    }

    abstract public function process_payment( $order_id, $amount, $level_id );
}