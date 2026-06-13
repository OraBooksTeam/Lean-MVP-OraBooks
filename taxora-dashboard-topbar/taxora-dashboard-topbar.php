<?php
/*
Plugin Name: TaxOra Dashboard Topbar
Description: Premium SaaS dashboard topbar shortcode + Global Admin Topbar
Version: 2.2.1
Author: TaxOra
*/

// Prevent direct access
defined( 'ABSPATH' ) || exit;

define( 'TAXORA_TOPBAR_VERSION', '2.2.1' );
define( 'TAXORA_TOPBAR_URL', plugin_dir_url( __FILE__ ) );
define( 'TAXORA_TOPBAR_PATH', plugin_dir_path( __FILE__ ) );

// ============================================================
// HELPER: Build quick-access addon feature buttons for topbar
// ============================================================
function taxora_topbar_hex2rgb( $hex ) {
	$hex = str_replace( '#', '', $hex );
	if ( strlen( $hex ) == 3 ) {
		$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
		$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
		$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
	} else {
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
	}
	return "$r, $g, $b";
}

function taxora_topbar_get_addon_buttons() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	// 1. Define base list of user-facing dashboard features with premium styling
	$all_features = array(
		'accounting' => array(
			'label' => 'Accounting',
			'desc'  => 'Manage accounts, invoicing & transactions',
			'url'   => home_url( '/accounting' ),
			'color' => '#6366f1',
			'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M7 8h10M7 12h6"/></svg>',
		),
		'inventory' => array(
			'label' => 'Inventory',
			'desc'  => 'Manage stock, products & warehouses',
			'url'   => home_url( '/inventory' ),
			'color' => '#10b981',
			'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
		),
		'analytics' => array(
			'label' => 'Analytics',
			'desc'  => 'Track performance & business metrics',
			'url'   => home_url( '/analytics' ),
			'color' => '#3b82f6',
			'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
		),
		'reporting' => array(
			'label' => 'Reporting',
			'desc'  => 'Generate financial & module reports',
			'url'   => home_url( '/reporting' ),
			'color' => '#ec4899',
			'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
		),
	);

	// 2. Query active feature list assigned to logged-in user via Membership Panel database tables
	$features = array();
	$user_id = get_current_user_id();

	global $wpdb;
	$user_level = get_user_meta( $user_id, 'orabooks_level', true );
	if ( $user_level && isset( $wpdb->orabooks_feature_assignments ) ) {
		$assigned_features = $wpdb->get_results( $wpdb->prepare(
			"SELECT feature_key FROM {$wpdb->orabooks_feature_assignments} WHERE level_id = %d",
			$user_level
		) );
		foreach ( $assigned_features as $af ) {
			$slug = strtolower( $af->feature_key );
			$features[] = $slug;
			
			// Auto-register future dynamic features found in active database assignments!
			if ( ! isset( $all_features[ $slug ] ) ) {
				$all_features[ $slug ] = array(
					'label' => ucfirst( $slug ),
					'desc'  => 'Access ' . ucfirst( $slug ) . ' module features',
					'url'   => home_url( '/' . $slug ),
					'color' => '#818cf8',
					'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
				);
			}
		}
	}

	// 3. Fallback: Query legacy feature-access function
	if ( empty( $features ) && function_exists( 'orabooks_user_has_feature_access' ) ) {
		$taxora_features = array( 'accounting', 'inventory', 'analytics', 'customization', 'reporting' );
		foreach ( $taxora_features as $feat ) {
			if ( orabooks_user_has_feature_access( $user_id, $feat ) ) {
				$features[] = $feat;
			}
		}
	}

	// 4. Double fallback: Check active backend plugin states directly
	if ( empty( $features ) ) {
		if ( function_exists( 'run_obn_frontend_accounting' ) || defined( 'OBN_ACCOUNTING_VERSION' ) ) {
			$features[] = 'accounting';
		}
		if ( function_exists( 'frontend_inventory_init' ) || defined( 'FRONTEND_INVENTORY_VERSION' ) ) {
			$features[] = 'inventory';
		}
	}

	// Unique list of keys
	$features = array_unique( $features );

	// Filter and build addons to render
	$addons_to_render = array();
	foreach ( $features as $feat_slug ) {
		if ( isset( $all_features[ $feat_slug ] ) ) {
			$addons_to_render[ $feat_slug ] = $all_features[ $feat_slug ];
		}
	}

	if ( empty( $addons_to_render ) ) {
		return '';
	}

	// Detect currently active module in request
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$active_addon_label = 'Quick Access';
	
	foreach ( $addons_to_render as $id => $addon ) {
		if ( strpos( $request_uri, '/' . $id ) !== false ) {
			$active_addon_label = esc_html( $addon['label'] );
			break;
		}
	}

	// Render premium launcher dropdown markup
	$html = '<div class="taxora-addon-dropdown-container" id="taxora-addon-dropdown-container">
		<button class="taxora-addon-trigger" id="taxora-addon-trigger" aria-haspopup="true" aria-expanded="false" aria-label="Toggle Quick Access menu">
			<span class="taxora-addon-trigger-icon">⚡</span>
			<span class="taxora-addon-trigger-label">' . $active_addon_label . '</span>
			<svg class="taxora-addon-trigger-chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<polyline points="6 9 12 15 18 9"></polyline>
			</svg>
		</button>
		
		<div class="taxora-addon-dropdown" id="taxora-addon-dropdown" role="menu" aria-label="Quick Access features">
			<div class="taxora-addon-dropdown-header">
				<span>Modules & Addons</span>
			</div>
			<div class="taxora-addon-dropdown-list">';

	foreach ( $addons_to_render as $id => $addon ) {
		$is_current = ( strpos( $request_uri, '/' . $id ) !== false );
		$active_cls = $is_current ? ' taxora-addon-item--active' : '';
		
		$html .= sprintf(
			'<a href="%s" class="taxora-addon-item%s" role="menuitem" aria-current="%s" style="--addon-color: %s">
				<div class="taxora-addon-item-icon" style="background: rgba(%s, 0.1);">
					%s
				</div>
				<div class="taxora-addon-item-content">
					<div class="taxora-addon-item-title">%s</div>
					<div class="taxora-addon-item-desc">%s</div>
				</div>
				%s
			</a>',
			esc_url( $addon['url'] ),
			$active_cls,
			$is_current ? 'page' : 'false',
			esc_attr( $addon['color'] ),
			taxora_topbar_hex2rgb( $addon['color'] ),
			$addon['icon'],
			esc_html( $addon['label'] ),
			esc_html( $addon['desc'] ),
			$is_current ? '<span class="taxora-addon-item-indicator"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></span>' : ''
		);
	}

	$html .= '</div>
		</div>
	</div>';

	return $html;
}

