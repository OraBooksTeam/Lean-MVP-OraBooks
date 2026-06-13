<?php
/**
 * OraBooks Localization Engine
 * Implements localization features as per ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md
 * 
 * Core Principles:
 * - Localization is legal alignment, not just translation
 * - Currency localization
 * - Tax/VAT localization
 * - Date/time localization
 * - Language localization
 * - Religious/cultural localization
 * - Jurisdiction-aware features
 * 
 * @package OraBooks_Membership
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Localization {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Supported currencies
     */
    private static $currencies = array(
        'USD' => array(
            'name' => 'US Dollar',
            'symbol' => '$',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ),
        'EUR' => array(
            'name' => 'Euro',
            'symbol' => '€',
            'symbol_position' => 'after',
            'decimal_places' => 2,
            'thousands_separator' => '.',
            'decimal_separator' => ',',
        ),
        'GBP' => array(
            'name' => 'British Pound',
            'symbol' => '£',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ),
        'BDT' => array(
            'name' => 'Bangladeshi Taka',
            'symbol' => '৳',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ),
        'INR' => array(
            'name' => 'Indian Rupee',
            'symbol' => '₹',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ),
        'JPY' => array(
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'symbol_position' => 'before',
            'decimal_places' => 0,
            'thousands_separator' => ',',
            'decimal_separator' => '.',
        ),
    );
    
    /**
     * Supported date formats by locale
     */
    private static $date_formats = array(
        'en_US' => array(
            'date' => 'm/d/Y',
            'time' => 'g:i A',
            'datetime' => 'm/d/Y g:i A',
            'timezone' => 'America/New_York',
        ),
        'en_GB' => array(
            'date' => 'd/m/Y',
            'time' => 'H:i',
            'datetime' => 'd/m/Y H:i',
            'timezone' => 'Europe/London',
        ),
        'bn_BD' => array(
            'date' => 'd/m/Y',
            'time' => 'H:i',
            'datetime' => 'd/m/Y H:i',
            'timezone' => 'Asia/Dhaka',
        ),
        'ja_JP' => array(
            'date' => 'Y/m/d',
            'time' => 'H:i',
            'datetime' => 'Y/m/d H:i',
            'timezone' => 'Asia/Tokyo',
        ),
    );
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Set locale based on user preference
        add_action('init', array($this, 'set_user_locale'));
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('orabooks', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Set user locale based on preference
     */
    public function set_user_locale() {
        if (is_user_logged_in()) {
            $user_locale = get_user_meta(get_current_user_id(), 'orabooks_locale', true);
            if ($user_locale) {
                switch_to_locale($user_locale);
            }
        }
    }
    
    /**
     * Format currency amount
     * 
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @return string Formatted amount
     */
    public function format_currency($amount, $currency = 'USD') {
        $currency = strtoupper($currency);
        
        if (!isset(self::$currencies[$currency])) {
            $currency = 'USD';
        }
        
        $config = self::$currencies[$currency];
        
        // Format number
        $formatted = number_format(
            $amount,
            $config['decimal_places'],
            $config['decimal_separator'],
            $config['thousands_separator']
        );
        
        // Add symbol
        if ($config['symbol_position'] === 'before') {
            return $config['symbol'] . $formatted;
        } else {
            return $formatted . ' ' . $config['symbol'];
        }
    }
    
    /**
     * Get currency symbol
     * 
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    public function get_currency_symbol($currency = 'USD') {
        $currency = strtoupper($currency);
        
        if (isset(self::$currencies[$currency])) {
            return self::$currencies[$currency]['symbol'];
        }
        
        return '$';
    }
    
    /**
     * Get supported currencies
     * 
     * @return array Supported currencies
     */
    public function get_supported_currencies() {
        return self::$currencies;
    }
    
    /**
     * Format date according to locale
     * 
     * @param string $date Date string or timestamp
     * @param string $format Format type (date, time, datetime)
     * @param string $locale Locale code
     * @return string Formatted date
     */
    public function format_date($date, $format = 'date', $locale = null) {
        if ($locale === null) {
            $locale = $this->get_current_locale();
        }
        
        if (!isset(self::$date_formats[$locale])) {
            $locale = 'en_US';
        }
        
        $config = self::$date_formats[$locale];
        $format_string = isset($config[$format]) ? $config[$format] : $config['date'];
        
        if (is_numeric($date)) {
            $timestamp = $date;
        } else {
            $timestamp = strtotime($date);
        }
        
        return date($format_string, $timestamp);
    }
    
    /**
     * Get current locale
     * 
     * @return string Locale code
     */
    public function get_current_locale() {
        if (is_user_logged_in()) {
            $user_locale = get_user_meta(get_current_user_id(), 'orabooks_locale', true);
            if ($user_locale) {
                return $user_locale;
            }
        }
        
        return get_locale();
    }
    
    /**
     * Set user locale
     * 
     * @param int $user_id User ID
     * @param string $locale Locale code
     * @return bool Success status
     */
    public function set_user_locale_preference($user_id, $locale) {
        if (!isset(self::$date_formats[$locale])) {
            return false;
        }
        
        return update_user_meta($user_id, 'orabooks_locale', $locale);
    }
    
    /**
     * Format number according to locale
     * 
     * @param float $number Number to format
     * @param int $decimals Decimal places
     * @param string $locale Locale code
     * @return string Formatted number
     */
    public function format_number($number, $decimals = 2, $locale = null) {
        if ($locale === null) {
            $locale = $this->get_current_locale();
        }
        
        $currency = $this->get_currency_for_locale($locale);
        
        if ($currency && isset(self::$currencies[$currency])) {
            $config = self::$currencies[$currency];
            return number_format(
                $number,
                $decimals,
                $config['decimal_separator'],
                $config['thousands_separator']
            );
        }
        
        // Default format
        return number_format($number, $decimals, '.', ',');
    }
    
    /**
     * Get currency for locale
     * 
     * @param string $locale Locale code
     * @return string Currency code
     */
    private function get_currency_for_locale($locale) {
        $map = array(
            'en_US' => 'USD',
            'en_GB' => 'GBP',
            'bn_BD' => 'BDT',
            'ja_JP' => 'JPY',
            'fr_FR' => 'EUR',
            'de_DE' => 'EUR',
        );
        
        return isset($map[$locale]) ? $map[$locale] : null;
    }
    
    /**
     * Get timezone for locale
     * 
     * @param string $locale Locale code
     * @return string Timezone
     */
    public function get_timezone_for_locale($locale = null) {
        if ($locale === null) {
            $locale = $this->get_current_locale();
        }
        
        if (isset(self::$date_formats[$locale])) {
            return self::$date_formats[$locale]['timezone'];
        }
        
        return 'UTC';
    }
    
    /**
     * Translate string with context
     * 
     * @param string $string String to translate
     * @param string $context Context for translation
     * @return string Translated string
     */
    public function translate($string, $context = '') {
        return $context ? _x($string, $context, 'orabooks') : __($string, 'orabooks');
    }
    
    /**
     * Get localized number suffix (st, nd, rd, th)
     * 
     * @param int $number Number
     * @return string Suffix
     */
    public function get_number_suffix($number) {
        $locale = $this->get_current_locale();
        
        // English suffixes
        if (strpos($locale, 'en_') === 0) {
            $last_digit = $number % 10;
            $last_two = $number % 100;
            
            if ($last_two >= 11 && $last_two <= 13) {
                return 'th';
            }
            
            switch ($last_digit) {
                case 1: return 'st';
                case 2: return 'nd';
                case 3: return 'rd';
                default: return 'th';
            }
        }
        
        // For other locales, return empty string
        return '';
    }
    
    /**
     * Format percentage
     * 
     * @param float $value Value to format
     * @param int $decimals Decimal places
     * @param string $locale Locale code
     * @return string Formatted percentage
     */
    public function format_percentage($value, $decimals = 2, $locale = null) {
        $formatted = $this->format_number($value, $decimals, $locale);
        return $formatted . '%';
    }
    
    /**
     * Format file size
     * 
     * @param int $bytes Size in bytes
     * @param int $decimals Decimal places
     * @return string Formatted size
     */
    public function format_file_size($bytes, $decimals = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $decimals) . ' ' . $units[$pow];
    }
    
    /**
     * Get jurisdiction-specific settings
     * 
     * @param string $country Country code
     * @return array Jurisdiction settings
     */
    public function get_jurisdiction_settings($country) {
        $settings = array(
            'US' => array(
                'tax_rate' => 0.00,
                'tax_name' => 'Sales Tax',
                'date_format' => 'm/d/Y',
                'currency' => 'USD',
            ),
            'GB' => array(
                'tax_rate' => 0.20,
                'tax_name' => 'VAT',
                'date_format' => 'd/m/Y',
                'currency' => 'GBP',
            ),
            'BD' => array(
                'tax_rate' => 0.15,
                'tax_name' => 'VAT',
                'date_format' => 'd/m/Y',
                'currency' => 'BDT',
            ),
        );
        
        $country = strtoupper($country);
        return isset($settings[$country]) ? $settings[$country] : $settings['US'];
    }
    
    /**
     * Get religious calendar events for locale
     * 
     * @param string $locale Locale code
     * @param int $year Year
     * @return array Calendar events
     */
    public function get_religious_calendar_events($locale = null, $year = null) {
        if ($locale === null) {
            $locale = $this->get_current_locale();
        }
        
        if ($year === null) {
            $year = date('Y');
        }
        
        $events = array();
        
        // Bengali Islamic calendar events
        if (strpos($locale, 'bn_') === 0 || strpos($locale, 'BD') !== false) {
            // Eid al-Fitr (approximate calculation)
            $events[] = array(
                'name' => 'Eid al-Fitr',
                'type' => 'religious',
                'date' => $this->calculate_eid_al_fitr($year),
            );
            
            // Eid al-Adha (approximate calculation)
            $events[] = array(
                'name' => 'Eid al-Adha',
                'type' => 'religious',
                'date' => $this->calculate_eid_al_adha($year),
            );
        }
        
        return $events;
    }
    
    /**
     * Calculate Eid al-Fitr date (simplified)
     */
    private function calculate_eid_al_fitr($year) {
        // Simplified calculation - in production, use proper Islamic calendar library
        return date('Y-m-d', strtotime('+1 day', strtotime($year . '-04-10')));
    }
    
    /**
     * Calculate Eid al-Adha date (simplified)
     */
    private function calculate_eid_al_adha($year) {
        // Simplified calculation - in production, use proper Islamic calendar library
        return date('Y-m-d', strtotime('+1 day', strtotime($year . '-06-17')));
    }
    
    /**
     * Get RTL status for locale
     * 
     * @param string $locale Locale code
     * @return bool Is RTL
     */
    public function is_rtl($locale = null) {
        if ($locale === null) {
            $locale = $this->get_current_locale();
        }
        
        $rtl_locales = array('ar', 'he', 'fa', 'ur');
        
        foreach ($rtl_locales as $rtl_locale) {
            if (strpos($locale, $rtl_locale) === 0) {
                return true;
            }
        }
        
        return false;
    }
}

// Initialize the localization system
OraBooks_Localization::get_instance();
