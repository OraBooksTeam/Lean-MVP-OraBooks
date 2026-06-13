<?php
if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Session {

    private static $instance = null;
    private $data = [];
    private $loaded = false;
    private $guest_token = '';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_guest_token();
        add_action('shutdown', [$this, 'save'], 100);
        add_action('wp_login', [$this, 'migrate_on_login'], 10, 2);
    }

    private function init_guest_token() {
        if (!is_user_logged_in()) {
            $cookie_name = 'orabooks_session';
            if (!empty($_COOKIE[$cookie_name])) {
                $this->guest_token = sanitize_key($_COOKIE[$cookie_name]);
            } else {
                $this->guest_token = wp_generate_password(32, false);
                setcookie($cookie_name, $this->guest_token, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            }
        }
    }

    public function get($key, $default = null) {
        $this->maybe_load();
        $ns = $this->get_namespace();
        return isset($this->data[$ns][$key]) ? $this->data[$ns][$key] : $default;
    }

    public function set($key, $value) {
        $this->maybe_load();
        $ns = $this->get_namespace();
        if (!isset($this->data[$ns])) {
            $this->data[$ns] = [];
        }
        $this->data[$ns][$key] = $value;
    }

    public function delete($key) {
        $this->maybe_load();
        $ns = $this->get_namespace();
        unset($this->data[$ns][$key]);
    }

    public function clear() {
        $this->maybe_load();
        $ns = $this->get_namespace();
        unset($this->data[$ns]);
    }

    public function clear_all() {
        $this->data = [];
        $this->loaded = true;
    }

    public function migrate_on_login($user_login, $user) {
        if ($this->guest_token) {
            $old_ns = 'guest_' . md5(SECURE_AUTH_KEY . $this->guest_token);
            $new_ns = 'user_' . $user->ID;
            $this->maybe_load();
            if (isset($this->data[$old_ns]) && !empty($this->data[$old_ns])) {
                $this->data[$new_ns] = $this->data[$old_ns];
                unset($this->data[$old_ns]);
            }
            $this->guest_token = '';
            setcookie('orabooks_session', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
    }

    private function maybe_load() {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        $key = $this->get_transient_key();
        $stored = get_transient($key);
        $this->data = is_array($stored) ? $stored : [];
    }

    public function save() {
        if (!$this->loaded) {
            return;
        }
        $key = $this->get_transient_key();
        $ns = $this->get_namespace();
        $has_data = isset($this->data[$ns]) && !empty($this->data[$ns]);
        if ($has_data) {
            set_transient($key, $this->data, DAY_IN_SECONDS);
        }
    }

    private function get_transient_key() {
        return 'orabooks_session_store';
    }

    private function get_namespace() {
        $user_id = get_current_user_id();
        if ($user_id) {
            return 'user_' . $user_id;
        }
        return 'guest_' . md5(SECURE_AUTH_KEY . $this->guest_token);
    }

    public static function init() {
        self::get_instance();
    }
}