// ============================================================
// HELPER: Return pure topbar HTML (no inline CSS/JS wrapping)
// Used by both shortcode callback and admin renderer
// ============================================================
function taxora_topbar_get_html() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$current_user   = wp_get_current_user();
	$logout_url     = wp_logout_url();
	$profile_url    = admin_url( 'profile.php' );
	$home_url       = home_url( '/dashboard' );

	// Read language: user meta first, then cookie fallback
	$current_lang = get_user_meta( get_current_user_id(), 'taxora_language', true );
	if ( ! $current_lang && isset( $_COOKIE['taxora_language'] ) ) {
		$current_lang = sanitize_text_field( wp_unslash( $_COOKIE['taxora_language'] ) );
	}
	$current_lang = in_array( $current_lang, array( 'en', 'bn', 'ar' ), true ) ? $current_lang : 'en';

	$lang_names     = array(
		'en' => 'English',
		'bn' => 'Bangla',
		'ar' => 'Arabic',
	);
	$current_lang_name = $lang_names[ $current_lang ] ?? 'English';

	$settings_label = esc_html__( 'Settings', 'taxora-topbar' );
	$account_label  = esc_html__( 'My Account', 'taxora-topbar' );
	$upgrade_label  = esc_html__( 'Upgrade Plan', 'taxora-topbar' );
	$logout_label   = esc_html__( 'Logout', 'taxora-topbar' );

	return '<div id="taxora-topbar-root">
		<div class="taxora-topbar" id="taxora-topbar">
			<div class="taxora-topbar-left">
				<button class="taxora-topbar-btn" onclick="window.TaxOraTopbar.back()" aria-label="Back"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
				<a href="' . esc_url( $home_url ) . '" class="taxora-topbar-btn taxora-topbar-home" aria-label="Home"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3L2 12h3v8h6v-6h2v6h6v-8h3L12 3z"></path></svg></a>
				<button class="taxora-topbar-btn" onclick="window.TaxOraTopbar.forward()" aria-label="Forward"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
			</div>
			' . taxora_topbar_get_addon_buttons() . '
			<div class="taxora-topbar-center">
				<span class="taxora-clock" id="taxora-clock">00:00:00</span>
				<button class="taxora-calendar-btn" onclick="window.TaxOraTopbar.toggleCalendar()" aria-label="Calendar"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></button>
				<span class="taxora-date" id="taxora-date"></span>
			</div>
			<div class="taxora-topbar-right">
				<div class="taxora-dropdown">
					<button class="taxora-dropdown-btn" id="taxora-lang-btn">' . esc_html( $current_lang_name ) . ' &#9662;</button>
					<div class="taxora-dropdown-menu" id="taxora-language-menu">
						<a href="javascript:void(0)" data-lang="en">English</a>
						<a href="javascript:void(0)" data-lang="bn">Bangla</a>
						<a href="javascript:void(0)" data-lang="ar">Arabic</a>
					</div>
				</div>
				<button class="taxora-theme-toggle" id="taxora-theme-toggle" aria-label="Switch to dark mode" aria-pressed="false" tabindex="0">
					<span class="taxora-theme-toggle-icon sun-icon active">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="12" cy="12" r="5"></circle>
							<path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
						</svg>
					</span>
					<span class="taxora-theme-toggle-icon moon-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
						</svg>
					</span>
				</button>
				<div class="taxora-dropdown">
					<button class="taxora-dropdown-btn" id="taxora-settings-btn">' . $settings_label . ' &#9662;</button>
					<div class="taxora-dropdown-menu" id="taxora-settings-menu">
						<a href="' . esc_url( $profile_url ) . '">' . $account_label . '</a>
						<a href="#" onclick="window.TaxOraTopbar.upgradePlan()">' . $upgrade_label . '</a>
						<a href="' . esc_url( $logout_url ) . '">' . $logout_label . '</a>
					</div>
				</div>
			</div>
		</div>
		<div class="taxora-calendar-dropdown" id="taxora-calendar">
			<div class="taxora-calendar-header">
				<button onclick="window.TaxOraTopbar.changeMonth(-1)">&#8249;</button>
				<span id="taxora-calendar-month"></span>
				<button onclick="window.TaxOraTopbar.changeMonth(1)">&#8250;</button>
			</div>
			<div class="taxora-calendar-grid" id="taxora-calendar-grid"></div>
		</div>
	</div>';
}



function taxora_topbar_register_shortcode() {
	add_shortcode( 'taxora_topbar', 'taxora_topbar_shortcode_callback' );
}
add_action( 'init', 'taxora_topbar_register_shortcode' );

