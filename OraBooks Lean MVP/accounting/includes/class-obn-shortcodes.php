<?php

class OBN_Shortcodes
{

	private $auth;
	private $dashboard;

	public function __construct($auth, $dashboard)
	{
		$this->auth = $auth;
		$this->dashboard = $dashboard;
		add_shortcode('orabooks_accounting', array($this, 'render_dashboard'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

		// Hard stop for assets on inventory pages
		add_action('template_redirect', function () {
			if (strpos($_SERVER['REQUEST_URI'], '/inventory') !== false) {
				remove_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
			}
		});
	}

	public function enqueue_assets()
	{
		global $post;
		$is_accounting_page = false;
		if (is_page() && isset($post->post_content) && has_shortcode($post->post_content, 'orabooks_accounting')) {
			$is_accounting_page = true;
		} elseif (isset($post->post_name) && (strpos($post->post_name, 'accounting') !== false)) {
			$is_accounting_page = true;
		} elseif (strpos($_SERVER['REQUEST_URI'] ?? '', '/accounting') !== false) {
			$is_accounting_page = true;
		}

		if (!$is_accounting_page)
			return;

		// Load Tailwind via CDN script so utility classes are available
		wp_enqueue_script('obn-tailwind', 'https://cdn.tailwindcss.com', array(), null, false);
		// Load FontAwesome
		wp_enqueue_style('obn-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css', array(), '6.4.2');
		// Load SweetAlert2
		wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11.0.0', false);

		// Load Bootstrap 5 (required for modals)
		wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', [], '5.3.2');
		wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', [], '5.3.2', false);

		// Load Select2 & jQuery UI for Quotations
		wp_enqueue_style('obn-select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
		wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
		wp_enqueue_script('obn-select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', false);
		wp_enqueue_script('jquery-ui-autocomplete');

		// DataTables & Buttons Extensions (Print, Excel, PDF, CSV, Column Visibility)
		wp_enqueue_style('obn-datatables-css', 'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css');
		wp_enqueue_style('obn-datatables-buttons-css', 'https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css');

		wp_enqueue_script('obn-datatables-js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', ['jquery'], '1.13.7', false);
		wp_enqueue_script('obn-datatables-buttons-js', 'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js', ['obn-datatables-js'], '2.4.2', false);
		wp_enqueue_script('obn-jszip', 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', [], '3.10.1', false);
		wp_enqueue_script('obn-pdfmake', 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js', [], '0.2.7', false);
		wp_enqueue_script('obn-vfs-fonts', 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js', ['obn-pdfmake'], '0.2.7', false);
		wp_enqueue_script('obn-buttons-html5', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js', ['obn-datatables-buttons-js'], '2.4.2', false);
		wp_enqueue_script('obn-buttons-print', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js', ['obn-datatables-buttons-js'], '2.4.2', false);
		wp_enqueue_script('obn-buttons-colvis', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js', ['obn-datatables-buttons-js'], '2.4.2', false);

		// Manual Export Libraries (Inventory Style)
		wp_enqueue_script('obn-jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', false);
		wp_enqueue_script('obn-jspdf-autotable', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js', ['obn-jspdf'], '3.8.2', false);
		wp_enqueue_script('obn-html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', [], '1.4.1', false);
		wp_enqueue_script('obn-xlsx', 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js', [], '0.18.5', false);

		// optional: define Tailwind config (colors, etc.) by printing inline script before the CDN loads if needed

		wp_enqueue_style('obn-accounting-style', OBN_ACCOUNTING_PLUGIN_URL . 'assets/css/style.css', array(), OBN_ACCOUNTING_VERSION);
		wp_enqueue_style('obn-accounting-brand-theme', OBN_ACCOUNTING_PLUGIN_URL . 'assets/css/brand-theme.css', array('obn-accounting-style'), OBN_ACCOUNTING_VERSION);
		wp_enqueue_script('obn-accounting-script', OBN_ACCOUNTING_PLUGIN_URL . 'assets/js/script.js', array('jquery', 'jquery-ui-autocomplete'), OBN_ACCOUNTING_VERSION, false);
		wp_localize_script(
			'obn-accounting-script',
			'obn_ajax',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('frontend_ajax_nonce'),
				'auth_nonce' => wp_create_nonce('obn_auth_nonce'),
				'expense_nonce' => wp_create_nonce('obn_expense_action_nonce'),
				'je_nonce' => wp_create_nonce('obn_je_action_nonce'),
				'permissions_nonce' => wp_create_nonce('obn_permissions_nonce')
			)
		);
	}

	public function render_dashboard()
	{
		// If not logged in at all, redirect to login
		if (!is_user_logged_in()) {
			// Option: Redirect automatically
			wp_redirect(wp_login_url(get_permalink()));
			exit;
		}

		// Logged in but no access
		if ( $this->auth->is_partner_org_blocked() ) {
			return '<div class="obn-notice obn-error">
                <h3>Access Denied</h3>
                <p>Partner accounts cannot perform accounting operations.</p>
                <p>You earn commissions from qualified customers attributed to your Partner Code. No accounting features.</p>
            </div>';
		}

		if ( ! $this->auth->can_access_accounting() ) {
			return '<div class="obn-notice obn-error">
                <h3>Access Denied</h3>
                <p>Your organization does not have permission to access the Advanced Accounting workspace.</p>
                <p>Please contact your organization owner or administrator.</p>
            </div>';
		}

		$user = $this->auth->get_current_user();

		// Hide Admin Bar and Theme Headers via CSS
		// Make Dashboard Full Screen Overlay
		ob_start();
		?>
		<style>
			/* Hide WP Admin Bar */
			#wpadminbar {
				display: none !important;
			}

			html {
				margin-top: 0 !important;
			}

			/* Hide Theme Elements (Best Guess Common Selectors) */
			header,
			.header,
			#masthead,
			.site-header,
			.top-bar,
			.nav,
			nav:not(.obn-sidebar-nav),
			footer,
			.footer,
			#colophon,
			.site-footer {
				display: none !important;
			}

			/* Force Full Screen Dashboard */
			body.orabooks-accounting-page,
			body {
				margin: 0;
				padding: 0;
				overflow: hidden;
				height: 100%;
				width: 100%;
			}

			.obn-dashboard-wrapper {
				display: flex;
				flex-direction: row;
				position: fixed;
				inset: 0;
				width: 100%;
				height: 100vh;
				height: 100dvh;
				max-height: 100dvh;
				z-index: 99999;
				background: #f3f4f6;
				overflow: hidden;
			}

			.obn-sidebar {
				height: 100%;
				max-height: 100%;
				min-height: 0;
				overflow: hidden;
			}

			.obn-sidebar-nav {
				flex: 1 1 auto;
				min-height: 0;
				overflow-y: auto;
				overflow-x: hidden;
				-webkit-overflow-scrolling: touch;
			}

			.obn-main-content {
				flex: 1 1 auto;
				min-width: 0;
				min-height: 0;
				overflow: hidden;
			}

			.obn-content-area {
				min-height: 0;
				min-width: 0;
				overflow-y: auto;
				overflow-x: hidden;
				-webkit-overflow-scrolling: touch;
			}

			/* Fix for Print Previews */
			@media print {

				#wpadminbar,
				.dt-buttons,
				.dataTables_filter,
				.obn-delete-cash-tx {
					/* display: none !important; */
				}

				body,
				html {
					overflow: visible !important;
					height: auto !important;
					margin: 0 !important;
					padding: 0 !important;
				}

				.obn-dashboard-wrapper {
					position: relative !important;
					height: auto !important;
					overflow: visible !important;
					z-index: auto !important;
					background: #fff !important;
				}

				.obn-card {
					box-shadow: none !important;
					border: none !important;
					padding: 0 !important;
				}

				table {
					width: 100% !important;
					page-break-inside: auto;
				}

				tr {
					page-break-inside: avoid;
					page-break-after: auto;
				}
			}
		</style>
		<?php
		$styles = ob_get_clean();

		return $styles . $this->dashboard->render($user);
	}

}