// 1. Enqueue scripts the WordPress way
function taxora_topbar_enqueue_scripts() {
	if ( ! is_user_logged_in() || wp_doing_ajax() ) {
		return;
	}

	if ( ! taxora_topbar_is_dashboard_request() ) {
		return;
	}

	// Register topbar.js
	wp_enqueue_script( 'taxora-topbar-js', TAXORA_TOPBAR_URL . 'assets/js/topbar.js', array( 'jquery' ), TAXORA_TOPBAR_VERSION, true );

	// Pass variables to topbar.js
	wp_localize_script( 'taxora-topbar-js', 'taxoraTopbarVars', array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'langNonce'    => wp_create_nonce( 'taxora_lang_nonce' ),
		'isAdmin'      => false,
		'topbarHeight' => 56,
	) );

	// Register language.js
	if ( file_exists( TAXORA_TOPBAR_PATH . 'assets/js/taxora-language.js' ) ) {
		wp_enqueue_script( 'taxora-language-js', TAXORA_TOPBAR_URL . 'assets/js/taxora-language.js', array( 'taxora-topbar-js' ), TAXORA_TOPBAR_VERSION, true );
	}

	// Register theme-mode.css
	if ( file_exists( TAXORA_TOPBAR_PATH . 'assets/css/theme-mode.css' ) ) {
		wp_enqueue_style( 'taxora-theme-mode-css', TAXORA_TOPBAR_URL . 'assets/css/theme-mode.css', array(), TAXORA_TOPBAR_VERSION );
	}

	// Register theme-toggle.js
	if ( file_exists( TAXORA_TOPBAR_PATH . 'assets/js/theme-toggle.js' ) ) {
		wp_enqueue_script( 'taxora-theme-toggle-js', TAXORA_TOPBAR_URL . 'assets/js/theme-toggle.js', array(), TAXORA_TOPBAR_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'taxora_topbar_enqueue_scripts' );

function taxora_topbar_get_early_theme_script() {
	return '<script id="taxora-early-theme-init">
	(function() {
		try {
			var theme = localStorage.getItem("taxora_theme_mode") || "light";
			var html = document.documentElement;
			html.classList.remove("taxora-light-mode", "taxora-dark-mode", "light", "dark");
			html.classList.add("taxora-" + theme + "-mode", theme);
			
			var body = document.body;
			if (body) {
				body.classList.remove("taxora-light-mode", "taxora-dark-mode", "light", "dark");
				body.classList.add("taxora-" + theme + "-mode", theme);
			} else {
				var observer = new MutationObserver(function(mutations, obs) {
					var b = document.body;
					if (b) {
						b.classList.remove("taxora-light-mode", "taxora-dark-mode", "light", "dark");
						b.classList.add("taxora-" + theme + "-mode", theme);
						obs.disconnect();
					}
				});
				observer.observe(html, { childList: true, subtree: true });
			}
		} catch (e) {}
	})();
	</script>';
}

function taxora_topbar_inject_head_theme_script() {
	if ( ! is_user_logged_in() || wp_doing_ajax() ) {
		return;
	}
	if ( ! taxora_topbar_is_dashboard_request() ) {
		return;
	}
	echo taxora_topbar_get_early_theme_script();
}
add_action( 'wp_head', 'taxora_topbar_inject_head_theme_script', 0 );

function taxora_topbar_shortcode_callback() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$early = taxora_topbar_get_early_theme_script();
	$html  = taxora_topbar_get_html();
	$css   = '<style>' . taxora_topbar_get_inline_css() . taxora_topbar_get_theme_mode_css();

	// Add frontend dashboard fixed-position override (catches WPFD + inventory templates)
	if ( taxora_topbar_is_dashboard_request() ) {
		$css .= taxora_topbar_get_frontend_override_css();
	}

	$css .= '</style>';

	// Inline JavaScript since some dashboard templates (WPFD) call exit; in template_redirect,
	// which prevents wp_enqueue_scripts from properly outputting enqueued script tags.
	$js = taxora_topbar_get_inline_js();

	return $early . $css . $js . $html;
}

/**
 * Return the topbar JavaScript as an inline <script> tag.
 * This ensures the topbar JS works even when standalone dashboard templates
 * bypass the normal WordPress enqueue system.
 */
function taxora_topbar_get_inline_js() {
	$js_file = TAXORA_TOPBAR_PATH . 'assets/js/topbar.js';
	if ( ! file_exists( $js_file ) ) {
		return '';
	}

	// Read the original JS file
	$js_content = file_get_contents( $js_file );

	// Build the wp_localize_script data inline as a <script> tag before the main JS
	$vars = array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'langNonce'    => wp_create_nonce( 'taxora_lang_nonce' ),
		'isAdmin'      => false,
		'topbarHeight' => 56,
	);

	$inline_vars = 'var taxoraTopbarVars = ' . wp_json_encode( $vars ) . ';';

	// Load taxora-language.js too if it exists
	$lang_js_content = '';
	$lang_js_file = TAXORA_TOPBAR_PATH . 'assets/js/taxora-language.js';
	if ( file_exists( $lang_js_file ) ) {
		$lang_js_content = file_get_contents( $lang_js_file );
	}

	// Load theme-toggle.js too if it exists
	$theme_js_content = '';
	$theme_js_file = TAXORA_TOPBAR_PATH . 'assets/js/theme-toggle.js';
	if ( file_exists( $theme_js_file ) ) {
		$theme_js_content = file_get_contents( $theme_js_file );
	}

	return '<script>' . $inline_vars . $js_content . $lang_js_content . $theme_js_content . '</script>';
}

function taxora_topbar_get_frontend_override_css() {
	return '
#taxora-topbar-root {
	position: fixed !important;
	top: 0 !important;
	left: 0 !important;
	right: 0 !important;
	z-index: 999999 !important;
	margin: 0 !important;
	padding: 0 !important;
	width: 100% !important;
	background: transparent !important;
	display: block !important;
	flex: 0 0 auto !important;
}

#taxora-topbar-root .taxora-topbar {
	margin: 0 !important;
	border-radius: 0 !important;
	width: 100% !important;
}

/* ===== WP Frontend Dashboard (wp-frontend-dashboard) ===== */
/* WPFD: <body class="h-full flex flex-col md:flex-row overflow-hidden">
   <aside> sidebar + <div.flex-1> sit side by side in a flex row.
   Uses `:has(header.glass)` to scope this rule to WPFD pages ONLY.
   The Inventory plugin already has its own `has-taxora-topbar` CSS handling
   (margin-top: 56px on #inventory-main-layout), so applying body padding
   there would cause a double offset (blank page). */

body.taxora-topbar-active.taxora-topbar-frontend:has(header.glass) {
	padding-top: 56px !important;
	box-sizing: border-box !important;
}

/* Sidebar offset handled by body padding on WPFD - no extra margin needed on header */
body.taxora-topbar-active.taxora-topbar-frontend header.glass {
	margin-top: 0 !important;
	top: 0 !important;
}

/* ===== OraBooks Accounting & Inventory ===== */
body.taxora-topbar-active.taxora-topbar-frontend .obn-dashboard-header {
	top: 56px !important;
}

body.taxora-topbar-active.taxora-topbar-frontend .obn-dashboard-sidebar {
	top: 56px !important;
	height: calc(100vh - 56px) !important;
}

/* Do NOT override #inventory-main-layout margin-top - the Inventory template
   already handles its own offset via body.has-taxora-topbar #inventory-main-layout
   with margin-top: 56px in its own stylesheet. Overriding it here with 0 would
   break the inventory layout. */
body.taxora-topbar-active.taxora-topbar-frontend .obn-dashboard-wrapper,
body.taxora-topbar-active.taxora-topbar-frontend #wpfd-dashboard-main {
	margin-top: 0 !important;
}

/* Common Theme Headers that might be fixed */
body.taxora-topbar-active.taxora-topbar-frontend #masthead,
body.taxora-topbar-active.taxora-topbar-frontend .site-header,
body.taxora-topbar-active.taxora-topbar-frontend #header,
body.taxora-topbar-active.taxora-topbar-frontend #top-header,
body.taxora-topbar-active.taxora-topbar-frontend #main-header,
body.taxora-topbar-active.taxora-topbar-frontend .main-header,
body.taxora-topbar-active.taxora-topbar-frontend .header-main,
body.taxora-topbar-active.taxora-topbar-frontend .navbar,
body.taxora-topbar-active.taxora-topbar-frontend #navbar,
body.taxora-topbar-active.taxora-topbar-frontend .nav-container {
	top: 56px !important;
}

';
}

// Helper function no longer used as we use wp_enqueue_scripts
// function taxora_topbar_get_inline_js() { ... }


// ============================================================
// FRONTEND: Dashboard route detection + buffer injection (kept)
// ============================================================
function taxora_topbar_is_dashboard_request() {
	if ( is_admin() || wp_doing_ajax() ) {
		return false;
	}

	$route = get_query_var( 'wpfd_route' );
	if ( $route === 'dashboard' ) {
		return true;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = trim( (string) parse_url( $request_uri, PHP_URL_PATH ), '/' );
	$home_path   = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

	if ( $home_path && ( $path === $home_path || strpos( $path, $home_path . '/' ) === 0 ) ) {
		$path = trim( substr( $path, strlen( $home_path ) ), '/' );
	}

	$dashboard_slugs = array( 'dashboard', 'accounting', 'inventory' );
	foreach ( $dashboard_slugs as $slug ) {
		if ( $path === $slug || strpos( $path, $slug . '/' ) === 0 ) {
			return true;
		}
	}

	return false;
}

/**
 * Inject Topbar directly into the body start.
 * Using wp_body_open is the standard WordPress way to inject content at the start of <body>.
 */
function taxora_topbar_inject_directly() {
	if ( ! is_user_logged_in() || ! taxora_topbar_is_dashboard_request() ) {
		return;
	}

	// Prevent double injection if theme calls wp_body_open multiple times
	if ( defined( 'TAXORA_TOPBAR_INJECTED' ) ) {
		return;
	}

	echo taxora_topbar_shortcode_callback();
	define( 'TAXORA_TOPBAR_INJECTED', true );
}
add_action( 'wp_body_open', 'taxora_topbar_inject_directly', 5 );

/**
 * Fallback Injection Logic
 * If wp_body_open wasn't called by the theme, we use a targeted buffer or JS fallback.
 */
function taxora_topbar_start_dashboard_buffer() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	if ( ! is_user_logged_in() || ! taxora_topbar_is_dashboard_request() ) {
		return;
	}

	// If already injected via wp_body_open, don't start buffer
	if ( defined( 'TAXORA_TOPBAR_INJECTED' ) ) {
		return;
	}

	// Use priority 0 to ensure we start before any other redirect/router logic (like WPFD exit)
	ob_start( 'taxora_topbar_inject_into_dashboard_html' );
}
add_action( 'template_redirect', 'taxora_topbar_start_dashboard_buffer', 0 );

function taxora_topbar_inject_into_dashboard_html( $html ) {
	// If already injected or empty, skip
	if ( defined( 'TAXORA_TOPBAR_INJECTED' ) || ! trim( $html ) ) {
		return $html;
	}

	// If Topbar is already present in HTML (e.g. manually added), skip
	if ( stripos( $html, 'id="taxora-topbar-root"' ) !== false ) {
		return $html;
	}

	$topbar_html = taxora_topbar_shortcode_callback();

	// Inject after <body> tag
	$pos = stripos( $html, '<body' );
	if ( $pos !== false ) {
		$end_body_tag = strpos( $html, '>', $pos );
		if ( $end_body_tag !== false ) {
			$body_tag = substr( $html, $pos, $end_body_tag - $pos + 1 );
			if ( stripos( $body_tag, 'taxora-topbar-active' ) === false ) {
				if ( preg_match( '/class=(["\'])(.*?)\1/i', $body_tag ) ) {
					$body_tag = preg_replace( '/class=(["\'])(.*?)\1/i', 'class=$1$2 taxora-topbar-active taxora-topbar-frontend has-taxora-topbar$1', $body_tag, 1 );
				} else {
					$body_tag = rtrim( $body_tag, '>' ) . ' class="taxora-topbar-active taxora-topbar-frontend has-taxora-topbar">';
				}
			}
			$before = substr( $html, 0, $pos ) . $body_tag;
			$after  = substr( $html, $end_body_tag + 1 );
			
			if ( ! defined( 'TAXORA_TOPBAR_INJECTED' ) ) {
				define( 'TAXORA_TOPBAR_INJECTED', true );
			}
			
			return $before . $topbar_html . $after;
		}
	}

	// Fallback: Inject after <html> tag if <body> is missing (partial templates)
	$pos = stripos( $html, '<html' );
	if ( $pos !== false ) {
		$end_html_tag = strpos( $html, '>', $pos );
		if ( $end_html_tag !== false ) {
			$before = substr( $html, 0, $end_html_tag + 1 );
			$after  = substr( $html, $end_html_tag + 1 );
			
			if ( ! defined( 'TAXORA_TOPBAR_INJECTED' ) ) {
				define( 'TAXORA_TOPBAR_INJECTED', true );
			}
			
			return $before . $topbar_html . $after;
		}
	}

	// Last resort: Prepend if we found some content but no structural tags
	if ( ! defined( 'TAXORA_TOPBAR_INJECTED' ) ) {
		define( 'TAXORA_TOPBAR_INJECTED', true );
	}
	return $topbar_html . $html;
}


// ============================================================
// AJAX handler for language update
// ============================================================
function taxora_update_user_language() {
	check_ajax_referer( 'taxora_lang_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_die( 'Not logged in' );
	}

	$lang = sanitize_text_field( $_POST['lang'] );
	$allowed_langs = array( 'en', 'bn', 'ar' );

	if ( in_array( $lang, $allowed_langs, true ) ) {
		update_user_meta( get_current_user_id(), 'taxora_language', $lang );

		$locale_map = array(
			'en' => 'en_US',
			'bn' => 'bn_BD',
			'ar' => 'ar',
		);

		if ( isset( $locale_map[ $lang ] ) ) {
			update_user_meta( get_current_user_id(), 'locale', $locale_map[ $lang ] );
		}

		wp_send_json_success( array( 'message' => 'Language updated' ) );
	} else {
		wp_send_json_error( array( 'message' => 'Invalid language' ) );
	}
}
add_action( 'wp_ajax_taxora_update_user_language', 'taxora_update_user_language' );


// Also ensure front-end dashboard output adds the body class
function taxora_topbar_frontend_body_class( $classes ) {
	if ( ! is_user_logged_in() ) {
		return $classes;
	}
	if ( taxora_topbar_is_dashboard_request() ) {
		$classes[] = 'taxora-topbar-active';
		$classes[] = 'taxora-topbar-frontend';
	}
	return $classes;
}
add_filter( 'body_class', 'taxora_topbar_frontend_body_class' );

// 5. Frontend dashboard CSS: fixed positioning + wrapper offset
// Hooked to wp_head for standard WordPress pages (accounting shortcode page)
// Also inlined in shortcode output for WPFD + inventory standalone templates
function taxora_topbar_frontend_head_css() {
	if ( ! is_user_logged_in() || wp_doing_ajax() ) {
		return;
	}
	if ( ! taxora_topbar_is_dashboard_request() ) {
		return;
	}
	echo '<style id="taxora-topbar-frontend-override">' . taxora_topbar_get_frontend_override_css() . '</style>';
}
add_action( 'wp_head', 'taxora_topbar_frontend_head_css', 1 );


// ============================================================
// THEME MODE CSS HELPER
// ============================================================
function taxora_topbar_get_theme_mode_css() {
	$theme_css_file = TAXORA_TOPBAR_PATH . 'assets/css/theme-mode.css';
	if ( file_exists( $theme_css_file ) ) {
		return file_get_contents( $theme_css_file );
	}
	return '';
}

// ============================================================
// INLINE CSS used by shortcode (frontend standalone)
// ============================================================
function taxora_topbar_get_inline_css() {
	return '
#taxora-topbar-root {
	all: initial;
	display: block;
	width: 100%;
	position: sticky;
	top: var(--taxora-admin-offset, 0px);
	z-index: 99999;
	margin-bottom: 25px; /* Visual gap below topbar */
	font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

#taxora-topbar-root * {
	box-sizing: border-box;
}

#taxora-topbar-root .taxora-topbar {
	background: linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.95) 100%);
	backdrop-filter: blur(20px);
	-webkit-backdrop-filter: blur(20px);
	border-bottom: 1px solid rgba(226,232,240,0.8);
	box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06), 0 1px 1px rgba(0,0,0,0.04);
	height: 56px;
	display: flex;
	align-items: center;
	padding: 0 24px;
	transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
	position: relative;
}

#taxora-topbar-root .taxora-topbar:hover { box-shadow: 0 8px 10px -1px rgba(0,0,0,0.12), 0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 2px rgba(0,0,0,0.06); }
#taxora-topbar-root .taxora-topbar-left { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
#taxora-topbar-root .taxora-topbar-btn {
	background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(249,250,251,0.8) 100%);
	border: 1px solid rgba(226,232,240,0.6);
	border-radius: 10px;
	padding: 8px 14px;
	font-size: 14px;
	font-weight: 500;
	color: #374151;
	cursor: pointer;
	transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
	display: flex;
	align-items: center;
	gap: 6px;
	text-decoration: none;
	position: relative;
	overflow: hidden;
}

#taxora-topbar-root .taxora-topbar-btn::before {
	content: "";
	position: absolute;
	top: 0; left: -100%;
	width: 100%; height: 100%;
	background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
	transition: left 0.5s;
	pointer-events: none;
}

#taxora-topbar-root .taxora-topbar-btn:hover {
	background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(241,245,249,0.9) 100%);
	border-color: rgba(99,102,241,0.3);
	transform: translateY(-2px);
	box-shadow: 0 8px 16px rgba(0,0,0,0.1), 0 4px 8px rgba(0,0,0,0.06);
}

#taxora-topbar-root .taxora-topbar-btn:hover::before { left: 100%; }
#taxora-topbar-root .taxora-topbar-btn:active { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.08); }
#taxora-topbar-root .taxora-topbar-text {
	font-size: 14px; font-weight: 500; color: #374151; text-decoration: none;
	transition: all 0.15s ease; padding: 6px 12px; border-radius: 8px;
}

#taxora-topbar-root .taxora-topbar-text:hover { background: rgba(0,0,0,0.05); color: #1f2937; transform: translateY(-1px); }
#taxora-topbar-root .taxora-topbar-center { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; font-weight: 500; color: #1f2937; }
#taxora-topbar-root .taxora-clock { font-variant-numeric: tabular-nums; letter-spacing: 0.02em; }
#taxora-topbar-root .taxora-calendar-btn { background: none; border: none; padding: 4px; cursor: pointer; border-radius: 6px; transition: background 0.15s ease; color: #6b7280; display: flex; align-items: center; }
#taxora-topbar-root .taxora-calendar-btn:hover { background: rgba(0,0,0,0.05); color: #374151; }
#taxora-topbar-root .taxora-date { color: #6b7280; font-weight: 400; }
#taxora-topbar-root .taxora-topbar-right { display: flex; align-items: center; gap: 12px; flex: 0 0 auto; }
#taxora-topbar-root .taxora-dropdown { position: relative; }
#taxora-topbar-root .taxora-dropdown-btn {
	background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.08); border-radius: 8px;
	padding: 6px 12px; font-size: 13px; font-weight: 500; color: #374151;
	cursor: pointer; transition: all 0.15s ease; display: flex; align-items: center; gap: 6px;
	min-width: 120px; justify-content: space-between;
}

#taxora-topbar-root .taxora-dropdown-btn:hover { background: rgba(0,0,0,0.05); border-color: rgba(0,0,0,0.12); transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
#taxora-topbar-root .taxora-dropdown-menu {
	position: absolute; top: calc(100% + 4px); right: 0;
	background: rgba(255,255,255,0.98); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
	border: 1px solid rgba(0,0,0,0.08); border-radius: 12px;
	box-shadow: 0 10px 25px rgba(0,0,0,0.1), 0 4px 10px rgba(0,0,0,0.05);
	min-width: 180px; padding: 8px;
	opacity: 0; visibility: hidden; transform: translateY(-8px);
	transition: all 0.15s ease; z-index: 100000;
}

#taxora-topbar-root .taxora-dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
#taxora-topbar-root .taxora-dropdown-menu a {
	display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 6px;
	font-size: 13px; font-weight: 500; color: #374151; text-decoration: none;
	transition: all 0.15s ease; cursor: pointer; border: none; background: none; width: 100%; text-align: left;
}

#taxora-topbar-root .taxora-dropdown-menu a:hover { background: rgba(0,0,0,0.05); color: #1f2937; transform: translateX(2px); }
#taxora-topbar-root .taxora-dropdown-menu a.active, #taxora-topbar-root .taxora-dropdown-menu a.selected { background: rgba(99,102,241,0.1); color: #6366f1; font-weight: 600; }
#taxora-topbar-root .taxora-dropdown-menu a.active::after, #taxora-topbar-root .taxora-dropdown-menu a.selected::after { content: "✓"; float: right; margin-left: 8px; }

#taxora-topbar-root .taxora-calendar-dropdown {
	position: absolute; top: 60px; left: 50%; transform: translateX(-50%);
	background: rgba(255,255,255,0.98); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
	border: 1px solid rgba(0,0,0,0.08); border-radius: 12px;
	box-shadow: 0 10px 25px rgba(0,0,0,0.1), 0 4px 10px rgba(0,0,0,0.05);
	min-width: 280px; padding: 12px; z-index: 100000;
	opacity: 0; visibility: hidden; transform: translateX(-50%) translateY(-8px);
	transition: all 0.15s ease;
}

#taxora-topbar-root .taxora-calendar-dropdown.show { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }
#taxora-topbar-root .taxora-calendar-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-weight: 600; color: #1f2937; }
#taxora-topbar-root .taxora-calendar-header button { background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.08); border-radius: 6px; padding: 4px 8px; font-size: 12px; cursor: pointer; transition: all 0.15s ease; }
#taxora-topbar-root .taxora-calendar-header button:hover { background: rgba(0,0,0,0.05); }
#taxora-topbar-root .taxora-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; font-size: 12px; }
#taxora-topbar-root .taxora-calendar-day-header { text-align: center; font-weight: 600; color: #6b7280; padding: 4px; }
#taxora-topbar-root .taxora-calendar-day { text-align: center; padding: 6px; border-radius: 6px; cursor: pointer; transition: all 0.15s ease; color: #374151; }
#taxora-topbar-root .taxora-calendar-day:hover { background: rgba(0,0,0,0.05); }
#taxora-topbar-root .taxora-calendar-day.today { background: #3b82f6; color: #fff; font-weight: 600; }

/* ===== Redesigned Addon Quick-Access Dropdown (Premium SaaS Layout) ===== */
#taxora-topbar-root .taxora-addon-dropdown-container {
	position: relative;
	display: inline-block;
	flex: 0 0 auto;
	margin: 0 12px;
}

#taxora-topbar-root .taxora-addon-trigger {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 8px 16px;
	border-radius: 20px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	border: 1px solid rgba(226, 232, 240, 0.8);
	background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
	color: #1e293b;
	transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
	outline: none;
	user-select: none;
	transform: translate3d(0, 0, 0);
	backface-visibility: hidden;
}

#taxora-topbar-root .taxora-addon-trigger:hover {
	transform: translateY(-1.5px);
	background: #ffffff;
	border-color: rgba(99, 102, 241, 0.35);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02);
	color: #000000;
}

#taxora-topbar-root .taxora-addon-trigger-icon {
	font-size: 14px;
	filter: drop-shadow(0 0 4px rgba(234, 179, 8, 0.2));
	animation: taxora-pulse-bolt 2s infinite ease-in-out;
}

@keyframes taxora-pulse-bolt {
	0%, 100% { transform: scale(1); opacity: 1; }
	50% { transform: scale(1.1); opacity: 0.85; }
}

#taxora-topbar-root .taxora-addon-trigger-chevron {
	transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
	opacity: 0.6;
}

#taxora-topbar-root .taxora-addon-trigger.open .taxora-addon-trigger-chevron,
#taxora-topbar-root .taxora-addon-trigger:hover .taxora-addon-trigger-chevron {
	transform: rotate(180deg);
	opacity: 1;
}

/* Redesigned Apps Dropdown Menu */
#taxora-topbar-root .taxora-addon-dropdown {
	position: absolute;
	top: calc(100% + 8px);
	left: 0;
	background: rgba(255, 255, 255, 0.96);
	backdrop-filter: blur(20px);
	-webkit-backdrop-filter: blur(20px);
	border: 1px solid rgba(226, 232, 240, 0.8);
	border-radius: 16px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.02);
	min-width: 310px;
	max-width: 360px;
	padding: 10px;
	opacity: 0;
	visibility: hidden;
	transform: translateY(-8px) scale(0.97);
	transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
	z-index: 100000;
	max-height: 400px;
	overflow-y: auto;
}

#taxora-topbar-root .taxora-addon-dropdown::-webkit-scrollbar {
	width: 5px;
}
#taxora-topbar-root .taxora-addon-dropdown::-webkit-scrollbar-track {
	background: transparent;
}
#taxora-topbar-root .taxora-addon-dropdown::-webkit-scrollbar-thumb {
	background: rgba(0, 0, 0, 0.08);
	border-radius: 10px;
}
#taxora-topbar-root .taxora-addon-dropdown::-webkit-scrollbar-thumb:hover {
	background: rgba(0, 0, 0, 0.15);
}

#taxora-topbar-root .taxora-addon-dropdown.show {
	opacity: 1;
	visibility: visible;
	transform: translateY(0) scale(1);
}

#taxora-topbar-root .taxora-addon-dropdown-header {
	padding: 6px 12px 10px;
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	color: #64748b;
	letter-spacing: 0.05em;
	border-bottom: 1px solid rgba(226, 232, 240, 0.6);
	margin-bottom: 8px;
}

#taxora-topbar-root .taxora-addon-dropdown-list {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

/* Addon Item Design */
#taxora-topbar-root .taxora-addon-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 14px;
	border-radius: 12px;
	text-decoration: none;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	color: #334155;
	border: 1px solid transparent;
	transform: translate3d(0, 0, 0);
	backface-visibility: hidden;
}

#taxora-topbar-root .taxora-addon-item-icon {
	width: 38px;
	height: 38px;
	border-radius: 10px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--addon-color, #6366f1);
	flex-shrink: 0;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	transform: translate3d(0,0,0);
}

#taxora-topbar-root .taxora-addon-item-content {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

#taxora-topbar-root .taxora-addon-item-title {
	font-size: 14px;
	font-weight: 600;
	color: #0f172a;
	transition: color 0.2s ease;
}

#taxora-topbar-root .taxora-addon-item-desc {
	font-size: 11px;
	color: #64748b;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	font-weight: 400;
}

#taxora-topbar-root .taxora-addon-item-indicator {
	color: var(--addon-color, #6366f1);
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0.85;
	flex-shrink: 0;
}

/* Hover States */
#taxora-topbar-root .taxora-addon-item:hover {
	background: rgba(248, 250, 252, 0.95);
	border-color: rgba(226, 232, 240, 0.7);
	transform: translateX(4px);
}

#taxora-topbar-root .taxora-addon-item:hover .taxora-addon-item-icon {
	transform: scale(1.05);
	box-shadow: 0 0 12px color-mix(in srgb, var(--addon-color, #6366f1) 25%, transparent);
	filter: brightness(1.05);
}

#taxora-topbar-root .taxora-addon-item:hover .taxora-addon-item-title {
	color: var(--addon-color, #6366f1);
}

/* Active Highlight Module */
#taxora-topbar-root .taxora-addon-item--active {
	background: rgba(99, 102, 241, 0.05);
	border-color: rgba(99, 102, 241, 0.12);
}

#taxora-topbar-root .taxora-addon-item--active .taxora-addon-item-title {
	color: var(--addon-color, #6366f1);
	font-weight: 700;
}

/* Responsive & Mobile Support */
@media (max-width: 768px) {
	#taxora-topbar-root .taxora-addon-dropdown-container {
		margin: 0 4px;
	}
	#taxora-topbar-root .taxora-addon-trigger {
		padding: 6px 10px;
		gap: 4px;
		font-size: 12px;
		border-radius: 16px;
	}
	#taxora-topbar-root .taxora-addon-trigger-label {
		display: none;
	}
	#taxora-topbar-root .taxora-addon-dropdown {
		position: fixed;
		top: auto;
		bottom: 0;
		left: 0;
		right: 0;
		width: 100vw;
		max-width: 100vw;
		border-radius: 20px 20px 0 0;
		border: 1px solid rgba(226, 232, 240, 0.9);
		border-bottom: none;
		box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.15);
		transform: translateY(100%);
		max-height: 70vh;
	}
	#taxora-topbar-root .taxora-addon-dropdown.show {
		transform: translateY(0);
	}
}

/* Dark Mode Overrides */
body.taxora-dark-mode #taxora-topbar-root .taxora-addon-trigger {
	border-color: var(--taxora-border-color, rgba(255,255,255,0.08));
	background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.9) 100%);
	color: var(--taxora-text-primary, #f8fafc);
}

body.taxora-dark-mode #taxora-topbar-root .taxora-addon-trigger:hover {
	background: var(--taxora-hover-bg, rgba(255,255,255,0.05));
	border-color: rgba(99, 102, 241, 0.45);
	color: #ffffff;
}

body.taxora-dark-mode #taxora-topbar-root .taxora-addon-dropdown {
	background: rgba(15, 23, 42, 0.95);
	border-color: var(--taxora-border-color, rgba(255,255,255,0.08));
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4), 0 1px 3px rgba(0, 0, 0, 0.2);
}

body.taxora-dark-mode #taxora-topbar-root .taxora-addon-dropdown-header {
	border-bottom-color: var(--taxora-border-color, rgba(255,255,255,0.08));
	color: var(--taxora-text-muted, #94a3b8);
}

body.taxora-dark-mode #taxora-topbar-root .taxora-addon-item {
	color: var(--taxora-text-secondary, #cbd5e1);
}

body.taxora-dark-mode #taxora-topbar-root .taxora-addon-item-title {
	color: var(--taxora-text-primary, #f8fafc);
}

body.taxora-dark-mode #taxora-topbar-root .taxora-addon-item-desc {
	color: var(--taxora-text-muted, #94a3b8);
}

body.taxora-dark-mode #taxora-topbar-root .taxora-addon-item:hover {
	background: rgba(30, 41, 59, 0.6);
	border-color: rgba(255, 255, 255, 0.05);
}

body.taxora-dark-mode #taxora-topbar-root .taxora-addon-item--active {
	background: rgba(99, 102, 241, 0.12);
	border-color: rgba(99, 102, 241, 0.2);
}

@media (max-width: 1440px) {
	#taxora-topbar-root .taxora-topbar { padding: 0 22px; height: 54px; }
	#taxora-topbar-root .taxora-topbar-left { gap: 7px; }
	#taxora-topbar-root .taxora-topbar-btn { padding: 7px 13px; font-size: 13px; border-radius: 9px; }
	#taxora-topbar-root .taxora-topbar-center { font-size: 13px; gap: 7px; }
	#taxora-topbar-root .taxora-clock { font-size: 12px; }
	#taxora-topbar-root .taxora-date { font-size: 11px; }
	#taxora-topbar-root .taxora-calendar-btn { padding: 7px; font-size: 15px; }
	#taxora-topbar-root .taxora-topbar-right { gap: 9px; }
	#taxora-topbar-root .taxora-dropdown-btn { min-width: 110px; font-size: 12px; padding: 7px 11px; border-radius: 9px; }
	#taxora-topbar-root .taxora-dropdown-menu { min-width: 190px; border-radius: 11px; }
	#taxora-topbar-root .taxora-calendar-dropdown { min-width: 310px; border-radius: 13px; }
}
@media (max-width: 1024px) {
	#taxora-topbar-root .taxora-topbar { padding: 0 18px; height: 50px; }
	#taxora-topbar-root .taxora-topbar-left { gap: 6px; }
	#taxora-topbar-root .taxora-topbar-btn { padding: 6px 11px; font-size: 12px; border-radius: 8px; }
	#taxora-topbar-root .taxora-topbar-center { font-size: 12px; gap: 6px; }
	#taxora-topbar-root .taxora-clock { font-size: 11px; }
	#taxora-topbar-root .taxora-date { font-size: 10px; }
	#taxora-topbar-root .taxora-calendar-btn { padding: 6px; font-size: 14px; }
	#taxora-topbar-root .taxora-topbar-right { gap: 7px; }
	#taxora-topbar-root .taxora-dropdown-btn { min-width: 95px; font-size: 11px; padding: 6px 9px; border-radius: 8px; }
	#taxora-topbar-root .taxora-dropdown-menu { min-width: 170px; border-radius: 10px; }
	#taxora-topbar-root .taxora-calendar-dropdown { min-width: 280px; border-radius: 12px; }
}
@media (max-width: 768px) {
	#taxora-topbar-root .taxora-topbar { padding: 0 8px; height: 44px; }
	#taxora-topbar-root .taxora-topbar-left { gap: 2px; }
	#taxora-topbar-root .taxora-topbar-btn { padding: 6px 8px; font-size: 12px; min-width: 36px; height: 32px; }
	#taxora-topbar-root .taxora-topbar-text { padding: 6px 8px; font-size: 12px; }
	#taxora-topbar-root .taxora-topbar-center { font-size: 12px; gap: 6px; }
	#taxora-topbar-root .taxora-clock { font-size: 11px; }
	#taxora-topbar-root .taxora-date { font-size: 10px; display: none; }
	#taxora-topbar-root .taxora-calendar-btn { padding: 6px; font-size: 14px; }
	#taxora-topbar-root .taxora-topbar-right { gap: 6px; }
	#taxora-topbar-root .taxora-dropdown-btn { min-width: 80px; font-size: 11px; padding: 6px 8px; height: 32px; }
	#taxora-topbar-root .taxora-dropdown-menu { min-width: 160px; right: -8px; }
	#taxora-topbar-root .taxora-calendar-dropdown { min-width: 280px; left: 50%; transform: translateX(-50%); right: auto; }
	#taxora-topbar-root .taxora-calendar-dropdown.show { transform: translateX(-50%); }
	#taxora-topbar-root .taxora-calendar-grid { font-size: 11px; }
	#taxora-topbar-root .taxora-calendar-day { padding: 4px; font-size: 11px; }
	#taxora-topbar-root .taxora-calendar-header { font-size: 13px; }
	#taxora-topbar-root .taxora-calendar-header button { padding: 3px 6px; font-size: 11px; }
}
@media (max-width: 480px) {
	#taxora-topbar-root .taxora-topbar { padding: 0 6px; }
	#taxora-topbar-root .taxora-topbar-left { gap: 1px; }
	#taxora-topbar-root .taxora-topbar-btn { padding: 5px 6px; min-width: 32px; height: 28px; }
	#taxora-topbar-root .taxora-topbar-center { gap: 4px; }
	#taxora-topbar-root .taxora-clock { font-size: 10px; }
	#taxora-topbar-root .taxora-calendar-btn { padding: 4px; font-size: 12px; }
	#taxora-topbar-root .taxora-topbar-right { gap: 4px; }
	#taxora-topbar-root .taxora-dropdown-btn { min-width: 70px; font-size: 10px; padding: 5px 6px; height: 28px; }
	#taxora-topbar-root .taxora-dropdown-menu { min-width: 140px; font-size: 12px; }
	#taxora-topbar-root .taxora-dropdown-menu a { padding: 6px 8px; font-size: 12px; }
}
';
}
