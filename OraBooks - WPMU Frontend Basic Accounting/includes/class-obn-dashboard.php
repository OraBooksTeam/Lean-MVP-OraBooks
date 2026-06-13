<?php
class OBN_Dashboard
{
	private function get_customers_count()
	{
		global $wpdb;
		return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}orabooks_db_customers WHERE status = 1");
	}

	private function get_sales_stats()
	{
		global $wpdb;
		$stats = $wpdb->get_row("SELECT SUM(grand_total) as total_sales, SUM(paid_amount) as total_paid, SUM(grand_total - paid_amount) as total_due FROM {$wpdb->prefix}orabooks_db_sales WHERE status = 1");

		if (!$stats) {
			return (object) ['total_sales' => 0.0, 'total_paid' => 0.0, 'total_due' => 0.0];
		}

		return (object) [
			'total_sales' => (float) ($stats->total_sales ?? 0),
			'total_paid' => (float) ($stats->total_paid ?? 0),
			'total_due' => (float) ($stats->total_due ?? 0)
		];
	}

	private function get_monthly_sales_data()
	{
		global $wpdb;
		$year = date('Y');
		$results = $wpdb->get_results($wpdb->prepare("SELECT MONTH(sales_date) as month, SUM(grand_total) as total FROM {$wpdb->prefix}orabooks_db_sales WHERE status = 1 AND YEAR(sales_date) = %d GROUP BY MONTH(sales_date) ORDER BY month ASC", $year));
		$data = array_fill(1, 12, 0);
		foreach ($results as $row) {
			$data[(int) $row->month] = (float) $row->total;
		}
		return ['labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], 'data' => array_values($data)];
	}

	private function get_top_items_data()
	{
		global $wpdb;
		$results = $wpdb->get_results("SELECT i.item_name, SUM(si.sales_qty) as total_qty FROM {$wpdb->prefix}orabooks_db_salesitems si JOIN {$wpdb->prefix}orabooks_db_items i ON si.item_id = i.id GROUP BY si.item_id ORDER BY total_qty DESC LIMIT 5");
		$labels = [];
		$data = [];
		if ($results) {
			foreach ($results as $row) {
				$labels[] = $row->item_name;
				$data[] = (float) $row->total_qty;
			}
		} else {
			$labels = ['No Data'];
			$data = [1];
		}
		return ['labels' => $labels, 'data' => $data];
	}

	public function render($user)
	{
		ob_start();

		// Fetch Dashboard Data
		$cust_count = $this->get_customers_count();
		$sales_stats = $this->get_sales_stats();
		$monthly_sales = $this->get_monthly_sales_data();
		$top_items = $this->get_top_items_data();

		global $wpdb;
		$currency_symbol = '$';
		$currency_row = $wpdb->get_row("SELECT symbol FROM {$wpdb->prefix}orabooks_db_currency WHERE status = 1 LIMIT 1");
		if ($currency_row)
			$currency_symbol = $currency_row->symbol;
		$can_manage_settings = current_user_can('manage_options') || (function_exists('is_super_admin') && is_super_admin());
		?>
		<div class="obn-dashboard-wrapper">
			<div class="obn-overlay"></div>
			<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
			<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
			<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
			<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
			<!-- Sidebar -->
			<div class="obn-sidebar">
				<div class="obn-sidebar-brand">
					<h3>ORA ACCOUNTING</h3>
				</div>
				<nav class="obn-sidebar-nav">
					<ul>
						<?php echo OBN_Sidebar::render_sidebar(); ?>
					</ul>
				</nav>
			</div>

			<!-- Main Content -->
			<div class="obn-main-content">
				<div class="obn-top-bar">
					<div class="flex items-center">
						<button type="button" class="obn-hamburger">
							<i class="fa-solid fa-bars"></i>
						</button>
						<h2>Welcome, <?php echo esc_html($user->username); ?></h2>
					</div>
				</div>
				<div class="obn-content-area">
					<!-- Setting - Currency (List / Add / Edit views) -->
					<?php if ($can_manage_settings): ?>
						<div id="obn-view-setting-currency-list" class="obn-view-section">
							<div class="obn-card p-6 !pt-2">
								<div class="flex items-center justify-between mb-6">
									<h3 class="text-2xl font-bold text-gray-800">Currency List</h3>
									<button type="button" id="obn-show-currency-add"
										class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add New
										Currency</button>
								</div>
								<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
									<div class="relative w-full md:w-96">
										<span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
											<i class="fa-solid fa-magnifying-glass"></i>
										</span>
										<input type="search" id="obn-currency-search"
											class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm transition-all"
											placeholder="Search currencies...">
									</div>

									<div class="flex items-center gap-2">
										<!-- Column Visibility -->
										<div class="relative inline-block text-left">
											<button type="button"
												class="obn-column-toggle-btn inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
												<i class="fa-solid fa-columns mr-2"></i> Columns
											</button>
											<div
												class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
												<div class="py-1 p-3 space-y-2">
													<?php
													$cur_cols = ['#', 'Name', 'Code', 'Symbol'];
													foreach ($cur_cols as $idx => $name): ?>
														<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
															<input type="checkbox" checked
																class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
																data-column="<?php echo $idx; ?>" data-table="#obn-currency-table">
															<span
																class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
														</label>
													<?php endforeach; ?>
												</div>
											</div>
										</div>

										<div class="flex items-center bg-gray-100 p-1 rounded-lg">
											<button id="printBtn"
												class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
												data-table="#obn-currency-table" data-title="Currency List" title="Print">
												<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
											</button>
											<button id="pdfBtn"
												class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
												data-table="#obn-currency-table" data-title="Currency List" title="PDF">
												<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
											</button>
											<button id="excelBtn"
												class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
												data-table="#obn-currency-table" data-title="Currency List" title="Excel">
												<i class="fa-solid fa-file-excel mr-1"></i> <span
													class="hidden sm:inline">Excel</span>
											</button>
											<button id="csvBtn"
												class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
												data-table="#obn-currency-table" data-title="Currency List" title="CSV">
												<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
											</button>
										</div>
									</div>
								</div>

								<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
									<table id="obn-currency-table" class="w-full text-sm">
										<thead class="bg-gray-50 border-b border-gray-200">
											<tr>
												<th class="px-4 py-3 text-left font-semibold text-gray-700">#</th>
												<th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
												<th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
												<th class="px-4 py-3 text-left font-semibold text-gray-700">Symbol</th>
												<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
												<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
											</tr>
										</thead>
										<tbody class="divide-y divide-gray-200">
											<?php
											$currencies = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}orabooks_db_currency ORDER BY id DESC");
											if ($currencies):
												foreach ($currencies as $c): ?>
													<tr class="hover:bg-gray-50">
														<td class="px-4 py-3 text-gray-600"><?php echo esc_html($c->id); ?></td>
														<td class="px-4 py-3 text-gray-800 font-medium">
															<?php echo esc_html($c->currency_name); ?>
														</td>
														<td class="px-4 py-3 text-gray-600"><?php echo esc_html($c->currency_code); ?></td>
														<td class="px-4 py-3 text-gray-600"><?php echo esc_html($c->symbol); ?></td>
														<td class="px-4 py-3 text-center no-export">
															<label class="flex items-center justify-center">
																<input type="checkbox" class="obn-toggle-status"
																	data-id="<?php echo esc_attr($c->id); ?>"
																	data-nonce="<?php echo wp_create_nonce('obn_auth_nonce'); ?>"
																	data-status="<?php echo esc_attr($c->status); ?>" <?php checked($c->status, 1); ?>
																	style="width:18px;height:18px;cursor:pointer;">
															</label>
														</td>
														<td class="px-4 py-3 text-right space-x-2 no-export">
															<button
																class="obn-edit-currency px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
																data-id="<?php echo esc_attr($c->id); ?>"
																data-name="<?php echo esc_attr($c->currency_name); ?>"
																data-code="<?php echo esc_attr($c->currency_code); ?>"
																data-symbol="<?php echo esc_attr($c->symbol); ?>">Edit</button>
															<button
																class="obn-delete-currency px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
																data-id="<?php echo esc_attr($c->id); ?>">Delete</button>
														</td>
													</tr>
												<?php endforeach; else: ?>
												<tr>
													<td colspan="6" class="px-4 py-8 text-center text-gray-500">No currencies found.
													</td>
												</tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div id="obn-view-setting-currency-add" class="obn-view-section">
							<div class="obn-card p-6 !pt-4">
								<h3 class="text-2xl font-bold text-gray-800 mb-6">Add New Currency</h3>
								<form id="obn-currency-add-form"
									class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-2xl">
									<input type="hidden" name="action" value="obn_insert_currency">
									<input type="hidden" name="security" value="<?php echo wp_create_nonce('obn_auth_nonce'); ?>">
									<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
										<div>
											<label class="block text-sm font-semibold text-gray-700 mb-2">Currency Name <span
													class="text-red-600">*</span></label>
											<input type="text" name="currency_name" class="w-full px-4 py-2 border rounded"
												placeholder="e.g., US Dollar" required>
										</div>
										<div>
											<label class="block text-sm font-semibold text-gray-700 mb-2">Currency Code <span
													class="text-red-600">*</span></label>
											<input type="text" name="currency_code" class="w-full px-4 py-2 border rounded"
												placeholder="e.g., USD" required>
										</div>
										<div class="col-span-2">
											<label class="block text-sm font-semibold text-gray-700 mb-2">Currency Symbol <span
													class="text-red-600">*</span></label>
											<input type="text" name="symbol" class="w-full px-4 py-2 border rounded"
												placeholder="e.g., $" required>
										</div>
									</div>
									<div class="mt-6 flex gap-2">
										<button type="submit" id="obn-currency-add-save"
											class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded font-semibold transition">Save
											Currency</button>
										<button type="button" id="obn-currency-add-cancel"
											class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded font-semibold transition">Cancel</button>
									</div>
								</form>
							</div>
						</div>

						<div id="obn-view-setting-currency-edit" class="obn-view-section">
							<div class="obn-card p-6 !pt-4">
								<h3 class="text-2xl font-bold text-gray-800 mb-6">Edit Currency</h3>
								<form id="obn-currency-edit-form"
									class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-2xl">
									<input type="hidden" name="action" value="obn_update_currency">
									<input type="hidden" name="security" value="<?php echo wp_create_nonce('obn_auth_nonce'); ?>">
									<input type="hidden" name="id" id="obn_edit_currency_id">
									<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
										<div>
											<label class="block text-sm font-semibold text-gray-700 mb-2">Currency Name <span
													class="text-red-600">*</span></label>
											<input type="text" name="currency_name" id="obn_edit_currency_name"
												class="w-full px-4 py-2 border rounded" required>
										</div>
										<div>
											<label class="block text-sm font-semibold text-gray-700 mb-2">Currency Code <span
													class="text-red-600">*</span></label>
											<input type="text" name="currency_code" id="obn_edit_currency_code"
												class="w-full px-4 py-2 border rounded" required>
										</div>
										<div class="col-span-2">
											<label class="block text-sm font-semibold text-gray-700 mb-2">Currency Symbol <span
													class="text-red-600">*</span></label>
											<input type="text" name="symbol" id="obn_edit_symbol"
												class="w-full px-4 py-2 border rounded" required>
										</div>
									</div>
									<div class="mt-6 flex gap-2">
										<button type="submit" id="obn-currency-edit-save"
											class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold transition">Update
											Currency</button>
										<button type="button" id="obn-currency-edit-cancel"
											class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded font-semibold transition">Back
											to List</button>
									</div>
								</form>
							</div>
						</div>
					<?php endif; ?>
					<div id="obn-view-all-features" class="obn-view-section">
						<?php
						if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/settings/all-features.php')) {
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/settings/all-features.php';
						}
						?>
					</div>
					<!-- Dashboard View -->
					<div id="obn-view-dashboard" class="obn-view-section active">
						<!-- Top Stats Cards -->
						<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
							<!-- Customers -->
							<div
								class="bg-gradient-to-br from-blue-500 to-blue-400 rounded-xl p-6 text-white shadow-lg relative overflow-hidden transform hover:-translate-y-1 transition duration-300">
								<div class="relative z-10">
									<p class="text-blue-100 text-xs font-bold uppercase tracking-wider">Total Customers</p>
									<h3 class="text-3xl font-bold mt-1"><?php echo esc_html($cust_count); ?></h3>
								</div>
								<i class="fa-solid fa-users absolute right-4 top-4 text-5xl text-white opacity-20"></i>
							</div>
							<!-- Sales -->
							<div
								class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-6 text-white shadow-lg relative overflow-hidden transform hover:-translate-y-1 transition duration-300">
								<div class="relative z-10">
									<p class="text-purple-100 text-xs font-bold uppercase tracking-wider">Total Sales</p>
									<h3 class="text-3xl font-bold mt-1">
										<?php echo esc_html($currency_symbol . number_format($sales_stats->total_sales, 2)); ?>
									</h3>
								</div>
								<i class="fa-solid fa-chart-line absolute right-4 top-4 text-5xl text-white opacity-20"></i>
							</div>
							<!-- Paid -->
							<div
								class="bg-gradient-to-br from-blue-400 to-blue-500 rounded-xl p-6 text-white shadow-lg relative overflow-hidden transform hover:-translate-y-1 transition duration-300">
								<div class="relative z-10">
									<p class="text-emerald-100 text-xs font-bold uppercase tracking-wider">Total Received</p>
									<h3 class="text-3xl font-bold mt-1">
										<?php echo esc_html($currency_symbol . number_format($sales_stats->total_paid, 2)); ?>
									</h3>
								</div>
								<i class="fa-solid fa-wallet absolute right-4 top-4 text-5xl text-white opacity-20"></i>
							</div>
							<!-- Due -->
							<div
								class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-6 text-white shadow-lg relative overflow-hidden transform hover:-translate-y-1 transition duration-300">
								<div class="relative z-10">
									<p class="text-red-100 text-xs font-bold uppercase tracking-wider">Total Due</p>
									<h3 class="text-3xl font-bold mt-1">
										<?php echo esc_html($currency_symbol . number_format($sales_stats->total_due, 2)); ?>
									</h3>
								</div>
								<i
									class="fa-solid fa-circle-exclamation absolute right-4 top-4 text-5xl text-white opacity-20"></i>
							</div>
						</div>

						<!-- Charts Section -->
						<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
							<!-- Bar Chart -->
							<div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm lg:col-span-2">
								<h3 class="text-lg font-bold text-gray-800 mb-4">Monthly Sales (<?php echo date('Y'); ?>)</h3>
								<div class="h-64">
									<canvas id="obnMonthlySalesChart"></canvas>
								</div>
							</div>
							<!-- Donut Chart -->
							<div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
								<h3 class="text-lg font-bold text-gray-800 mb-4">Top Selling Items</h3>
								<div class="h-64 relative">
									<canvas id="obnTopItemsChart"></canvas>
								</div>
							</div>
						</div>

						<!-- Bottom Grid: Quick Reports & Actions -->
						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<!-- Quick Reports -->
							<div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
								<h3 class="text-lg font-bold text-gray-800 mb-4">Quick Reports</h3>
								<div class="space-y-4">
									<a href="#"
										class="obn-dash-link flex items-center p-3 bg-gray-50 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition"
										data-target="quotation-list">
										<div
											class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3">
											<i class="fa-solid fa-file-invoice"></i>
										</div>
										<span class="font-medium">Quotation List</span>
									</a>
									<a href="#"
										class="obn-dash-link flex items-center p-3 bg-gray-50 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition"
										data-target="expense-list">
										<div
											class="w-10 h-10 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center mr-3">
											<i class="fa-solid fa-receipt"></i>
										</div>
										<span class="font-medium">Expense Report</span>
									</a>
									<a href="#"
										class="obn-dash-link flex items-center p-3 bg-gray-50 rounded-lg hover:bg-emerald-50 hover:text-emerald-600 transition"
										data-target="advance-list">
										<div
											class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mr-3">
											<i class="fa-solid fa-money-bill-transfer"></i>
										</div>
										<span class="font-medium">Advance Payments</span>
									</a>
									<a href="#"
										class="obn-dash-link flex items-center p-3 bg-gray-50 rounded-lg hover:bg-orange-50 hover:text-orange-600 transition"
										data-target="coupon-customer-list">
										<div
											class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center mr-3">
											<i class="fa-solid fa-ticket"></i>
										</div>
										<span class="font-medium">Customer Coupons</span>
									</a>
								</div>
							</div>

							<!-- Quick Actions -->
							<div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
								<h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
								<div class="grid grid-cols-2 gap-4">
									<a href="#"
										class="obn-dash-link flex flex-col items-center justify-center p-4 bg-gray-50 rounded-xl hover:bg-indigo-50 hover:text-indigo-600 transition border border-gray-100"
										data-target="quotation-add">
										<i class="fa-solid fa-plus-circle text-2xl mb-2 text-indigo-500"></i>
										<span class="text-sm font-semibold">New Quotation</span>
									</a>
									<a href="#"
										class="obn-dash-link flex flex-col items-center justify-center p-4 bg-gray-50 rounded-xl hover:bg-pink-50 hover:text-pink-600 transition border border-gray-100"
										data-target="expense-add">
										<i class="fa-solid fa-circle-minus text-2xl mb-2 text-pink-500"></i>
										<span class="text-sm font-semibold">Add Expense</span>
									</a>
									<a href="#"
										class="obn-dash-link flex flex-col items-center justify-center p-4 bg-gray-50 rounded-xl hover:bg-teal-50 hover:text-teal-600 transition border border-gray-100"
										data-target="advance-add">
										<i class="fa-solid fa-hand-holding-dollar text-2xl mb-2 text-teal-500"></i>
										<span class="text-sm font-semibold">Add Advance</span>
									</a>
									<a href="#"
										class="obn-dash-link flex flex-col items-center justify-center p-4 bg-gray-50 rounded-xl hover:bg-amber-50 hover:text-amber-600 transition border border-gray-100"
										data-target="coupon-create-customer">
										<i class="fa-solid fa-tags text-2xl mb-2 text-amber-500"></i>
										<span class="text-sm font-semibold">Assign Coupon</span>
									</a>
								</div>
							</div>
						</div>
					</div>


					<!-- Setting Section -->
					<div id="obn-view-setting" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3>Settings</h3>
							<p>Manage your account settings here.</p>
						</div>
					</div>

					<!-- Tax Section (List / Add / Edit views) -->
					<div id="obn-view-setting-tax-list" class="obn-view-section">
						<div class="obn-card p-6 !pt-0">
							<div class="flex items-center justify-between mb-6">
								<h3 class="text-2xl font-bold text-gray-800 !mt-0">Tax List</h3>
								<button id="obn-show-tax-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add New Tax</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-tax-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search taxes...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="printBtn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-tax-table" data-title="Tax List" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="pdfBtn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-tax-table" data-title="Tax List" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="excelBtn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-tax-table" data-title="Tax List" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="csvBtn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-tax-table" data-title="Tax List" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last) -->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$tax_cols = ['#', 'Name', 'Rate', 'Type'];
												foreach ($tax_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
															data-column="<?php echo $idx; ?>" data-table="#obn-tax-table">
														<span class="ml-3 text-sm text-gray-700 font-bold uppercase">
															<?php echo $name; ?>
														</span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-tax-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">#</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Rate</th>
											<!-- <th class="px-4 py-3 text-left font-semibold text-gray-700">Type</th> -->
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody id="obn-tax-tbody" class="divide-y divide-gray-200">
										<?php
										global $wpdb;
										$tax_table = $wpdb->prefix . 'orabooks_db_tax';
										$taxes = $wpdb->get_results("SELECT * FROM $tax_table ORDER BY id DESC");
										if ($taxes):
											$cnt = 1;
											foreach ($taxes as $t):
												?>
												<tr data-id="<?php echo esc_attr($t->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($cnt++); ?>
													</td>
													<td class="px-4 py-3 text-gray-800 font-medium">
														<?php echo esc_html($t->tax_name); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($t->tax); ?>%
													</td>
													<!-- <td class="px-4 py-3 text-gray-600">&mdash;</td> -->
													<td class="px-4 py-3 text-center no-export">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="obn-toggle-tax-status sr-only peer"
																data-id="<?php echo esc_attr($t->id); ?>"
																data-status="<?php echo esc_attr($t->status); ?>"
																data-nonce="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>"
																<?php echo ($t->status == 1) ? 'checked' : ''; ?>>
															<div
																class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
															</div>
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-edit-tax px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($t->id); ?>"
															data-name="<?php echo esc_attr($t->tax_name); ?>"
															data-rate="<?php echo esc_attr($t->tax); ?>">Edit</button>
														<button
															class="button button-small button-danger obn-delete-tax px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($t->id); ?>">Delete</button>
													</td>
												</tr>
												<?php
											endforeach;
										else:
											?>
											<tr>
												<td colspan="6" class="px-4 py-8 text-center text-gray-500">No taxes found. Add one
													to get started.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-setting-tax-add" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add New Tax</h3>
							<form id="obn-tax-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-lg">
								<input type="hidden" name="action" value="obn_insert_tax">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>">
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Tax Name <span
											class="text-red-500">*</span></label>
									<input type="text" id="obn_add_tax_name" name="tax_name"
										class="w-full px-4 py-2 border rounded" placeholder="VAT" required>
								</div>
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Tax Percentage (%) <span
											class="text-red-500">*</span></label>
									<input type="number" step="0.01" id="obn_add_tax_rate" name="tax"
										class="w-full px-4 py-2 border rounded" placeholder="15" required>
								</div>
								<div class="flex gap-2">
									<button type="submit" id="obn-tax-add-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save Tax</button>
									<button type="button" id="obn-tax-add-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<div id="obn-view-setting-tax-edit" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Tax</h3>
							<form id="obn-tax-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-lg">
								<input type="hidden" name="action" value="obn_update_tax">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>">
								<input type="hidden" id="obn_edit_tax_id" name="id" value="">
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Tax Name <span
											class="text-red-500">*</span></label>
									<input type="text" id="obn_edit_tax_name" name="tax_name"
										class="w-full px-4 py-2 border rounded" placeholder="VAT" required>
								</div>
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Tax Percentage (%) <span
											class="text-red-500">*</span></label>
									<input type="number" step="0.01" id="obn_edit_tax_rate" name="tax"
										class="w-full px-4 py-2 border rounded" placeholder="15" required>
								</div>
								<div class="flex gap-2">
									<button type="submit" id="obn-tax-edit-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update Tax</button>
									<button type="button" id="obn-tax-edit-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>
					<!--Tax section end-->

					<!-- Payment Types Section (List / Add / Edit views) -->
					<div id="obn-view-setting-payment-types-list" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-6">
								<h3 class="text-2xl font-bold text-gray-800">Payment Types</h3>
								<button id="obn-show-payment-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add Payment
									Type</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-payment-types-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search payment types...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="printBtn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-payment-types-table" data-title="Payment Types" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="pdfBtn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-payment-types-table" data-title="Payment Types" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="excelBtn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-payment-types-table" data-title="Payment_Types" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="csvBtn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-payment-types-table" data-title="Payment_Types" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last)-->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$pt_cols = ['#', 'Payment Type'];
												foreach ($pt_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
															data-column="<?php echo $idx; ?>" data-table="#obn-payment-types-table">
														<span class="ml-3 text-sm text-gray-700 font-bold uppercase">
															<?php echo $name; ?>
														</span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-payment-types-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">#</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Payment Type</th>
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody id="obn-paymenttypes-tbody" class="divide-y divide-gray-200">
										<?php
										global $wpdb;
										$pt_table = $wpdb->prefix . 'orabooks_db_paymenttypes';
										$pts = $wpdb->get_results("SELECT * FROM $pt_table ORDER BY id DESC");
										if ($pts):
											$cnt = 1;
											foreach ($pts as $pt):
												?>
												<tr data-id="<?php echo esc_attr($pt->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($cnt++); ?>
													</td>
													<td class="px-4 py-3 text-gray-800 font-medium">
														<?php echo esc_html($pt->payment_type); ?>
													</td>
													<td class="px-4 py-3 text-center no-export">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="obn-toggle-payment-status sr-only peer"
																data-id="<?php echo esc_attr($pt->id); ?>"
																data-status="<?php echo esc_attr($pt->status); ?>"
																data-nonce="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>"
																<?php echo ($pt->status == 1) ? 'checked' : ''; ?>>
															<div
																class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
															</div>
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-edit-payment px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($pt->id); ?>"
															data-name="<?php echo esc_attr($pt->payment_type); ?>">Edit</button>
														<button
															class="button button-small button-danger obn-delete-payment px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($pt->id); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="4" class="px-4 py-8 text-center text-gray-500">No payment types found.
													Add one to get started.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-setting-payment-types-add" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Payment Type</h3>
							<form id="obn-payment-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-lg">
								<input type="hidden" name="action" value="obn_insert_payment_type">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>">
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Payment Type <span
											class="text-red-500">*</span></label>
									<div id="payment-types-container" style="position: relative; width: 100%;">
										<div id="payment-type-input-wrapper"
											style="border: 1px solid #d1d5db; border-radius: 0.375rem; background: white; min-height: 42px; padding: 4px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; width: 100%;">
											<input type="text" id="obn_add_payment_type" name="payment_type_input"
												style="border: none; outline: none; background: transparent; flex: 1; min-width: 120px; padding: 6px 4px; font-size: 14px;"
												placeholder="Type payment type and press comma, space, or Enter">
											<div id="payment-type-tags"
												style="display: flex; flex-wrap: wrap; gap: 4px; align-items: center;"></div>
										</div>
										<input type="hidden" name="payment_type" id="payment-type-hidden" value="">
									</div>
								</div>
								<div class="flex gap-2">
									<button type="submit" id="obn-payment-add-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save</button>
									<button type="button" id="obn-payment-add-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<div id="obn-view-setting-payment-types-edit" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Payment Type</h3>
							<form id="obn-payment-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-lg">
								<input type="hidden" name="action" value="obn_update_payment_type">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>">
								<input type="hidden" id="obn_edit_payment_id" name="id" value="">
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Payment Type <span
											class="text-red-500">*</span></label>
									<input type="text" id="obn_edit_payment_type" name="payment_type"
										class="w-full px-4 py-2 border rounded" placeholder="e.g. Cash" required>
								</div>
								<div class="flex gap-2">
									<button type="submit" id="obn-payment-edit-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update</button>
									<button type="button" id="obn-payment-edit-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>
					<!--Payment typ eends here-->

					<!--Journal entries start here -->
					<div id="obn-view-journal-entry-list" class="obn-view-section">
						<?php global $wpdb;
						$je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
						$journal_entries = $wpdb->get_results("SELECT * FROM {$je_table} ORDER BY id DESC");
						$je_nonce = wp_create_nonce('obn_je_action_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-4">
								<h3 class="text-2xl font-bold text-gray-800">Journal Entries</h3>
								<button id="obn-show-journal-entry-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add New Journal
									Entry</button>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-journal-entry-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Reference No</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Total Debit</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Total Credit</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-200">
										<?php if ($journal_entries):
											foreach ($journal_entries as $je): ?>
												<tr data-id="<?php echo esc_attr($je->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($je->id); ?>
													</td>
													<td class="px-4 py-3 text-gray-800">
														<?php echo esc_html($je->entry_date); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($je->reference_no); ?>
													</td>
													<td class="px-4 py-3 text-gray-800 font-medium">
														<?php echo esc_html(number_format_i18n($je->total_debit, 2)); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html(number_format_i18n($je->total_credit, 2)); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<span
															class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">
															<?php echo esc_html($je->status); ?>
														</span>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-view-je px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($je->id); ?>"
															data-nonce="<?php echo esc_attr($je_nonce); ?>">View</button>
														<!-- <button
															class="button button-small button-danger obn-delete-je px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php //echo esc_attr($je->id); ?>"
															data-nonce="<?php //echo esc_attr($je_nonce); ?>">Delete</button> -->
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="7" class="px-4 py-8 text-center text-gray-500">No journal entries
													found.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-journal-entry-add" class="obn-view-section">
						<?php $coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
						$coa_accounts = $wpdb->get_results("SELECT id, account_code, account_name FROM {$coa_table} WHERE status = 1 ORDER BY account_name ASC"); ?>
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Journal Entry</h3>
							<form id="obn-journal-entry-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-5xl">
								<input type="hidden" name="action" value="obn_insert_journal_entry">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_je_action_nonce')); ?>">

								<div class="grid grid-cols-3 gap-6 mb-6 pb-6 border-b border-gray-200">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Entry Date <span
												class="text-red-500">*</span></label>
										<input type="date" name="entry_date"
											class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500 hover:bg-gray-50 transition"
											required value="<?php echo date('Y-m-d'); ?>">
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Reference No</label>
										<input type="text" name="reference_no"
											class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500 hover:bg-gray-50 transition"
											placeholder="E.g., ADJ-2023-01">
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Currency</label>
										<input type="text" name="currency"
											class="w-full px-4 py-2 border rounded bg-gray-100 text-gray-500 font-medium cursor-not-allowed"
											value="Base currency" readonly>
									</div>
									<div class="col-span-3">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Description / Memo</label>
										<textarea name="description"
											class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500 hover:bg-gray-50 transition"
											rows="2" placeholder="Describe the reason for this journal entry..."></textarea>
									</div>
								</div>

								<div class="mb-4 flex justify-between items-center">
									<h4 class="text-lg font-bold text-gray-800">Ledger Lines</h4>
									<button type="button" id="obn-je-add-line"
										class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-lg text-sm font-semibold border border-indigo-200 transition shadow-sm">+
										Add Line</button>
								</div>

								<div class="overflow-x-auto mb-6 bg-white rounded-lg border border-gray-200 shadow-sm">
									<table class="w-full text-sm text-left border-collapse" id="obn-je-lines-table">
										<thead class="bg-gray-50 border-b border-gray-200">
											<tr>
												<th class="px-4 py-3 font-semibold text-gray-700 w-1/3">Account <span
														class="text-red-500">*</span></th>
												<th class="px-4 py-3 font-semibold text-gray-700 w-1/3">Description</th>
												<th class="px-4 py-3 font-semibold text-gray-700 w-32">Debit</th>
												<th class="px-4 py-3 font-semibold text-gray-700 w-32">Credit</th>
												<th class="px-2 py-3 font-semibold text-center text-gray-700 w-12"></th>
											</tr>
										</thead>
										<tbody class="divide-y divide-gray-100" id="obn-je-lines-body">
											<tr class="je-line-row group hover:bg-gray-50 transition">
												<td class="px-2 py-2">
													<select name="je_account[]"
														class="w-full px-3 py-2 border rounded text-sm je-account-select focus:ring-2 focus:ring-blue-500 bg-white"
														required>
														<option value="">Select Account</option>
														<?php foreach ($coa_accounts as $a): ?>
															<option value="<?php echo esc_attr($a->id); ?>">
																<?php echo esc_html($a->account_code ? $a->account_code . ' - ' : '') . esc_html($a->account_name); ?>
															</option>
														<?php endforeach; ?>
													</select>
												</td>
												<td class="px-2 py-2">
													<input type="text" name="je_desc[]"
														class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500 hover:bg-white"
														placeholder="Line description...">
												</td>
												<td class="px-2 py-2">
													<input type="number" step="0.01" min="0" name="je_debit[]"
														class="w-full px-3 py-2 border rounded text-sm text-right je-debit-input focus:ring-2 focus:ring-blue-500 hover:bg-white text-gray-800 font-medium"
														placeholder="0.00">
												</td>
												<td class="px-2 py-2">
													<input type="number" step="0.01" min="0" name="je_credit[]"
														class="w-full px-3 py-2 border rounded text-sm text-right je-credit-input focus:ring-2 focus:ring-blue-500 hover:bg-white text-gray-800 font-medium"
														placeholder="0.00">
												</td>
												<td class="px-2 py-2 text-center">
													<button type="button"
														class="text-red-400 hover:text-red-600 focus:outline-none obn-je-remove-line opacity-50 group-hover:opacity-100 transition-opacity"
														title="Remove Line"><i class="fa-solid fa-trash-can"></i></button>
												</td>
											</tr>
											<tr class="je-line-row group hover:bg-gray-50 transition">
												<td class="px-2 py-2">
													<select name="je_account[]"
														class="w-full px-3 py-2 border rounded text-sm je-account-select focus:ring-2 focus:ring-blue-500 bg-white"
														required>
														<option value="">Select Account</option>
														<?php foreach ($coa_accounts as $a): ?>
															<option value="<?php echo esc_attr($a->id); ?>">
																<?php echo esc_html($a->account_code ? $a->account_code . ' - ' : '') . esc_html($a->account_name); ?>
															</option>
														<?php endforeach; ?>
													</select>
												</td>
												<td class="px-2 py-2">
													<input type="text" name="je_desc[]"
														class="w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:ring-blue-500 hover:bg-white"
														placeholder="Line description...">
												</td>
												<td class="px-2 py-2">
													<input type="number" step="0.01" min="0" name="je_debit[]"
														class="w-full px-3 py-2 border rounded text-sm text-right je-debit-input focus:ring-2 focus:ring-blue-500 hover:bg-white text-gray-800 font-medium"
														placeholder="0.00">
												</td>
												<td class="px-2 py-2">
													<input type="number" step="0.01" min="0" name="je_credit[]"
														class="w-full px-3 py-2 border rounded text-sm text-right je-credit-input focus:ring-2 focus:ring-blue-500 hover:bg-white text-gray-800 font-medium"
														placeholder="0.00">
												</td>
												<td class="px-2 py-2 text-center">
													<button type="button"
														class="text-red-400 hover:text-red-600 focus:outline-none obn-je-remove-line opacity-50 group-hover:opacity-100 transition-opacity"
														title="Remove Line"><i class="fa-solid fa-trash-can"></i></button>
												</td>
											</tr>
										</tbody>
										<tfoot class="bg-gray-50 border-t border-gray-200">
											<tr>
												<td colspan="2" class="px-4 py-4 text-right font-bold text-gray-700">Totals:
												</td>
												<td class="px-4 py-4 font-bold text-gray-800 text-right bg-white border-l border-r border-gray-200 shadow-inner"
													id="je-total-debit">0.00</td>
												<td class="px-4 py-4 font-bold text-gray-800 text-right bg-white shadow-inner"
													id="je-total-credit">0.00</td>
												<td></td>
											</tr>
										</tfoot>
									</table>
								</div>

								<div id="je-balance-warning"
									class="hidden mb-6 text-sm text-red-600 font-semibold flex items-center bg-red-50 p-3 rounded-lg border border-red-200">
									<i class="fa-solid fa-triangle-exclamation mr-2 text-lg"></i> Journal entry is out of
									balance. Debits must mathematically equal Credits to proceed.
								</div>

								<div class="mt-4 flex gap-3">
									<button type="submit" id="obn-journal-entry-add-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-all shadow-md active:bg-blue-800 opacity-50 cursor-not-allowed flex items-center"
										disabled>
										<i class="fa-solid fa-paper-plane mr-2"></i> Post Journal Entry
									</button>
									<button type="button" id="obn-journal-entry-add-cancel"
										class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 px-6 py-2.5 rounded-lg font-semibold transition-colors shadow-sm">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<script>
						document.addEventListener('DOMContentLoaded', function () {
							const linesBody = document.getElementById('obn-je-lines-body');
							if (!linesBody) return;

							const addLineBtn = document.getElementById('obn-je-add-line');
							const totalDebitEl = document.getElementById('je-total-debit');
							const totalCreditEl = document.getElementById('je-total-credit');
							const balanceWarning = document.getElementById('je-balance-warning');
							const submitBtn = document.getElementById('obn-journal-entry-add-save');

							// Auto-route View logic for Journal Add 
							document.addEventListener('click', function (e) {
								if (e.target && e.target.id === 'obn-show-journal-entry-add') {
									e.preventDefault();
									document.querySelectorAll('.obn-view-section').forEach(function (el) {
										el.style.display = 'none';
									});
									document.getElementById('obn-view-journal-entry-add').style.display = 'block';
								}
								// Cancel routing back
								if (e.target && e.target.id === 'obn-journal-entry-add-cancel') {
									e.preventDefault();
									document.getElementById('obn-journal-entry-add-form').reset();
									calculateTotals();
									document.querySelectorAll('.obn-view-section').forEach(function (el) {
										el.style.display = 'none';
									});
									document.getElementById('obn-view-journal-entry-list').style.display = 'block';
								}
							});

							let rowTemplate = linesBody.querySelector('.je-line-row').cloneNode(true);

							function calculateTotals(e) {
								let totalDebit = 0;
								let totalCredit = 0;

								document.querySelectorAll('.je-debit-input').forEach(input => {
									let val = parseFloat(input.value);
									if (!isNaN(val)) totalDebit += val;
								});

								document.querySelectorAll('.je-credit-input').forEach(input => {
									let val = parseFloat(input.value);
									if (!isNaN(val)) totalCredit += val;
								});

								totalDebitEl.textContent = totalDebit.toFixed(2);
								totalCreditEl.textContent = totalCredit.toFixed(2);

								let isValid = false;
								if (totalDebit === 0 && totalCredit === 0) {
									balanceWarning.classList.add('hidden');
									isValid = false;
								} else if (Math.abs(totalDebit - totalCredit) > 0.001) {
									balanceWarning.classList.remove('hidden');
									totalDebitEl.classList.add('text-red-600');
									totalCreditEl.classList.add('text-red-600');
									isValid = false;
								} else {
									balanceWarning.classList.add('hidden');
									totalDebitEl.classList.remove('text-red-600');
									totalCreditEl.classList.remove('text-red-600');
									isValid = true;
								}

								if (isValid) {
									submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
									submitBtn.removeAttribute('disabled');
								} else {
									submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
									submitBtn.setAttribute('disabled', 'disabled');
								}
							}

							addLineBtn.addEventListener('click', function () {
								let newRow = rowTemplate.cloneNode(true);
								newRow.querySelectorAll('input').forEach(inp => inp.value = '');
								newRow.querySelectorAll('select').forEach(sel => sel.value = '');
								linesBody.appendChild(newRow);
								attachRowEvents(newRow);
							});

							function attachRowEvents(row) {
								let debitInput = row.querySelector('.je-debit-input');
								let creditInput = row.querySelector('.je-credit-input');

								debitInput.addEventListener('input', function () {
									if (this.value !== '') creditInput.value = '';
									calculateTotals();
								});
								creditInput.addEventListener('input', function () {
									if (this.value !== '') debitInput.value = '';
									calculateTotals();
								});

								row.querySelector('.obn-je-remove-line').addEventListener('click', function () {
									if (linesBody.querySelectorAll('.je-line-row').length > 2) {
										row.remove();
										calculateTotals();
									} else {
										alert('A journal entry must contain at least two account lines to balance.');
									}
								});
							}

							linesBody.querySelectorAll('.je-line-row').forEach(row => attachRowEvents(row));

							jQuery('#obn-journal-entry-add-form').on('submit', function (e) {
								e.preventDefault();
								var form = jQuery(this);
								var data = form.serialize();
								var btn = jQuery('#obn-journal-entry-add-save');
								if (btn.prop('disabled')) return;

								btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
								jQuery.post(obn_ajax.ajax_url, data, function (response) {
									if (response.success) {
										alert('âœ… ' + response.data.message);
										localStorage.setItem('obn-after-reload-view', 'obn-view-journal-entry-list');
										location.reload();
									} else {
										alert('âŒ ' + (response.data || 'Insert failed.'));
										btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane mr-2"></i> Post Journal Entry');
									}
								}).fail(function () {
									alert('âŒ Request failed.');
									btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane mr-2"></i> Post Journal Entry');
								});
							});
						});
					</script>
					<!--Journal entry ends here-->

					<!---------============== Acc. Report Section ===================------------------>
					<div id="obn-view-acc-report" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800">Accounting Reports</h3>
							<p class="text-gray-600">Select a report from the menu to view details.</p>
						</div>
					</div>

					<!-- Journal Report Section -->
					<div id="obn-view-journal-report" class="obn-view-section">
						<div class="obn-card p-6 !pt-0">
							<?php include OBN_ACCOUNTING_PLUGIN_DIR . 'acc_report/journal-report.php'; ?>
						</div>
					</div>

					<!-- Trial Balance Report Section -->
					<div id="obn-view-trial-balance-report" class="obn-view-section">
						<div class="obn-card p-6 !pt-0">
							<?php include OBN_ACCOUNTING_PLUGIN_DIR . 'acc_report/trial-balance-report.php'; ?>
						</div>
					</div>

					<!-- Income Statement Report Section -->
					<div id="obn-view-income-statement-report" class="obn-view-section">
						<div class="obn-card p-6 !pt-0">
							<?php include OBN_ACCOUNTING_PLUGIN_DIR . 'acc_report/income-statement-report.php'; ?>
						</div>
					</div>

					<!-- Balance Sheet Report Section -->
					<div id="obn-view-balance-sheet-report" class="obn-view-section">
						<div class="obn-card p-6 !pt-0">
							<?php include OBN_ACCOUNTING_PLUGIN_DIR . 'acc_report/balance-sheet-report.php'; ?>
						</div>
					</div>

					<!-- Ledger Report Section -->
					<div id="obn-view-ledger-report" class="obn-view-section">
						<div class="obn-card p-6 !pt-0">
							<?php include OBN_ACCOUNTING_PLUGIN_DIR . 'acc_report/ledger-report.php'; ?>
						</div>
					</div>

					<!--Bank Accounts Sections start-->
					<!--Bank Accounts Section (View Accounts - copied from Orabooks design) -->
					<div id="obn-view-accounts" class="obn-view-section">
						<?php global $wpdb;
						$acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
						$accounts = $wpdb->get_results("SELECT a.id, a.parent_id, a.account_code, a.account_name, a.balance, a.note, a.created_date, a.created_time, a.system_ip, a.system_name, a.status, p.account_name AS parent_account_name FROM {$acc_table} AS a LEFT JOIN {$acc_table} AS p ON a.parent_id = p.id ORDER BY a.id DESC");
						$acc_nonce = wp_create_nonce('obn_accounts_action_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-4">
								<h3 class="text-2xl font-bold text-gray-800">Bank Accounts</h3>
								<button id="obn-show-account-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add New</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-accounts-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search accounts...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="printBtn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-accounts-table" data-title="Accounts List" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="pdfBtn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-accounts-table" data-title="Accounts List" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="excelBtn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-accounts-table" data-title="Accounts List" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="csvBtn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-accounts-table" data-title="Accounts List" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last) -->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$acc_cols = ['ID', 'Parent', 'Code', 'Name', 'Balance', 'Note', 'Date', 'Time', 'IP', 'System'];
												foreach ($acc_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
															data-column="<?php echo $idx; ?>" data-table="#obn-accounts-table">
														<span
															class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-accounts-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Parent Account</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Account Code</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Account Name</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Opening Balance</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Note</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Created Date</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Created Time</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">System IP</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">System Name</th>
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-200">
										<?php if ($accounts):
											$cnt = 1;
											foreach ($accounts as $acc): ?>
												<tr data-id="<?php echo esc_attr($acc->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($acc->id); ?></td>
													<td class="px-4 py-3 text-gray-800">
														<?php echo esc_html($acc->parent_account_name ?: ''); ?>
													</td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($acc->account_code); ?>
													</td>
													<td class="px-4 py-3 text-gray-800 font-medium">
														<?php echo esc_html($acc->account_name); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html(number_format_i18n($acc->balance, 2)); ?>
													</td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($acc->note); ?></td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($acc->created_date); ?>
													</td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($acc->created_time); ?>
													</td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($acc->system_ip); ?></td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($acc->system_name); ?>
													</td>
													<td class="px-4 py-3 text-center no-export">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="obn-toggle-account-status sr-only peer"
																data-id="<?php echo esc_attr($acc->id); ?>"
																data-status="<?php echo esc_attr($acc->status); ?>"
																data-nonce="<?php echo esc_attr($acc_nonce); ?>" <?php echo ($acc->status == 1) ? 'checked' : ''; ?>>
															<div
																class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
															</div>
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-edit-account px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($acc->id); ?>">Edit</button>
														<button
															class="button button-small button-danger obn-delete-account px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($acc->id); ?>"
															data-nonce="<?php echo esc_attr($acc_nonce); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="12" class="px-4 py-8 text-center text-gray-500">No accounts found.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-accounts-add" class="obn-view-section">
						<?php global $wpdb;
						$acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
						$parent_accounts = $wpdb->get_results("SELECT id, account_name FROM {$acc_table} ORDER BY account_name ASC");
						$acc_nonce = wp_create_nonce('obn_accounts_action_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Bank Account</h3>
							<form id="obn-account-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_insert_account">
								<input type="hidden" name="security" value="<?php echo esc_attr($acc_nonce); ?>">
								<div class="grid grid-cols-2 gap-6">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Parent Account</label>
										<select name="parent_account" class="w-full px-4 py-2 border rounded">
											<option value="0">Select Parent Head</option>
											<?php foreach ($parent_accounts as $pa): ?>
												<option value="<?php echo esc_attr($pa->id); ?>">
													<?php echo esc_html($pa->account_name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Account Code <span
												class="text-red-500">*</span></label>
										<input type="text" name="account_code" class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Account Name <span
												class="text-red-500">*</span></label>
										<input type="text" name="account_name" class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Opening Balance <span
												class="text-red-500">*</span></label>
										<input type="number" step="0.01" name="opening_balance"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Note</label>
										<textarea name="note" class="w-full px-4 py-2 border rounded" rows="4"></textarea>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-account-add-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Add Account</button>
									<button type="button" id="obn-account-add-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>

					<div id="obn-view-accounts-edit" class="obn-view-section">
						<?php global $wpdb;
						$acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
						$parent_accounts = $wpdb->get_results("SELECT id, account_name FROM {$acc_table} ORDER BY account_name ASC");
						$acc_nonce = wp_create_nonce('obn_accounts_action_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Bank Account</h3>
							<form id="obn-account-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_update_account">
								<input type="hidden" name="security" value="<?php echo esc_attr($acc_nonce); ?>">
								<input type="hidden" id="obn_edit_account_id" name="id" value="">
								<div class="grid grid-cols-2 gap-6">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Parent Account</label>
										<select id="obn_edit_parent_account" name="parent_account"
											class="w-full px-4 py-2 border rounded">
											<option value="0"> Select Parent Head </option>
											<?php foreach ($parent_accounts as $pa): ?>
												<option value="<?php echo esc_attr($pa->id); ?>">
													<?php echo esc_html($pa->account_name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Account Code <span
												class="text-red-500">*</span></label>
										<input type="text" id="obn_edit_account_code" name="account_code"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Account Name <span
												class="text-red-500">*</span></label>
										<input type="text" id="obn_edit_account_name" name="account_name"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Opening Balance <span
												class="text-red-500">*</span></label>
										<input type="number" step="0.01" id="obn_edit_opening_balance" name="opening_balance"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Note</label>
										<textarea id="obn_edit_note" name="note" class="w-full px-4 py-2 border rounded"
											rows="4"></textarea>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-account-edit-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update
										Account</button>
									<button type="button" id="obn-account-edit-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>
					<!--Bank Accounts section ends here-->

					<!-- Account Types (CoA) Section -->
					<div id="obn-view-coa-types-list" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-6">
								<h3 class="text-2xl font-bold text-gray-800">Account Types</h3>
								<button id="obn-show-coa-type-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add Account
									Type</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-coa-types-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search account types...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coa-types-table" data-title="Account Types" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coa-types-table" data-title="Account Types" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coa-types-table" data-title="Account_Types" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-coa-types-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">#</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Account Type</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody id="obn-coa-types-tbody" class="divide-y divide-gray-200">
										<?php
										global $wpdb;
										$coa_table = $wpdb->prefix . 'orabooks_ac_coa_types';
										$coa_types = $wpdb->get_results("SELECT * FROM $coa_table ORDER BY id DESC");
										if ($coa_types):
											$cnt = 1;
											foreach ($coa_types as $ct):
												?>
												<tr data-id="<?php echo esc_attr($ct->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($cnt++); ?>
													</td>
													<td class="px-4 py-3 text-gray-800 font-medium">
														<?php echo esc_html($ct->coa_type); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($ct->description); ?>
													</td>
													<td class="px-4 py-3 text-center no-export">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="obn-toggle-coa-type-status sr-only peer"
																data-id="<?php echo esc_attr($ct->id); ?>"
																data-status="<?php echo esc_attr($ct->status); ?>"
																data-nonce="<?php echo esc_attr(wp_create_nonce('obn_accounts_action_nonce')); ?>"
																<?php echo ($ct->status == 1) ? 'checked' : ''; ?>>
															<div
																class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
															</div>
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-edit-coa-type px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($ct->id); ?>"
															data-name="<?php echo esc_attr($ct->coa_type); ?>"
															data-desc="<?php echo esc_attr($ct->description); ?>">Edit</button>
														<button
															class="button button-small button-danger obn-delete-coa-type px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($ct->id); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="5" class="px-4 py-8 text-center text-gray-500">No account types found.
													Add one to get started.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-coa-types-add" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Account Type</h3>
							<form id="obn-coa-type-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-lg">
								<input type="hidden" name="action" value="obn_insert_coa_type">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_accounts_action_nonce')); ?>">
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Account Type <span
											class="text-red-500">*</span></label>
									<input type="text" name="coa_type" class="w-full px-4 py-2 border rounded"
										placeholder="e.g. Asset, Liability, Equity" required>
								</div>
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
									<textarea name="description" class="w-full px-4 py-2 border rounded" rows="3"
										placeholder="Optional description..."></textarea>
								</div>
								<div class="flex gap-2">
									<button type="submit"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save</button>
									<button type="button"
										class="obn-coa-type-add-cancel bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<div id="obn-view-coa-types-edit" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Account Type</h3>
							<form id="obn-coa-type-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-lg">
								<input type="hidden" name="action" value="obn_update_coa_type">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_accounts_action_nonce')); ?>">
								<input type="hidden" id="obn_edit_coa_type_id" name="id" value="">
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Account Type <span
											class="text-red-500">*</span></label>
									<input type="text" id="obn_edit_coa_type_name" name="coa_type"
										class="w-full px-4 py-2 border rounded" required>
								</div>
								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
									<textarea id="obn_edit_coa_type_desc" name="description"
										class="w-full px-4 py-2 border rounded" rows="3"></textarea>
								</div>
								<div class="flex gap-2">
									<button type="submit"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update</button>
									<button type="button"
										class="obn-coa-type-edit-cancel bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>
					<!--Account type (COA) end here-->

					<!-- Chart of Accounts List Section -->
					<div id="obn-view-coa-list" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-6">
								<h3 class="text-2xl font-bold text-gray-800">Chart of Accounts</h3>
								<button id="obn-show-coa-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add Account</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-coa-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search accounts...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coa-table" data-title="Chart of Accounts" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coa-table" data-title="Chart of Accounts" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coa-table" data-title="Chart_of_Accounts" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
									</div>
								</div>
							</div>

							<?php
							global $wpdb;
							$coa_types_table = $wpdb->prefix . 'orabooks_ac_coa_types';
							$all_coa_types = $wpdb->get_results("SELECT id, coa_type FROM $coa_types_table WHERE status = 1 ORDER BY coa_type ASC");
							?>
							<!-- Tab Filter for Account Types -->
							<div class="flex overflow-x-auto gap-2 mb-4 border-b border-gray-200 pb-0 obn-coa-tabs">
								<button
									class="obn-coa-tab-btn active px-4 py-2 bg-blue-50 text-blue-700 font-bold rounded-t-lg border-b-2 border-blue-600 transition-colors whitespace-nowrap"
									data-filter="all">All Accounts</button>
								<?php foreach ($all_coa_types as $type): ?>
									<button
										class="obn-coa-tab-btn px-4 py-2 text-gray-600 hover:text-blue-600 font-medium rounded-t-lg border-b-2 border-transparent hover:border-blue-300 transition-colors whitespace-nowrap"
										data-filter="<?php echo esc_attr(sanitize_title($type->coa_type)); ?>">
										<?php echo esc_html($type->coa_type); ?>
									</button>
								<?php endforeach; ?>
								<button
									class="obn-coa-tab-btn px-4 py-2 text-gray-600 hover:text-blue-600 font-medium rounded-t-lg border-b-2 border-transparent hover:border-blue-300 transition-colors whitespace-nowrap"
									data-filter="uncategorized">Uncategorized</button>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-coa-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Account Name</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Account Type</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Tax</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700">Balance</th>
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody id="obn-coa-tbody" class="divide-y divide-gray-200">
										<?php
										global $wpdb;
										$coa_list_table = $wpdb->prefix . 'orabooks_ac_coa_list';
										$coa_types_table = $wpdb->prefix . 'orabooks_ac_coa_types';
										$tax_table = $wpdb->prefix . 'orabooks_db_tax';

										$journal_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';
										$coa_accounts = $wpdb->get_results("
                                    SELECT cl.*, ct.coa_type, t.tax_name,
                                           COALESCE((SELECT SUM(debit - credit) FROM $journal_line_table WHERE account_id = cl.id), 0) as balance
                                    FROM $coa_list_table cl 
                                    LEFT JOIN $coa_types_table ct ON cl.coa_type_id = ct.id 
                                    LEFT JOIN $tax_table t ON cl.tax_id = t.id 
                                    ORDER BY ct.coa_type ASC, cl.account_code ASC
                                ");

										if ($coa_accounts):
											$current_type = '';
											foreach ($coa_accounts as $ca):
												if ($current_type !== $ca->coa_type):
													$current_type = $ca->coa_type;
													?>
													<tr class="bg-gray-100/80 obn-coa-group-header"
														data-type="<?php echo esc_attr(sanitize_title($current_type ?: 'Uncategorized')); ?>">
														<td colspan="7"
															class="px-4 py-3 font-bold text-blue-700 uppercase tracking-wider border-b border-gray-200">
															<i class="fa-solid fa-folder-open mr-2"></i>
															<?php echo esc_html($current_type ?: 'Uncategorized'); ?>
														</td>
													</tr>
													<?php
												endif;
												?>
												<tr data-id="<?php echo esc_attr($ca->id); ?>" class="hover:bg-gray-50 obn-coa-row"
													data-type="<?php echo esc_attr(sanitize_title($ca->coa_type ?: 'Uncategorized')); ?>">
													<td class="px-4 py-3 text-gray-800 font-bold pl-8 border-l-4 border-blue-400">
														<?php echo esc_html($ca->account_code); ?>
													</td>
													<td class="px-4 py-3 text-gray-800 font-medium">
														<?php echo esc_html($ca->account_name); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($ca->coa_type); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo $ca->tax_name ? esc_html($ca->tax_name) : '<span class="text-gray-400 italic">None</span>'; ?>
													</td>
													<td
														class="px-4 py-3 text-right font-bold <?php echo $ca->balance >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>">
														<?php echo number_format(abs($ca->balance), 2) . ($ca->balance >= 0 ? ' DR' : ' CR'); ?>
													</td>
													<td class="px-4 py-3 text-center no-export">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="obn-toggle-coa-status sr-only peer"
																data-id="<?php echo esc_attr($ca->id); ?>"
																data-status="<?php echo esc_attr($ca->status); ?>"
																data-nonce="<?php echo esc_attr(wp_create_nonce('obn_accounts_action_nonce')); ?>"
																<?php echo ($ca->status == 1) ? 'checked' : ''; ?>>
															<div
																class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
															</div>
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-edit-coa px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($ca->id); ?>"
															data-type-id="<?php echo esc_attr($ca->coa_type_id); ?>"
															data-code="<?php echo esc_attr($ca->account_code); ?>"
															data-name="<?php echo esc_attr($ca->account_name); ?>"
															data-desc="<?php echo esc_attr($ca->description); ?>"
															data-tax-id="<?php echo esc_attr($ca->tax_id); ?>">Edit</button>
														<button
															class="button button-small button-danger obn-delete-coa px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($ca->id); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="6" class="px-4 py-8 text-center text-gray-500">No accounts found in
													Chart of Accounts.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-coa-add" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Account to CoA</h3>
							<form id="obn-coa-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-2xl">
								<input type="hidden" name="action" value="obn_insert_coa">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_accounts_action_nonce')); ?>">

								<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Account Type <span
												class="text-red-500">*</span></label>
										<select name="coa_type_id" class="w-full px-4 py-2 border rounded" required>
											<option value="">Select Account Type</option>
											<?php
											$coa_types = $wpdb->get_results("SELECT id, coa_type FROM $coa_types_table WHERE status = 1 ORDER BY coa_type ASC");
											foreach ($coa_types as $t)
												echo '<option value="' . $t->id . '">' . esc_html($t->coa_type) . '</option>';
											?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Account Code <span
												class="text-red-500">*</span></label>
										<input type="text" name="account_code" maxlength="10"
											class="w-full px-4 py-2 border rounded" placeholder="Unique code (max 10 chars)"
											required>
									</div>
								</div>

								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Account Name <span
											class="text-red-500">*</span></label>
									<input type="text" name="account_name" maxlength="150"
										class="w-full px-4 py-2 border rounded" placeholder="Account Title (max 150 chars)"
										required>
								</div>

								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Default Tax Setting</label>
									<select name="tax_id" class="w-full px-4 py-2 border rounded">
										<option value="">No Default Tax</option>
										<?php
										$all_taxes = $wpdb->get_results("SELECT id, tax_name, tax FROM $tax_table WHERE status = 1 ORDER BY tax_name ASC");
										foreach ($all_taxes as $tx)
											echo '<option value="' . $tx->id . '">' . esc_html($tx->tax_name) . ' (' . $tx->tax . '%)</option>';
										?>
									</select>
								</div>

								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Description (Optional)</label>
									<textarea name="description" class="w-full px-4 py-2 border rounded" rows="3"
										placeholder="How this account should be used..."></textarea>
								</div>

								<div class="flex gap-2">
									<button type="submit"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save Account</button>
									<button type="button"
										class="obn-coa-add-cancel bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<div id="obn-view-coa-edit" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit CoA Account</h3>
							<form id="obn-coa-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-2xl">
								<input type="hidden" name="action" value="obn_update_coa">
								<input type="hidden" name="security"
									value="<?php echo esc_attr(wp_create_nonce('obn_accounts_action_nonce')); ?>">
								<input type="hidden" id="obn_edit_coa_id" name="id" value="">

								<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Account Type <span
												class="text-red-500">*</span></label>
										<select id="obn_edit_coa_type_id" name="coa_type_id"
											class="w-full px-4 py-2 border rounded" required>
											<?php
											foreach ($coa_types as $t)
												echo '<option value="' . $t->id . '">' . esc_html($t->coa_type) . '</option>';
											?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Account Code <span
												class="text-red-500">*</span></label>
										<input type="text" id="obn_edit_coa_code" name="account_code" maxlength="10"
											class="w-full px-4 py-2 border rounded" required>
									</div>
								</div>

								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Account Name <span
											class="text-red-500">*</span></label>
									<input type="text" id="obn_edit_coa_name" name="account_name" maxlength="150"
										class="w-full px-4 py-2 border rounded" required>
								</div>

								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Default Tax Setting</label>
									<select id="obn_edit_coa_tax_id" name="tax_id" class="w-full px-4 py-2 border rounded">
										<option value="">No Default Tax</option>
										<?php
										foreach ($all_taxes as $tx)
											echo '<option value="' . $tx->id . '">' . esc_html($tx->tax_name) . ' (' . $tx->tax . '%)</option>';
										?>
									</select>
								</div>

								<div class="mb-4">
									<label class="block text-sm font-semibold text-gray-700 mb-2">Description (Optional)</label>
									<textarea id="obn_edit_coa_desc" name="description" class="w-full px-4 py-2 border rounded"
										rows="3"></textarea>
								</div>

								<div class="flex gap-2">
									<button type="submit"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update
										Account</button>
									<button type="button"
										class="obn-coa-edit-cancel bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<script>
						document.addEventListener('DOMContentLoaded', function () {
							// Links to switch views
							const viewLinks = document.querySelectorAll('.obn-dash-link');
							viewLinks.forEach(link => {
								link.addEventListener('click', function (e) {
									e.preventDefault();
									const target = this.getAttribute('data-target');
									if (target) {
										window.location.hash = 'view=' + target;
										localStorage.setItem('obn_active_view', target);
										// Hide all sections
										document.querySelectorAll('.obn-view-section').forEach(el => {
											el.style.display = 'none';
											el.classList.remove('active');
										});
										// Show target (construct ID like 'obn-view-quotation-list')
										const viewId = 'obn-view-' + target;
										const viewEl = document.getElementById(viewId);
										if (viewEl) {
											viewEl.style.display = 'block';
											viewEl.classList.add('active');
										} else {
											console.error('View not found: ' + viewId);
										}
									}
								});
							});

							// Charts
							const ctxSales = document.getElementById('obnMonthlySalesChart');
							if (ctxSales) {
								new Chart(ctxSales.getContext('2d'), {
									type: 'bar',
									data: {
										labels: <?php echo json_encode($monthly_sales['labels']); ?>,
										datasets: [{
											label: 'Sales',
											data: <?php echo json_encode($monthly_sales['data']); ?>,
											backgroundColor: '#4f46e5',
											borderRadius: 6,
											barThickness: 24
										}]
									},
									options: {
										responsive: true,
										maintainAspectRatio: false,
										plugins: { legend: { display: false } },
										scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
									}
								});
							}

							const ctxItems = document.getElementById('obnTopItemsChart');
							if (ctxItems) {
								new Chart(ctxItems.getContext('2d'), {
									type: 'doughnut',
									data: {
										labels: <?php echo json_encode($top_items['labels']); ?>,
										datasets: [{
											data: <?php echo json_encode($top_items['data']); ?>,
											backgroundColor: ['#1569B3', '#39B54A', '#2f9b3e', '#10548f', '#7fcf89'],
											borderWidth: 0
										}]
									},
									options: {
										responsive: true,
										maintainAspectRatio: false,
										plugins: { legend: { position: 'right' } }
									}
								});
							}
						});

						// Account Types (CoA) Logic
						jQuery(document).ready(function ($) {
							// Auto-switch to view if stored in localStorage
							const storedView = localStorage.getItem('obn_active_view');
							if (storedView) {
								$('.obn-view-section').removeClass('active').hide();
								$('#obn-view-' + storedView).show().addClass('active');
								localStorage.removeItem('obn_active_view');

								// Also update sidebar active state if possible
								$('.obn-nav-link, .obn-subnav-link').removeClass('active');
								$(`.obn-subnav-link[data-target="${storedView}"]`).addClass('active').closest('.obn-submenu').show().prev('.obn-nav-link').addClass('active');
							}

							// Show Add View
							$(document).on('click', '#obn-show-coa-type-add', function () {
								$('.obn-view-section').removeClass('active').hide();
								$('#obn-view-coa-types-add').fadeIn(300).addClass('active');
							});

							// Cancel Add/Edit
							$(document).on('click', '.obn-coa-type-add-cancel, .obn-coa-type-edit-cancel', function () {
								$('.obn-view-section').removeClass('active').hide();
								$('#obn-view-coa-types-list').fadeIn(300).addClass('active');
							});

							// Handle Add Submit
							$('#obn-coa-type-add-form').on('submit', function (e) {
								e.preventDefault();
								const form = $(this);
								const btn = form.find('button[type="submit"]');
								const data = form.serialize();

								btn.prop('disabled', true).text('Saving...');
								$.post(obn_ajax.ajax_url, data, function (res) {
									btn.prop('disabled', false).text('Save');
									if (res.success) {
										alert(res.data.message);
										localStorage.setItem('obn_active_view', 'coa-types-list');
										location.reload();
									} else {
										alert(res.data);
									}
								});
							});

							// Show Edit View
							$(document).on('click', '.obn-edit-coa-type', function () {
								const id = $(this).data('id');
								const name = $(this).data('name');
								const desc = $(this).data('desc');

								$('#obn_edit_coa_type_id').val(id);
								$('#obn_edit_coa_type_name').val(name);
								$('#obn_edit_coa_type_desc').val(desc);

								$('.obn-view-section').removeClass('active').hide();
								$('#obn-view-coa-types-edit').fadeIn(300).addClass('active');
							});

							// Handle Edit Submit
							$('#obn-coa-type-edit-form').on('submit', function (e) {
								e.preventDefault();
								const form = $(this);
								const btn = form.find('button[type="submit"]');
								const data = form.serialize();

								btn.prop('disabled', true).text('Updating...');
								$.post(obn_ajax.ajax_url, data, function (res) {
									btn.prop('disabled', false).text('Update');
									if (res.success) {
										alert(res.data.message);
										localStorage.setItem('obn_active_view', 'coa-types-list');
										location.reload();
									} else {
										alert(res.data);
									}
								});
							});

							// Handle Delete
							$(document).on('click', '.obn-delete-coa-type', function () {
								if (!confirm('Are you sure you want to delete this account type?')) return;

								const id = $(this).data('id');
								const nonce = $('#obn-coa-type-add-form input[name="security"]').val();

								$.post(obn_ajax.ajax_url, {
									action: 'obn_delete_coa_type',
									id: id,
									security: nonce
								}, function (res) {
									if (res.success) {
										localStorage.setItem('obn_active_view', 'coa-types-list');
										location.reload();
									} else {
										alert(res.data);
									}
								});
							});

							// Handle Status Toggle
							$(document).on('change', '.obn-toggle-coa-type-status', function () {
								const id = $(this).data('id');
								const status = this.checked ? 1 : 0;
								const nonce = $(this).data('nonce');

								$.post(obn_ajax.ajax_url, {
									action: 'obn_toggle_coa_type_status',
									id: id,
									status: status,
									security: nonce
								}, function (res) {
									if (!res.success) alert('Failed to update status.');
								});
							});

							// Search Functionality
							$('#obn-coa-types-search').on('keyup', function () {
								const value = $(this).val().toLowerCase();
								$('#obn-coa-types-tbody tr').filter(function () {
									$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
								});
							});

							// --- Chart of Accounts (CoA List) Logic ---

							// CoA Tab Filtering
							$(document).on('click', '.obn-coa-tab-btn', function () {
								$('.obn-coa-tab-btn').removeClass('active bg-blue-50 text-blue-700 border-blue-600').addClass('text-gray-600 border-transparent hover:border-blue-300');
								$(this).removeClass('text-gray-600 border-transparent hover:border-blue-300').addClass('active bg-blue-50 text-blue-700 border-blue-600');

								const filter = $(this).data('filter');

								if (filter === 'all') {
									$('.obn-coa-group-header, .obn-coa-row').show();
								} else {
									$('.obn-coa-group-header, .obn-coa-row').hide();
									$('.obn-coa-group-header[data-type="' + filter + '"]').show();
									$('.obn-coa-row[data-type="' + filter + '"]').show();
								}
							});

							// Show CoA Add View
							$(document).on('click', '#obn-show-coa-add', function () {
								$('.obn-view-section').removeClass('active').hide();
								$('#obn-view-coa-add').fadeIn(300).addClass('active');
							});

							// Cancel CoA Add/Edit
							$(document).on('click', '.obn-coa-add-cancel, .obn-coa-edit-cancel', function () {
								$('.obn-view-section').removeClass('active').hide();
								$('#obn-view-coa-list').fadeIn(300).addClass('active');
							});

							// Handle CoA Add Submit
							$('#obn-coa-add-form').on('submit', function (e) {
								e.preventDefault();
								const form = $(this);
								const btn = form.find('button[type="submit"]');
								const data = form.serialize();

								btn.prop('disabled', true).text('Saving...');
								$.post(obn_ajax.ajax_url, data, function (res) {
									btn.prop('disabled', false).text('Save Account');
									if (res.success) {
										alert(res.data.message);
										localStorage.setItem('obn_active_view', 'coa-list');
										location.reload();
									} else {
										alert(res.data);
									}
								});
							});

							// Show CoA Edit View
							$(document).on('click', '.obn-edit-coa', function () {
								const id = $(this).data('id');
								const type_id = $(this).data('type-id');
								const code = $(this).data('code');
								const name = $(this).data('name');
								const desc = $(this).data('desc');
								const tax_id = $(this).data('tax-id');

								$('#obn_edit_coa_id').val(id);
								$('#obn_edit_coa_type_id').val(type_id);
								$('#obn_edit_coa_code').val(code);
								$('#obn_edit_coa_name').val(name);
								$('#obn_edit_coa_desc').val(desc);
								$('#obn_edit_coa_tax_id').val(tax_id);

								$('.obn-view-section').removeClass('active').hide();
								$('#obn-view-coa-edit').fadeIn(300).addClass('active');
							});

							// Handle CoA Edit Submit
							$('#obn-coa-edit-form').on('submit', function (e) {
								e.preventDefault();
								const form = $(this);
								const btn = form.find('button[type="submit"]');
								const data = form.serialize();

								btn.prop('disabled', true).text('Updating...');
								$.post(obn_ajax.ajax_url, data, function (res) {
									btn.prop('disabled', false).text('Update Account');
									if (res.success) {
										alert(res.data.message);
										localStorage.setItem('obn_active_view', 'coa-list');
										location.reload();
									} else {
										alert(res.data);
									}
								});
							});

							// Handle CoA Delete
							$(document).on('click', '.obn-delete-coa', function () {
								if (!confirm('Are you sure you want to delete this account?')) return;

								const id = $(this).data('id');
								const nonce = $('#obn-coa-add-form input[name="security"]').val();

								$.post(obn_ajax.ajax_url, {
									action: 'obn_delete_coa',
									id: id,
									security: nonce
								}, function (res) {
									if (res.success) {
										localStorage.setItem('obn_active_view', 'coa-list');
										location.reload();
									} else {
										alert(res.data);
									}
								});
							});

							// Handle CoA Status Toggle
							$(document).on('change', '.obn-toggle-coa-status', function () {
								const id = $(this).data('id');
								const status = this.checked ? 1 : 0;
								const nonce = $(this).data('nonce');

								$.post(obn_ajax.ajax_url, {
									action: 'obn_toggle_coa_status',
									id: id,
									status: status,
									security: nonce
								}, function (res) {
									if (!res.success) alert('Failed to update status.');
								});
							});

							// CoA Search Functionality
							$('#obn-coa-search').on('keyup', function () {
								const value = $(this).val().toLowerCase();
								$('#obn-coa-tbody tr').filter(function () {
									$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
								});
							});
						});
					</script>
					<!--COA section end here-->

					<!--Deposite section start here-->
					<!-- Deposits Section (List / Add / Edit views) -->
					<div id="obn-view-accounts-deposit" class="obn-view-section">
						<?php global $wpdb;
						$dep_table = $wpdb->prefix . 'orabooks_ac_moneydeposits';
						$acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
						$deposits = $wpdb->get_results("SELECT d.*, da.account_name AS debit_account_name, ca.account_name AS credit_account_name FROM {$dep_table} AS d LEFT JOIN {$acc_table} AS da ON d.debit_account_id = da.id LEFT JOIN {$acc_table} AS ca ON d.credit_account_id = ca.id ORDER BY d.id DESC");
						$dep_nonce = wp_create_nonce('obn_deposit_action_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-4">
								<h3 class="text-2xl font-bold text-gray-800">Deposits</h3>
								<button id="obn-show-deposit-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add Deposit</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-deposits-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search deposits...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="obn-print-btn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-deposits-table" data-title="Deposits List" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="obn-pdf-btn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-deposits-table" data-title="Deposits List" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="obn-excel-btn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-deposits-table" data-title="Deposits_List" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="obn-csv-btn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-deposits-table" data-title="Deposits_List" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last) -->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$dep_cols = ['ID', 'Date', 'Debit Account', 'Credit Account', 'Amount', 'Note'];
												foreach ($dep_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
															data-column="<?php echo $idx; ?>" data-table="#obn-deposits-table">
														<span
															class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-deposits-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Debit Account</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Credit Account</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Amount</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Note</th>
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-200">
										<?php if ($deposits):
											foreach ($deposits as $d): ?>
												<tr data-id="<?php echo esc_attr($d->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($d->id); ?></td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($d->deposit_date); ?></td>
													<td class="px-4 py-3 text-gray-800">
														<?php echo esc_html($d->debit_account_name ?: ''); ?>
													</td>
													<td class="px-4 py-3 text-gray-800">
														<?php echo esc_html($d->credit_account_name ?: ''); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html(number_format_i18n($d->amount, 2)); ?>
													</td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($d->note); ?></td>
													<td class="px-4 py-3 text-center no-export">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="obn-toggle-deposit-status sr-only peer"
																data-id="<?php echo esc_attr($d->id); ?>"
																data-status="<?php echo esc_attr($d->status); ?>"
																data-nonce="<?php echo esc_attr($dep_nonce); ?>" <?php echo ($d->status == 1) ? 'checked' : ''; ?>>
															<div
																class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
															</div>
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-edit-deposit px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($d->id); ?>"
															data-nonce="<?php echo esc_attr($dep_nonce); ?>">Edit</button>
														<button
															class="button button-small button-danger obn-delete-deposit px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($d->id); ?>"
															data-nonce="<?php echo esc_attr($dep_nonce); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="9" class="px-4 py-8 text-center text-gray-500">No deposits found.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-accounts-deposit-add" class="obn-view-section">
						<?php $accounts = $wpdb->get_results("SELECT id, account_name FROM {$acc_table} ORDER BY account_name ASC"); ?>
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Deposit</h3>
							<form id="obn-deposit-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_insert_deposit">
								<input type="hidden" name="security" value="<?php echo esc_attr($dep_nonce); ?>">
								<div class="grid grid-cols-2 gap-6">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
										<input type="date" name="deposit_date" class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Reference No</label>
										<input type="text" name="reference_no" class="w-full px-4 py-2 border rounded">
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Debit Account</label>
										<select name="debit_ac" class="w-full px-4 py-2 border rounded" required>
											<option value="">Select Debit Account</option>
											<?php foreach ($accounts as $a): ?>
												<option value="<?php echo esc_attr($a->id); ?>">
													<?php echo esc_html($a->account_name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Credit Account</label>
										<select name="credit_ac" class="w-full px-4 py-2 border rounded" required>
											<option value="">Select Credit Account</option>
											<?php foreach ($accounts as $a): ?>
												<option value="<?php echo esc_attr($a->id); ?>">
													<?php echo esc_html($a->account_name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Amount</label>
										<input type="number" step="0.01" name="amount" class="w-full px-4 py-2 border rounded"
											required>
									</div>
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Note</label>
										<textarea name="note" class="w-full px-4 py-2 border rounded" rows="3"></textarea>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-deposit-add-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Add Deposit</button>
									<button type="button" id="obn-deposit-add-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>

					<div id="obn-view-accounts-deposit-edit" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Deposit</h3>
							<form id="obn-deposit-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_update_deposit">
								<input type="hidden" name="security" value="<?php echo esc_attr($dep_nonce); ?>">
								<input type="hidden" id="obn_edit_deposit_id" name="id" value="">
								<div class="grid grid-cols-2 gap-6">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
										<input type="date" id="obn_edit_deposit_date" name="deposit_date"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Reference No</label>
										<input type="text" id="obn_edit_reference_no" name="reference_no"
											class="w-full px-4 py-2 border rounded">
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Debit Account</label>
										<select id="obn_edit_debit_ac" name="debit_ac" class="w-full px-4 py-2 border rounded"
											required>
											<option value=""> Select Debit Account </option>
											<?php foreach ($accounts as $a): ?>
												<option value="<?php echo esc_attr($a->id); ?>">
													<?php echo esc_html($a->account_name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Credit Account</label>
										<select id="obn_edit_credit_ac" name="credit_ac" class="w-full px-4 py-2 border rounded"
											required>
											<option value=""> Select Credit Account </option>
											<?php foreach ($accounts as $a): ?>
												<option value="<?php echo esc_attr($a->id); ?>">
													<?php echo esc_html($a->account_name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Amount</label>
										<input type="number" step="0.01" id="obn_edit_deposit_amount" name="amount"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Note</label>
										<textarea id="obn_edit_deposit_note" name="note" class="w-full px-4 py-2 border rounded"
											rows="3"></textarea>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-deposit-edit-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update
										Deposit</button>
									<button type="button" id="obn-deposit-edit-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>
					<!--Deposite section end here-->

					<!-- Money Transfer Section -->
					<?php include_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/money-transfer/view-money-transfer.php'; ?>
					<?php include_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/money-transfer/add-money-transfer.php'; ?>
					<?php include_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/money-transfer/edit-money-transfer.php'; ?>
					<!-- Money Transfer Section End Here-->

					<!-- Cash transactions Report start here -->
					<?php include_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/reports/cash-transactions.php'; ?>
					<!-- Cash transactions Report end here -->

					<!-- Fiscal Year Section (List / Add / Edit views) -->
					<div id="obn-view-fiscal-year-list" class="obn-view-section" style="display:none;">
						<?php global $wpdb;
						$fy_table = $wpdb->prefix . 'orabooks_ac_fiscal_years';
						$fiscal_years = [];
						if ($wpdb->get_var("SHOW TABLES LIKE '{$fy_table}'") == $fy_table) {
							$fiscal_years = $wpdb->get_results("SELECT * FROM {$fy_table} ORDER BY id DESC");
						}
						$fy_nonce = wp_create_nonce('obn_fiscal_year_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-4">
								<h3 class="text-2xl font-bold text-gray-800">Fiscal Years</h3>
								<button id="obn-show-fiscal-year-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add Fiscal
									Year</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-fiscal-year-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search fiscal years...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-fiscal-year-table" data-title="Fiscal Years List" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-fiscal-year-table" data-title="Fiscal Years List" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-fiscal-year-table" data-title="Fiscal_Years_List" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-fiscal-year-table" data-title="Fiscal_Years_List" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$fy_cols = ['ID', 'Name', 'Start Date', 'End Date', 'Description'];
												foreach ($fy_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
															data-column="<?php echo $idx; ?>" data-table="#obn-fiscal-year-table">
														<span class="ml-3 text-sm text-gray-700 font-bold uppercase">
															<?php echo $name; ?>
														</span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-fiscal-year-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Start Date</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">End Date</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-200">
										<?php if ($fiscal_years):
											foreach ($fiscal_years as $fy): ?>
												<tr data-id="<?php echo esc_attr($fy->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($fy->id); ?>
													</td>
													<td class="px-4 py-3 text-gray-800 font-medium">
														<?php echo esc_html($fy->fiscal_year_name); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($fy->start_date); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($fy->end_date); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($fy->description); ?>
													</td>
													<td class="px-4 py-3 text-center no-export">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="obn-toggle-fiscal-year-status sr-only peer"
																data-id="<?php echo esc_attr($fy->id); ?>"
																data-status="<?php echo esc_attr($fy->status); ?>"
																data-nonce="<?php echo esc_attr($fy_nonce); ?>" <?php echo ($fy->status == 1) ? 'checked' : ''; ?>>
															<div
																class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
															</div>
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-edit-fiscal-year px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($fy->id); ?>"
															data-nonce="<?php echo esc_attr($fy_nonce); ?>">Edit</button>
														<button
															class="button button-small button-danger obn-delete-fiscal-year px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($fy->id); ?>"
															data-nonce="<?php echo esc_attr($fy_nonce); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="7" class="px-4 py-8 text-center text-gray-500">No fiscal years found.
												</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-fiscal-year-add" class="obn-view-section" style="display:none;">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Fiscal Year</h3>
							<form id="obn-fiscal-year-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_insert_fiscal_year">
								<input type="hidden" name="security" value="<?php echo esc_attr($fy_nonce); ?>">
								<div class="grid grid-cols-2 gap-6">
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Fiscal Year Name <span
												class="text-red-500">*</span></label>
										<input type="text" name="fiscal_year_name" class="w-full px-4 py-2 border rounded"
											placeholder="e.g. FY 2026-2027" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Start Date <span
												class="text-red-500">*</span></label>
										<input type="date" name="start_date" class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">End Date <span
												class="text-red-500">*</span></label>
										<input type="date" name="end_date" class="w-full px-4 py-2 border rounded" required>
									</div>
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
										<textarea name="description" class="w-full px-4 py-2 border rounded"
											rows="3"></textarea>
									</div>
									<div class="col-span-2">
										<label class="flex items-center cursor-pointer">
											<div class="relative">
												<input type="checkbox" name="status" value="1" class="sr-only" checked>
												<div class="block bg-gray-300 w-10 h-6 rounded-full transition toggle-bg"></div>
												<div
													class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition transform">
												</div>
											</div>
											<div class="ml-3 text-gray-700 font-medium">Active Status</div>
										</label>
										<style>
											input:checked~.toggle-bg {
												background-color: #2563EB;
											}

											input:checked~.dot {
												transform: translateX(100%);
											}
										</style>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-fiscal-year-add-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Add Fiscal
										Year</button>
									<button type="button" id="obn-fiscal-year-add-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>

					<div id="obn-view-fiscal-year-edit" class="obn-view-section" style="display:none;">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Fiscal Year</h3>
							<form id="obn-fiscal-year-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_update_fiscal_year">
								<input type="hidden" name="security" value="<?php echo esc_attr($fy_nonce); ?>">
								<input type="hidden" id="obn_edit_fiscal_year_id" name="id" value="">
								<div class="grid grid-cols-2 gap-6">
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Fiscal Year Name <span
												class="text-red-500">*</span></label>
										<input type="text" id="obn_edit_fiscal_year_name" name="fiscal_year_name"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Start Date <span
												class="text-red-500">*</span></label>
										<input type="date" id="obn_edit_start_date" name="start_date"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">End Date <span
												class="text-red-500">*</span></label>
										<input type="date" id="obn_edit_end_date" name="end_date"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
										<textarea id="obn_edit_fy_description" name="description"
											class="w-full px-4 py-2 border rounded" rows="3"></textarea>
									</div>
									<div class="col-span-2">
										<label class="flex items-center cursor-pointer">
											<div class="relative">
												<input type="checkbox" id="obn_edit_fy_status" name="status" value="1"
													class="sr-only">
												<div class="block bg-gray-300 w-10 h-6 rounded-full transition toggle-bg"></div>
												<div
													class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition transform">
												</div>
											</div>
											<div class="ml-3 text-gray-700 font-medium">Active Status</div>
										</label>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-fiscal-year-edit-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update Fiscal
										Year</button>
									<button type="button" id="obn-fiscal-year-edit-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>
					<div id="obn-view-opening-balance-input" class="obn-view-section">
						<?php
						$ob_nonce = wp_create_nonce('obn_opening_balance_nonce');
						global $wpdb;

						// Fetch CoA grouped by type
						$coa_type_list = $wpdb->get_results("SELECT id, coa_type FROM {$wpdb->prefix}orabooks_ac_coa_types WHERE status = 1 ORDER BY id ASC");
						$accounts_by_type = [];
						foreach ($coa_type_list as $type) {
							$accounts_by_type[$type->coa_type] = $wpdb->get_results($wpdb->prepare(
								"SELECT * FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE coa_type_id = %d AND status = 1",
								$type->id
							));
						}

						// Fetch Customers
						$customers_list = $wpdb->get_results("SELECT id, customer_name, customer_code FROM {$wpdb->prefix}orabooks_db_customers WHERE status = 1");

						// Fetch Suppliers
						$suppliers_list = $wpdb->get_results("SELECT id, supplier_name, supplier_code FROM {$wpdb->prefix}orabooks_db_suppliers WHERE status = 1");

						// Fetch Inventory
						$items_list = $wpdb->get_results("SELECT id, item_name, item_code, purchase_price FROM {$wpdb->prefix}orabooks_db_items WHERE status = 1");

						// Fetch existing opening balances
						$existing_obs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}orabooks_ac_opening_balances WHERE store_id = 1");
						$coa_obs = [];
						$cust_obs = [];
						$supp_obs = [];
						$entry_date_val = date('Y-m-d');

						if ($existing_obs) {
							$entry_date_val = $existing_obs[0]->entry_date;
							foreach ($existing_obs as $ob) {
								if ($ob->account_type == 'COA') {
									$coa_obs[$ob->account_id] = $ob;
								} else if ($ob->account_type == 'AR') {
									$cust_obs[$ob->party_id] = $ob;
								} else if ($ob->account_type == 'AP') {
									$supp_obs[$ob->party_id] = $ob;
								}
							}
						}

						$existing_inv = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}orabooks_ac_inventory_opening WHERE store_id = 1");
						$inv_obs = [];
						if ($existing_inv) {
							foreach ($existing_inv as $inv) {
								$inv_obs[$inv->item_id] = $inv;
							}
						}
						?>
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-6">Opening Balance Entry</h3>

							<form id="obn-opening-balance-form">
								<input type="hidden" name="action" value="obn_save_opening_balances">
								<input type="hidden" name="security" value="<?php echo esc_attr($ob_nonce); ?>">

								<div
									class="mb-6 flex flex-col md:flex-row items-center justify-between bg-blue-50 p-6 rounded-xl border border-blue-100 shadow-sm gap-4">
									<div class="flex items-center gap-4">
										<label class="font-bold text-blue-900">Opening Date:</label>
										<input type="date" name="entry_date"
											class="border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 shadow-sm"
											value="<?php echo esc_attr($entry_date_val); ?>">
									</div>
									<div class="flex flex-wrap items-center gap-8">
										<div class="text-right">
											<span class="text-xs text-gray-500 uppercase font-black tracking-wider">Total
												Debits</span>
											<div id="ob-summary-debit" class="text-3xl font-black text-emerald-600">0.00</div>
										</div>
										<div class="text-right">
											<span class="text-xs text-gray-500 uppercase font-black tracking-wider">Total
												Credits</span>
											<div id="ob-summary-credit" class="text-3xl font-black text-indigo-600">0.00</div>
										</div>
										<div class="text-right border-l border-blue-200 pl-8">
											<span
												class="text-xs text-gray-500 uppercase font-black tracking-wider">Difference</span>
											<div id="ob-summary-diff" class="text-3xl font-black text-rose-600">0.00</div>
										</div>
									</div>
								</div>

								<!-- Tab Navigation -->
								<div
									class="mb-8 border-b border-gray-200 bg-white p-2 rounded-t-xl sticky top-0 z-10 shadow-sm overflow-x-auto whitespace-nowrap">
									<ul class="flex font-semibold text-center" id="ob-tabs" role="tablist">
										<li class="mr-4">
											<button
												class="inline-block px-6 py-3 border-b-4 rounded-t-lg active-tab bg-blue-50 text-blue-700 border-blue-600 transition-all font-bold"
												id="accounts-tab" data-target="#ob-accounts" type="button">1. Chart of
												Accounts</button>
										</li>
										<li class="mr-4">
											<button
												class="inline-block px-6 py-3 border-b-4 border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-300 transition-all font-bold"
												id="customers-tab" data-target="#ob-customers" type="button">2. Customers
												(AR)</button>
										</li>
										<li class="mr-4">
											<button
												class="inline-block px-6 py-3 border-b-4 border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-300 transition-all font-bold"
												id="suppliers-tab" data-target="#ob-suppliers" type="button">3. Suppliers
												(AP)</button>
										</li>
										<li>
											<button
												class="inline-block px-6 py-3 border-b-4 border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-300 transition-all font-bold"
												id="inventory-tab" data-target="#ob-inventory" type="button">4.
												Inventory</button>
										</li>
									</ul>
								</div>

								<style>
									.ob-input:focus,
									.ob-inv-qty:focus,
									.ob-inv-cost:focus {
										outline: none;
										box-shadow: 0 0 0 3px rgba(21, 105, 179, 0.2);
										border-color: #1569B3;
									}

									.ob-row:hover {
										background-color: #f8fafc;
										transition: all 0.2s;
									}

									.ob-tab-pane {
										animation: fadeIn 0.3s ease-in-out;
									}

									@keyframes fadeIn {
										from {
											opacity: 0;
											transform: translateY(5px);
										}

										to {
											opacity: 1;
											transform: translateY(0);
										}
									}
								</style>

								<!-- Tab Contents -->
								<div id="ob-tab-content" class="bg-white rounded-xl">
									<!-- Accounts Tab -->
									<div class="ob-tab-pane p-4" id="ob-accounts">
										<?php if (empty($accounts_by_type)): ?>
											<div class="p-8 text-center text-gray-500 bg-gray-50 rounded-lg">No accounts found in
												CoA. Please create accounts first.</div>
										<?php else: ?>
											<?php foreach ($accounts_by_type as $type_name => $accs):
												if (empty($accs))
													continue; ?>
												<div class="mb-10 last:mb-0">
													<h4
														class="text-lg font-black text-slate-700 bg-slate-50 border-l-4 border-blue-500 px-5 py-3 rounded-r-lg mb-4 flex items-center shadow-sm">
														<i class="fa-solid fa-folder-tree mr-3 text-blue-500"></i>
														<?php echo esc_html($type_name); ?>
													</h4>
													<div class="overflow-x-auto rounded-xl border border-slate-100 shadow-sm">
														<table class="w-full text-sm text-left">
															<thead
																class="text-xs text-slate-500 uppercase bg-slate-50 font-black tracking-wider">
																<tr>
																	<th class="px-6 py-4 w-1/3">Account Name</th>
																	<th class="px-6 py-4">Account Code</th>
																	<th class="px-6 py-4 text-right">Debit</th>
																	<th class="px-6 py-4 text-right">Credit</th>
																</tr>
															</thead>
															<tbody class="divide-y divide-slate-100">
																<?php foreach ($accs as $acc):
																	$is_debit_nature = in_array($type_name, ['Assets', 'Expenses']);
																	?>
																	<tr class="ob-row group"
																		data-type="<?php echo esc_attr($type_name); ?>">
																		<td class="px-6 py-4 font-bold text-slate-800">
																			<?php echo esc_html($acc->account_name); ?>
																		</td>
																		<td class="px-6 py-4 text-slate-400 font-medium">
																			<?php echo esc_html($acc->account_code); ?>
																		</td>
																		<td class="px-6 py-4">
																			<input type="number" step="0.01"
																				name="coa_balances[<?php echo $acc->id; ?>][debit]"
																				class="ob-input ob-debit w-full px-4 py-2 border border-slate-200 rounded-lg text-right font-black transition-all <?php echo ($is_debit_nature) ? 'bg-emerald-50 text-emerald-700 placeholder-emerald-300' : 'bg-white text-slate-700'; ?>"
																				value="<?php echo isset($coa_obs[$acc->id]) && $coa_obs[$acc->id]->debit > 0 ? esc_attr($coa_obs[$acc->id]->debit) : ''; ?>"
																				placeholder="0.00">
																		</td>
																		<td class="px-6 py-4">
																			<input type="number" step="0.01"
																				name="coa_balances[<?php echo $acc->id; ?>][credit]"
																				class="ob-input ob-credit w-full px-4 py-2 border border-slate-200 rounded-lg text-right font-black transition-all <?php echo (!$is_debit_nature) ? 'bg-indigo-50 text-indigo-700 placeholder-indigo-300' : 'bg-white text-slate-700'; ?>"
																				value="<?php echo isset($coa_obs[$acc->id]) && $coa_obs[$acc->id]->credit > 0 ? esc_attr($coa_obs[$acc->id]->credit) : ''; ?>"
																				placeholder="0.00">
																		</td>
																	</tr>
																<?php endforeach; ?>
															</tbody>
														</table>
													</div>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>

									<!-- Customers Tab -->
									<div class="ob-tab-pane hidden p-4" id="ob-customers">
										<div
											class="bg-blue-50 p-5 rounded-xl mb-8 border border-blue-100 flex items-center shadow-inner">
											<div
												class="w-12 h-12 bg-blue-500 text-white rounded-full flex items-center justify-center mr-5 flex-shrink-0 shadow-lg">
												<i class="fa-solid fa-users text-xl"></i>
											</div>
											<div>
												<h5 class="font-black text-blue-900 mb-1">Accounts Receivable (AR)</h5>
												<p class="text-sm text-blue-700 font-medium">Enter outstanding balances for your
													customers. These total values will be linked to the master AR account.</p>
											</div>
										</div>
										<div class="overflow-x-auto rounded-xl border border-slate-100 shadow-sm">
											<table class="w-full text-sm text-left">
												<thead
													class="text-xs text-slate-500 uppercase bg-slate-50 font-black tracking-wider">
													<tr>
														<th class="px-6 py-4 w-1/3">Customer Name</th>
														<th class="px-6 py-4">Customer Code</th>
														<th class="px-6 py-4 text-right">Debit (Owed to you)</th>
														<th class="px-6 py-4 text-right">Credit (Advances)</th>
													</tr>
												</thead>
												<tbody class="divide-y divide-slate-100">
													<?php if (empty($customers_list)): ?>
														<tr>
															<td colspan="4"
																class="px-6 py-10 text-center text-slate-400 font-bold italic">No
																customers found.</td>
														</tr>
													<?php else: ?>
														<?php foreach ($customers_list as $cust): ?>
															<tr class="ob-row group">
																<td class="px-6 py-4 font-bold text-slate-800">
																	<?php echo esc_html($cust->customer_name); ?>
																</td>
																<td class="px-6 py-4 text-slate-400 font-medium">
																	<?php echo esc_html($cust->customer_code); ?>
																</td>
																<td class="px-6 py-4"><input type="number" step="0.01"
																		name="customer_balances[<?php echo $cust->id; ?>][debit]"
																		class="ob-input ob-debit w-full px-4 py-2 border border-slate-200 rounded-lg text-right font-black bg-emerald-50 text-emerald-700 transition-all"
																		value="<?php echo isset($cust_obs[$cust->id]) && $cust_obs[$cust->id]->debit > 0 ? esc_attr($cust_obs[$cust->id]->debit) : ''; ?>"
																		placeholder="0.00"></td>
																<td class="px-6 py-4"><input type="number" step="0.01"
																		name="customer_balances[<?php echo $cust->id; ?>][credit]"
																		class="ob-input ob-credit w-full px-4 py-2 border border-slate-200 rounded-lg text-right font-black bg-indigo-50 text-indigo-700 transition-all"
																		value="<?php echo isset($cust_obs[$cust->id]) && $cust_obs[$cust->id]->credit > 0 ? esc_attr($cust_obs[$cust->id]->credit) : ''; ?>"
																		placeholder="0.00"></td>
															</tr>
														<?php endforeach; ?>
													<?php endif; ?>
												</tbody>
											</table>
										</div>
									</div>

									<!-- Suppliers Tab -->
									<div class="ob-tab-pane hidden p-4" id="ob-suppliers">
										<div
											class="bg-orange-50 p-5 rounded-xl mb-8 border border-orange-100 flex items-center shadow-inner">
											<div
												class="w-12 h-12 bg-orange-500 text-white rounded-full flex items-center justify-center mr-5 flex-shrink-0 shadow-lg">
												<i class="fa-solid fa-truck-field text-xl"></i>
											</div>
											<div>
												<h5 class="font-black text-orange-900 mb-1">Accounts Payable (AP)</h5>
												<p class="text-sm text-orange-700 font-medium">Enter outstanding balances for
													your suppliers. These total values will be linked to the master AP account.
												</p>
											</div>
										</div>
										<div class="overflow-x-auto rounded-xl border border-slate-100 shadow-sm">
											<table class="w-full text-sm text-left">
												<thead
													class="text-xs text-slate-500 uppercase bg-slate-50 font-black tracking-wider">
													<tr>
														<th class="px-6 py-4 w-1/3">Supplier Name</th>
														<th class="px-6 py-4">Supplier Code</th>
														<th class="px-6 py-4 text-right">Debit (Prepayments)</th>
														<th class="px-6 py-4 text-right">Credit (Owed by you)</th>
													</tr>
												</thead>
												<tbody class="divide-y divide-slate-100">
													<?php if (empty($suppliers_list)): ?>
														<tr>
															<td colspan="4"
																class="px-6 py-10 text-center text-slate-400 font-bold italic">No
																suppliers found.</td>
														</tr>
													<?php else: ?>
														<?php foreach ($suppliers_list as $supp): ?>
															<tr class="ob-row group">
																<td class="px-6 py-4 font-bold text-slate-800">
																	<?php echo esc_html($supp->supplier_name); ?>
																</td>
																<td class="px-6 py-4 text-slate-400 font-medium">
																	<?php echo esc_html($supp->supplier_code); ?>
																</td>
																<td class="px-6 py-4"><input type="number" step="0.01"
																		name="supplier_balances[<?php echo $supp->id; ?>][debit]"
																		class="ob-input ob-debit w-full px-4 py-2 border border-slate-200 rounded-lg text-right font-black bg-emerald-50 text-emerald-700 transition-all"
																		value="<?php echo isset($supp_obs[$supp->id]) && $supp_obs[$supp->id]->debit > 0 ? esc_attr($supp_obs[$supp->id]->debit) : ''; ?>"
																		placeholder="0.00"></td>
																<td class="px-6 py-4"><input type="number" step="0.01"
																		name="supplier_balances[<?php echo $supp->id; ?>][credit]"
																		class="ob-input ob-credit w-full px-4 py-2 border border-slate-200 rounded-lg text-right font-black bg-indigo-50 text-indigo-700 transition-all"
																		value="<?php echo isset($supp_obs[$supp->id]) && $supp_obs[$supp->id]->credit > 0 ? esc_attr($supp_obs[$supp->id]->credit) : ''; ?>"
																		placeholder="0.00"></td>
															</tr>
														<?php endforeach; ?>
													<?php endif; ?>
												</tbody>
											</table>
										</div>
									</div>

									<!-- Inventory Tab -->
									<div class="ob-tab-pane hidden p-4" id="ob-inventory">
										<div
											class="bg-indigo-50 p-5 rounded-xl mb-8 border border-indigo-100 flex items-center shadow-inner">
											<div
												class="w-12 h-12 bg-indigo-600 text-white rounded-full flex items-center justify-center mr-5 flex-shrink-0 shadow-lg">
												<i class="fa-solid fa-boxes-stacked text-xl"></i>
											</div>
											<div>
												<h5 class="font-black text-indigo-900 mb-1">Starting Inventory Opening</h5>
												<p class="text-sm text-indigo-700 font-medium">Set your starting stock levels.
													Total values will be debited to the master Inventory asset account.</p>
											</div>
										</div>
										<div class="overflow-x-auto rounded-xl border border-slate-100 shadow-sm">
											<table class="w-full text-sm text-left">
												<thead
													class="text-xs text-slate-500 uppercase bg-slate-50 font-black tracking-wider">
													<tr>
														<th class="px-6 py-4 w-1/3">Item Name</th>
														<th class="px-6 py-4">Item Code</th>
														<th class="px-6 py-4 text-right">Quantity</th>
														<th class="px-6 py-4 text-right">Unit Cost</th>
														<th class="px-6 py-4 text-right">Total Value</th>
													</tr>
												</thead>
												<tbody class="divide-y divide-slate-100">
													<?php if (empty($items_list)): ?>
														<tr>
															<td colspan="5"
																class="px-6 py-10 text-center text-slate-400 font-bold italic">No
																inventory items found.</td>
														</tr>
													<?php else: ?>
														<?php foreach ($items_list as $item): ?>
															<tr class="ob-inv-row group">
																<td class="px-6 py-4 font-bold text-slate-800">
																	<?php echo esc_html($item->item_name); ?>
																</td>
																<td class="px-6 py-4 text-slate-400 font-medium">
																	<?php echo esc_html($item->item_code); ?>
																</td>
																<td class="px-6 py-4">
																	<input type="number" step="0.01"
																		name="inventory_items[<?php echo $item->id; ?>][qty]"
																		class="ob-inv-qty w-full px-4 py-2 border border-slate-200 rounded-lg text-right font-black bg-white focus:bg-emerald-50 transition-all"
																		value="<?php echo isset($inv_obs[$item->id]) && $inv_obs[$item->id]->quantity > 0 ? esc_attr($inv_obs[$item->id]->quantity) : ''; ?>"
																		placeholder="0.00">
																</td>
																<td class="px-6 py-4">
																	<input type="number" step="0.01"
																		name="inventory_items[<?php echo $item->id; ?>][cost]"
																		class="ob-inv-cost w-full px-4 py-2 border border-slate-200 rounded-lg text-right font-black bg-white focus:bg-indigo-50 transition-all"
																		value="<?php echo isset($inv_obs[$item->id]) && $inv_obs[$item->id]->unit_cost > 0 ? esc_attr($inv_obs[$item->id]->unit_cost) : esc_attr($item->purchase_price); ?>">
																</td>
																<td
																	class="px-6 py-4 text-right font-black text-slate-700 ob-inv-subtotal text-lg">
																	0.00</td>
															</tr>
														<?php endforeach; ?>
													<?php endif; ?>
												</tbody>
												<tfoot class="bg-slate-900 rounded-b-xl overflow-hidden">
													<tr>
														<td colspan="4"
															class="px-6 py-6 text-right text-slate-300 uppercase font-black tracking-widest text-xs">
															Total Marketable Inventory Value:</td>
														<td id="ob-total-inventory"
															class="px-6 py-6 text-right text-emerald-400 text-2xl font-black">
															0.00</td>
													</tr>
												</tfoot>
											</table>
										</div>
									</div>
								</div>

								<!-- Validation and Actions -->
								<div
									class="mt-12 p-8 bg-slate-50 rounded-2xl border border-slate-200 shadow-lg border-b-8 border-indigo-200 transition-all hover:shadow-xl">
									<h4 class="text-xl font-black text-slate-800 mb-6 flex items-center">
										<i class="fa-solid fa-shield-halved mr-3 text-indigo-600"></i> Finalization & Safety
										Checks
									</h4>

									<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
										<div
											class="p-6 bg-white rounded-xl border border-slate-200 shadow-sm group hover:border-blue-400 transition-all">
											<label class="flex items-start cursor-pointer">
												<input type="checkbox" name="auto_adjust" value="1"
													class="mt-1 w-6 h-6 text-blue-600 rounded-lg border-slate-300 focus:ring-blue-500 transition-all cursor-pointer">
												<div class="ml-4">
													<span
														class="block text-lg font-black text-slate-800 group-hover:text-blue-700 transition">Auto-Imbalance
														Correction</span>
													<p class="text-sm text-slate-500 mt-2 font-medium leading-relaxed">System
														will automatically adjust any credit/debit mismatch to the
														<strong>Opening Balance Equity</strong> account for bookkeeping
														compliance.
													</p>
												</div>
											</label>
										</div>

										<div
											class="p-6 bg-white rounded-xl border border-slate-200 shadow-sm group hover:border-rose-400 transition-all">
											<label class="flex items-start cursor-pointer">
												<input type="checkbox" name="lock" value="1"
													class="mt-1 w-6 h-6 text-rose-600 rounded-lg border-slate-300 focus:ring-rose-500 transition-all cursor-pointer">
												<div class="ml-4">
													<span
														class="block text-lg font-black text-slate-800 group-hover:text-rose-700 transition">Final
														Commit & Lock</span>
													<p class="text-sm text-slate-500 mt-2 font-medium leading-relaxed">Check
														this to finalize the opening balances and generate a <strong>Journal
															Entry</strong>. This action is permanent and restricts further basic
														editing.</p>
												</div>
											</label>
										</div>
									</div>

									<div class="flex flex-col sm:flex-row gap-6">
										<button type="submit" id="obn-opening-balance-save"
											class="flex-1 px-10 py-5 bg-indigo-600 hover:bg-slate-900 text-white font-black rounded-xl shadow-xl hover:shadow-2xl active:transform active:scale-95 transition-all flex items-center justify-center text-lg tracking-wide uppercase">
											<i class="fa-solid fa-cloud-arrow-up mr-3 text-2xl"></i> Save & Process Balances
										</button>
										<button type="button" id="obn-opening-balance-reset"
											class="px-10 py-5 bg-white border-4 border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 text-slate-600 font-black rounded-xl transition-all uppercase tracking-widest text-sm">
											<i class="fa-solid fa-rotate mr-2"></i> Hard Reset
										</button>
									</div>
								</div>
							</form>
						</div>
					</div>
					<!--Opening Balance Section End Here-->

					<!--Add features start here-->
					<div id="obn-view-add-features" class="obn-view-section">
						<?php
						if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/settings/add-features.php')) {
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/settings/add-features.php';
						} else {
							echo "<div class='p-4 bg-red-100 text-red-700'>Error: Template file missing.</div>";
						}
						?>
					</div>
					<!--Add features end here-->

					<!--User Permissions start here-->
					<div id="obn-view-setting-user-permissions" class="obn-view-section">
						<?php
						if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/settings/user-permissions.php')) {
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/settings/user-permissions.php';
						} else {
							echo "<div class='p-4 bg-red-100 text-red-700'>Error: Template file missing.</div>";
						}
						?>
					</div>
					<!--User Permissions end here-->

					<!-- Advance Section (List / Add / Edit views) -->
					<div id="obn-view-advance-list" class="obn-view-section">
						<?php global $wpdb;
						$adv_table = $wpdb->prefix . 'orabooks_db_custadvance';
						$cust_table = $wpdb->prefix . 'orabooks_db_customers';
						$pt_table = $wpdb->prefix . 'orabooks_db_paymenttypes';
						$advances = $wpdb->get_results("SELECT a.*, c.customer_name FROM {$adv_table} AS a LEFT JOIN {$cust_table} AS c ON a.customer_id = c.id ORDER BY a.id DESC");
						$adv_nonce = wp_create_nonce('obn_advance_action_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-4">
								<h3 class="text-2xl font-bold text-gray-800">Advance List</h3>
								<button id="obn-show-advance-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add Advance</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-advances-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search advances...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="printBtn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-advances-table" data-title="Advance List" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="pdfBtn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-advances-table" data-title="Advance List" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="excelBtn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-advances-table" data-title="Advance_List" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="csvBtn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-advances-table" data-title="Advance_List" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last) -->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$adv_cols = ['ID', 'Date', 'Customer', 'Amount', 'Payment Type', 'Note'];
												foreach ($adv_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
															data-column="<?php echo $idx; ?>" data-table="#obn-advances-table">
														<span
															class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-advances-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Customer</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Amount</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Payment Type</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Note</th>
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-200">
										<?php if ($advances):
											foreach ($advances as $a): ?>
												<tr data-id="<?php echo esc_attr($a->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($a->id); ?></td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($a->payment_date); ?></td>
													<td class="px-4 py-3 text-gray-800">
														<?php echo esc_html($a->customer_name ?: ''); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html(number_format_i18n($a->amount, 2)); ?>
													</td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($a->payment_type); ?></td>
													<td class="px-4 py-3 text-gray-600"><?php echo esc_html($a->note); ?></td>
													<td class="px-4 py-3 text-center no-export">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="obn-toggle-advance-status sr-only peer"
																data-id="<?php echo esc_attr($a->id); ?>"
																data-status="<?php echo esc_attr($a->status); ?>"
																data-nonce="<?php echo esc_attr($adv_nonce); ?>" <?php echo ($a->status == 1) ? 'checked' : ''; ?>>
															<div
																class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
															</div>
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="button button-small obn-edit-advance px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($a->id); ?>"
															data-nonce="<?php echo esc_attr($adv_nonce); ?>">Edit</button>
														<button
															class="button button-small button-danger obn-delete-advance px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($a->id); ?>"
															data-nonce="<?php echo esc_attr($adv_nonce); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="8" class="px-4 py-8 text-center text-gray-500">No advances found.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="obn-view-advance-add" class="obn-view-section">
						<?php $customers = $wpdb->get_results("SELECT id, customer_name FROM {$cust_table} WHERE status = 1 ORDER BY customer_name ASC");
						$payment_types = $wpdb->get_results("SELECT id, payment_type FROM {$pt_table} WHERE status = 1 ORDER BY payment_type ASC"); ?>
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Advance</h3>
							<form id="obn-advance-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_insert_advance">
								<input type="hidden" name="security" value="<?php echo esc_attr($adv_nonce); ?>">
								<div class="grid grid-cols-2 gap-6">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
										<input type="date" name="payment_date" class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Customer</label>
										<select name="customer_id" class="w-full px-4 py-2 border rounded" required>
											<option value="">Select Customer</option>
											<?php foreach ($customers as $c): ?>
												<option value="<?php echo esc_attr($c->id); ?>">
													<?php echo esc_html($c->customer_name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Amount</label>
										<input type="number" step="0.01" name="amount" class="w-full px-4 py-2 border rounded"
											required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Payment Type</label>
										<select name="payment_type" class="w-full px-4 py-2 border rounded" required>
											<option value="">Select Payment Type </option>
											<?php foreach ($payment_types as $pt): ?>
												<option value="<?php echo esc_attr($pt->payment_type); ?>">
													<?php echo esc_html($pt->payment_type); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Note</label>
										<textarea name="note" class="w-full px-4 py-2 border rounded" rows="3"></textarea>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-advance-add-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Add Advance</button>
									<button type="button" id="obn-advance-add-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>

					<div id="obn-view-advance-edit" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Advance</h3>
							<form id="obn-advance-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_update_advance">
								<input type="hidden" name="security" value="<?php echo esc_attr($adv_nonce); ?>">
								<input type="hidden" id="obn_edit_advance_id" name="id" value="">
								<div class="grid grid-cols-2 gap-6">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
										<input type="date" id="obn_edit_advance_date" name="payment_date"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Customer</label>
										<select id="obn_edit_advance_customer" name="customer_id"
											class="w-full px-4 py-2 border rounded" required>
											<option value=""> Select Customer </option>
											<?php foreach ($customers as $c): ?>
												<option value="<?php echo esc_attr($c->id); ?>">
													<?php echo esc_html($c->customer_name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Amount</label>
										<input type="number" step="0.01" id="obn_edit_advance_amount" name="amount"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Payment Type</label>
										<select id="obn_edit_advance_payment_type" name="payment_type"
											class="w-full px-4 py-2 border rounded" required>
											<option value=""> Select Payment Type </option>
											<?php foreach ($payment_types as $pt): ?>
												<option value="<?php echo esc_attr($pt->payment_type); ?>">
													<?php echo esc_html($pt->payment_type); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-span-2">
										<label class="block text-sm font-semibold text-gray-700 mb-2">Note</label>
										<textarea id="obn_edit_advance_note" name="note" class="w-full px-4 py-2 border rounded"
											rows="3"></textarea>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-advance-edit-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update
										Advance</button>
									<button type="button" id="obn-advance-edit-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Quotations Section -->
					<?php include_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/quotation/view-quotations.php'; ?>
					<?php include_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/quotation/add-quotation.php'; ?>
					<?php include_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/quotation/edit-quotation.php'; ?>
					<?php include_once OBN_ACCOUNTING_PLUGIN_DIR . 'includes/quotation/quotation-invoice.php'; ?>

					<!-- Expense List View -->
					<div id="obn-view-expense-list" class="obn-view-section">
						<?php global $wpdb;
						$exp_table = $wpdb->prefix . 'orabooks_db_expense';
						$sup_table = $wpdb->prefix . 'orabooks_db_suppliers';
						$acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
						$exp_items_table = $wpdb->prefix . 'orabooks_db_expense_items';

						// Get expenses with supplier and account info
						$expenses = $wpdb->get_results("
							SELECT e.*, s.supplier_name, a.account_name,
								   (SELECT COUNT(*) FROM {$exp_items_table} ei WHERE ei.expense_id = e.id AND ei.status = 1) as item_count
							FROM {$exp_table} AS e 
							LEFT JOIN {$sup_table} AS s ON e.supplier_id = s.id 
							LEFT JOIN {$acc_table} AS a ON e.account_id = a.id 
							WHERE e.status = 1 
							ORDER BY e.expense_date DESC, e.id DESC
						");

						$suppliers = $wpdb->get_results("SELECT id, supplier_name FROM {$sup_table} WHERE status = 1 ORDER BY supplier_name ASC");
						$accounts = $wpdb->get_results("SELECT id, account_name FROM {$acc_table} WHERE status = 1 ORDER BY account_name ASC");
						$exp_nonce = wp_create_nonce('obn_expense_action_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-4">
								<h3 class="text-2xl font-bold text-gray-800">Expenses</h3>
								<button id="obn-expense-show-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ New Expense</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-expenses-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search expenses...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="printBtn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-expenses-table" data-title="Expense List" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="pdfBtn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-expenses-table" data-title="Expense List" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="excelBtn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-expenses-table" data-title="Expense_List" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="csvBtn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-expenses-table" data-title="Expense_List" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last) -->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$exp_cols = ['Date', 'Supplier', 'Reference', 'Payment Type', 'Total Amount', 'Account', 'Items'];
												foreach ($exp_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
															data-column="<?php echo $idx; ?>" data-table="#obn-expenses-table">
														<span
															class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-expenses-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Supplier</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Reference</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Payment Type</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Total Amount</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Account</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Items</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-200">
										<?php if ($expenses):
											foreach ($expenses as $e): ?>
												<tr data-id="<?php echo esc_attr($e->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html(date('d M Y', strtotime($e->expense_date))); ?>
													</td>
													<td class="px-4 py-3 text-gray-800">
														<?php echo esc_html($e->supplier_name ?: 'No Supplier'); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($e->reference_no ?: ''); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($e->payment_type); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html(number_format($e->total_amount ?? 0, 2)); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($e->account_name ?: ''); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html($e->item_count . ' items'); ?>
													</td>
													<td class="px-4 py-3 text-center no-export">
														<label class="flex items-center justify-center">
															<input type="checkbox" class="obn-toggle-expense-status"
																data-id="<?php echo esc_attr($e->id); ?>"
																data-nonce="<?php echo esc_attr($exp_nonce); ?>" <?php checked($e->status, 1); ?>
																style="width:18px;height:18px;cursor:pointer;">
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="obn-expense-view px-3 py-1 bg-teal-500 hover:bg-teal-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($e->id); ?>"
															data-nonce="<?php echo esc_attr($exp_nonce); ?>">View</button>
														<button
															class="obn-expense-edit px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($e->id); ?>"
															data-nonce="<?php echo esc_attr($exp_nonce); ?>">Edit</button>
														<button
															class="obn-expense-delete px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($e->id); ?>"
															data-nonce="<?php echo esc_attr($exp_nonce); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="9" class="px-4 py-8 text-center text-gray-500">No expenses found.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- Expense View Modal -->
					<div id="obn-expense-view-modal"
						class="fixed inset-0 z-[9999] hidden flex items-center justify-center bg-black bg-opacity-50 transition-opacity">
						<div
							class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform scale-95 opacity-0 transition-all duration-300">
							<!-- Header -->
							<div
								class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
								<h3 class="text-xl font-bold text-gray-800 flex items-center">
									<i class="fa-solid fa-file-invoice mr-3 text-blue-600"></i> Expense Details
								</h3>
								<button type="button"
									class="obn-close-modal text-gray-400 hover:text-gray-700 transition-colors">
									<i class="fa-solid fa-times text-xl"></i>
								</button>
							</div>

							<!-- Content -->
							<div class="p-6">
								<!-- Info Grid -->
								<div
									class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 bg-blue-50 p-4 rounded-lg border border-blue-100">
									<div>
										<span class="block text-xs font-semibold text-gray-500 uppercase">Date</span>
										<strong class="text-gray-900" id="view_expense_date"></strong>
									</div>
									<div>
										<span class="block text-xs font-semibold text-gray-500 uppercase">Reference No.</span>
										<strong class="text-gray-900" id="view_expense_ref"></strong>
									</div>
									<div>
										<span class="block text-xs font-semibold text-gray-500 uppercase">Supplier</span>
										<strong class="text-gray-900" id="view_expense_supplier"></strong>
									</div>
									<div>
										<span class="block text-xs font-semibold text-gray-500 uppercase">Payment Type</span>
										<strong class="text-gray-900" id="view_expense_payment_type"></strong>
									</div>
								</div>

								<!-- Items Table -->
								<h4 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">Expense Items</h4>
								<div class="overflow-x-auto mb-6 border border-gray-200 rounded-lg">
									<table class="min-w-full divide-y divide-gray-200">
										<thead class="bg-gray-50">
											<tr>
												<th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">#</th>
												<th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">
													Account</th>
												<th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">
													Description</th>
												<th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">
													Amount</th>
											</tr>
										</thead>
										<tbody id="view_expense_items_tbody" class="bg-white divide-y divide-gray-200">
											<!-- Injected via JS -->
										</tbody>
										<tfoot class="bg-gray-50">
											<tr>
												<td colspan="3" class="px-4 py-3 text-right font-bold text-gray-700">Total
													Amount:</td>
												<td class="px-4 py-3 text-right font-bold text-lg text-blue-600"
													id="view_expense_total">0.00</td>
											</tr>
										</tfoot>
									</table>
								</div>

								<!-- Summary and Comments -->
								<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
									<div>
										<h4 class="text-md font-bold text-gray-800 mb-2">Comments</h4>
										<p class="text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-200 min-h-[60px]"
											id="view_expense_comments"></p>
									</div>
									<div class="space-y-3">
										<div class="flex justify-between items-center py-2 border-b">
											<span class="font-medium text-gray-600">Paid Amount:</span>
											<strong class="text-gray-900" id="view_expense_paid">0.00</strong>
										</div>
										<div class="flex justify-between items-center py-2 border-b">
											<span class="font-medium text-gray-600">Due Amount:</span>
											<strong class="text-rose-600" id="view_expense_due">0.00</strong>
										</div>
										<div class="flex justify-between items-center py-2">
											<span class="font-medium text-gray-600">Status:</span>
											<span class="px-3 py-1 rounded-full text-xs font-bold text-white"
												id="view_expense_status"></span>
										</div>
									</div>
								</div>
							</div>

							<!-- Footer -->
							<div
								class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl flex justify-between items-center">
								<div class="flex gap-2">
									<button type="button" onclick="window.print()"
										class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md font-medium transition-colors">
										<i class="fa-solid fa-print mr-1"></i> Print
									</button>
									<button type="button" onclick="window.print()"
										class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-md font-medium transition-colors"
										title="Use Print to Save as PDF">
										<i class="fa-solid fa-file-pdf mr-1"></i> PDF
									</button>
								</div>
								<button type="button"
									class="obn-close-modal px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md font-medium transition-colors">
									Close
								</button>
							</div>
						</div>
					</div>

					<!-- Journal Entry View Modal -->
					<div id="obn-journal-view-modal"
						class="fixed inset-0 z-[9999] hidden flex items-center justify-center bg-black bg-opacity-50 transition-opacity">
						<div
							class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform scale-95 opacity-0 transition-all duration-300">
							<!-- Header -->
							<div
								class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
								<h3 class="text-xl font-bold text-gray-800 flex items-center">
									<i class="fa-solid fa-book mr-3 text-blue-600"></i> Journal Entry Details
								</h3>
								<button type="button"
									class="obn-close-je-modal text-gray-400 hover:text-gray-700 transition-colors">
									<i class="fa-solid fa-times text-xl"></i>
								</button>
							</div>

							<!-- Content -->
							<div class="p-6">
								<!-- Info Grid -->
								<div
									class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 bg-blue-50 p-4 rounded-lg border border-blue-100">
									<div>
										<span class="block text-xs font-semibold text-gray-500 uppercase">Date</span>
										<strong class="text-gray-900" id="view_je_date"></strong>
									</div>
									<div>
										<span class="block text-xs font-semibold text-gray-500 uppercase">Reference</span>
										<strong class="text-gray-900" id="view_je_ref"></strong>
									</div>
									<div>
										<span class="block text-xs font-semibold text-gray-500 uppercase">Status</span>
										<span class="px-2 py-0.5 rounded bg-green-100 text-green-800 text-xs font-bold"
											id="view_je_status"></span>
									</div>
									<div>
										<span class="block text-xs font-semibold text-gray-500 uppercase">Created By</span>
										<strong class="text-gray-900" id="view_je_user"></strong>
									</div>
								</div>

								<!-- Ledger Lines Table -->
								<div class="mb-6">
									<h4 class="text-md font-bold text-gray-800 mb-3 flex items-center">
										<i class="fa-solid fa-list-ul mr-2 text-gray-400"></i> Ledger Lines
									</h4>
									<table
										class="w-full border-collapse border border-gray-200 rounded-lg overflow-hidden shadow-sm">
										<thead class="bg-gray-50">
											<tr>
												<th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">
													Account</th>
												<th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">
													Description</th>
												<th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">
													Debit</th>
												<th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">
													Credit</th>
											</tr>
										</thead>
										<tbody id="view_je_lines_tbody" class="bg-white divide-y divide-gray-200">
											<!-- Lines will be loaded here -->
										</tbody>
										<tfoot class="bg-gray-50 font-bold">
											<tr>
												<td colspan="2"
													class="px-4 py-3 text-right text-gray-700 uppercase tracking-wider">Total
												</td>
												<td class="px-4 py-3 text-right text-blue-600" id="view_je_total_debit">0.00
												</td>
												<td class="px-4 py-3 text-right text-blue-600" id="view_je_total_credit">0.00
												</td>
											</tr>
										</tfoot>
									</table>
								</div>

								<!-- Description -->
								<div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
									<h4 class="text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Memo /
										Description</h4>
									<p class="text-gray-600 whitespace-pre-wrap" id="view_je_description"></p>
								</div>
							</div>

							<!-- Footer -->
							<div
								class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl flex justify-between items-center no-print">
								<div class="flex gap-2">
									<button type="button" onclick="window.print()"
										class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md font-medium transition-colors">
										<i class="fa-solid fa-print mr-1"></i> Print
									</button>
								</div>
								<button type="button"
									class="obn-close-je-modal px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md font-medium transition-colors">
									Close
								</button>
							</div>
						</div>
					</div>

					<!-- Expense Add View -->
					<div id="obn-view-expense-add" class="obn-view-section" style="display:none;">
						<div class="bg-white rounded-lg shadow-lg border border-gray-200">
							<!-- Header -->
							<div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
								<h3 class="text-xl font-semibold text-gray-900">Add Expense</h3>
								<button type="button" id="obn-expense-add-cancel"
									class="text-gray-400 hover:text-gray-600 transition-colors">
									<i class="fa-solid fa-times text-lg"></i>
								</button>
							</div>

							<!-- Form -->
							<form id="obn-expense-add-form" class="p-6">
								<input type="hidden" name="action" value="obn_insert_expense">
								<input type="hidden" name="security" value="<?php echo esc_attr($exp_nonce); ?>">

								<!-- First Row: Date | Reference No | Pay To -->
								<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Date <span class="text-red-500">*</span>
										</label>
										<input type="date" name="expense_date" id="obn_add_expense_date"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
									</div>

									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Reference No.
										</label>
										<input type="text" name="reference_no" id="obn_add_expense_ref"
											class="w-full p-2 px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											placeholder="Optional">
									</div>

									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Pay To <span class="text-red-500">*</span>
										</label>
										<select name="supplier_id" id="obn_add_supplier_id"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											required>
											<option value="">Select Supplier</option>
											<?php
											// Get suppliers from database
											$suppliers = $wpdb->get_results("SELECT id, supplier_name, address FROM {$wpdb->prefix}orabooks_db_suppliers WHERE status = 1 ORDER BY supplier_name ASC");
											foreach ($suppliers as $supplier) {
												echo '<option value="' . esc_attr($supplier->id) . '" data-address="' . esc_attr($supplier->address) . '">' . esc_html($supplier->supplier_name) . '</option>';
											}
											?>
										</select>
									</div>
								</div>

								<!-- Second Row: Payment Type | Transaction from | Billing Address -->
								<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Payment Type <span class="text-red-500">*</span>
										</label>
										<select name="payment_type" id="obn_add_expense_payment_type"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											required>
											<option value="">Select Payment Type</option>
											<?php
											// Get payment types from database
											$payment_types = $wpdb->get_results("SELECT id, payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE status = 1 ORDER BY payment_type ASC");
											foreach ($payment_types as $pt) {
												echo '<option value="' . esc_attr($pt->payment_type) . '">' . esc_html($pt->payment_type) . '</option>';
											}
											?>
										</select>
									</div>

									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Transaction From
										</label>
										<select name="bank_account_id" id="obn_add_expense_account"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
											<option value="">Select Account</option>
											<?php foreach ($accounts as $acc) {
												echo '<option value="' . esc_attr($acc->id) . '">' . esc_html($acc->account_name) . '</option>';
											} ?>
										</select>
									</div>

									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Billing Address
										</label>
										<textarea id="billing_address" readonly
											class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-600 resize-none"
											rows="2" placeholder="Select supplier to show address"></textarea>
									</div>
								</div>

								<!-- Third Row: Table -->
								<div class="mb-6">
									<div class="flex justify-between items-center mb-3">
										<h4 class="text-sm font-medium text-gray-700">Expense Details</h4>
										<button type="button" id="add-expense-row"
											class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors">
											<i class="fa-solid fa-plus mr-1"></i> Add Row
										</button>
									</div>

									<div class="overflow-x-auto">
										<table class="min-w-full border border-gray-200">
											<thead class="bg-gray-50">
												<tr>
													<th class="px-3 py-2 text-left text-xs font-medium text-gray-700 border-b">
														SL No.</th>
													<th class="px-3 py-2 text-left text-xs font-medium text-gray-700 border-b">
														Account</th>
													<th class="px-3 py-2 text-left text-xs font-medium text-gray-700 border-b">
														Description</th>
													<th class="px-3 py-2 text-left text-xs font-medium text-gray-700 border-b">
														Amount</th>
													<th
														class="px-3 py-2 text-center text-xs font-medium text-gray-700 border-b">
														Total</th>
													<th
														class="px-3 py-2 text-center text-xs font-medium text-gray-700 border-b">
														Action</th>
												</tr>
											</thead>
											<tbody id="expense-items-tbody">
												<tr class="expense-row" data-row="1">
													<td class="px-3 py-2 text-sm text-gray-900 border-b">1</td>
													<td class="px-3 py-2 border-b">
														<select name="expense_account[]"
															class="expense-account w-full px-2 py-1 border border-gray-300 rounded text-sm"
															required>
															<option value="">Select Account</option>
															<?php
															// Get expense accounts from COA by joining with coa_types
															$expense_accounts = $wpdb->get_results("
																SELECT coa.id, coa.account_name, coa.account_code 
																FROM {$wpdb->prefix}orabooks_ac_coa_list coa
																LEFT JOIN {$wpdb->prefix}orabooks_ac_coa_types ct ON coa.coa_type_id = ct.id
																WHERE ct.coa_type = 'Expenses' 
																ORDER BY coa.account_name ASC
															");
															foreach ($expense_accounts as $account) {
																echo '<option value="' . esc_attr($account->id) . '">' . esc_html($account->account_name) . ' (' . esc_html($account->account_code) . ')</option>';
															}
															?>
														</select>
													</td>
													<td class="px-3 py-2 border-b">
														<input type="text" name="expense_description[]"
															class="expense-description w-full px-2 py-1 border border-gray-300 rounded text-sm"
															placeholder="Description" required>
													</td>
													<td class="px-3 py-2 border-b">
														<input type="number" step="0.01" name="expense_amount[]"
															class="expense-amount w-full px-2 py-1 border border-gray-300 rounded text-sm"
															placeholder="0.00" required>
													</td>
													<td
														class="px-3 py-2 text-right text-sm font-medium text-gray-900 border-b expense-total">
														0.00</td>
													<td class="px-3 py-2 text-center border-b">
														<button type="button"
															class="remove-expense-row text-red-600 hover:text-red-800 text-sm">
															<i class="fa-solid fa-trash"></i>
														</button>
													</td>
												</tr>
											</tbody>
											<tfoot>
												<tr class="bg-gray-50">
													<td colspan="4" class="px-3 py-2 text-right font-medium text-gray-700">Total
														Amount:</td>
													<td class="px-3 py-2 text-right font-bold text-lg text-gray-900"
														id="total-amount">0.00</td>
													<td></td>
												</tr>
											</tfoot>
										</table>
									</div>
								</div>

								<!-- Payment Breakdown -->
								<div class="grid grid-cols-1 md:grid-cols-1 gap-4 mb-6">
									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Paid Amount
										</label>
										<input type="number" step="0.01" name="paid_amount" id="obn_add_expense_paid_amount"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											placeholder="0.00">
									</div>
								</div>

								<!-- Comments Box -->
								<div class="mb-6">
									<label class="block text-sm font-medium text-gray-700 mb-2">
										Comments
									</label>
									<textarea name="comments" id="obn_add_expense_comments"
										class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
										rows="3" placeholder="Add any additional comments..."></textarea>
								</div>

								<!-- Action Buttons -->
								<div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
									<button type="button" id="obn-expense-add-cancel"
										class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
										Back to List
									</button>
									<button type="submit"
										class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
										Save Expense
									</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Expense Edit View -->
					<div id="obn-view-expense-edit" class="obn-view-section" style="display:none;">
						<div class="bg-white rounded-lg shadow-lg border border-gray-200">
							<!-- Header -->
							<div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
								<h3 class="text-xl font-semibold text-gray-900">Edit Expense</h3>
								<button type="button" id="obn-expense-edit-cancel"
									class="text-gray-400 hover:text-gray-600 transition-colors">
									<i class="fa-solid fa-times text-lg"></i>
								</button>
							</div>

							<!-- Form -->
							<form id="obn-expense-edit-form" class="p-6">
								<input type="hidden" name="action" value="obn_update_expense">
								<input type="hidden" name="security" value="<?php echo esc_attr($exp_nonce); ?>">
								<input type="hidden" name="id" id="obn_edit_expense_id">

								<!-- First Row: Date | Reference No | Pay To -->
								<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Date <span class="text-red-500">*</span>
										</label>
										<input type="date" name="expense_date" id="obn_edit_expense_date"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											required>
									</div>

									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Reference No.
										</label>
										<input type="text" name="reference_no" id="obn_edit_expense_ref"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											placeholder="Optional">
									</div>

									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Pay To <span class="text-red-500">*</span>
										</label>
										<select name="supplier_id" id="obn_edit_supplier_id"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											required>
											<option value="">Select Supplier</option>
											<?php
											foreach ($suppliers as $supplier) {
												echo '<option value="' . esc_attr($supplier->id) . '" data-address="' . esc_attr($supplier->address) . '">' . esc_html($supplier->supplier_name) . '</option>';
											}
											?>
										</select>
									</div>
								</div>

								<!-- Second Row: Payment Type | Transaction from | Billing Address -->
								<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Payment Type <span class="text-red-500">*</span>
										</label>
										<select name="payment_type" id="obn_edit_expense_payment_type"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											required>
											<option value="">Select Payment Type</option>
											<?php
											foreach ($payment_types as $pt) {
												echo '<option value="' . esc_attr($pt->payment_type) . '">' . esc_html($pt->payment_type) . '</option>';
											}
											?>
										</select>
									</div>

									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Transaction From
										</label>
										<select name="bank_account_id" id="obn_edit_expense_account"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
											<option value="">Select Account</option>
											<?php foreach ($accounts as $acc) {
												echo '<option value="' . esc_attr($acc->id) . '">' . esc_html($acc->account_name) . '</option>';
											} ?>
										</select>
									</div>

									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Billing Address
										</label>
										<textarea id="edit_billing_address" name="billing_address"
											class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-600 resize-none"
											rows="2" placeholder="Select supplier to show address"></textarea>
									</div>
								</div>

								<!-- Third Row: Table -->
								<div class="mb-6">
									<div class="flex justify-between items-center mb-3">
										<h4 class="text-sm font-medium text-gray-700">Expense Details</h4>
										<button type="button" id="edit-add-expense-row"
											class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors">
											<i class="fa-solid fa-plus mr-1"></i> Add Row
										</button>
									</div>

									<div class="overflow-x-auto">
										<table class="min-w-full border border-gray-200">
											<thead class="bg-gray-50">
												<tr>
													<th class="px-3 py-2 text-left text-xs font-medium text-gray-700 border-b">
														SL No.</th>
													<th class="px-3 py-2 text-left text-xs font-medium text-gray-700 border-b">
														Account</th>
													<th class="px-3 py-2 text-left text-xs font-medium text-gray-700 border-b">
														Description</th>
													<th class="px-3 py-2 text-left text-xs font-medium text-gray-700 border-b">
														Amount</th>
													<th
														class="px-3 py-2 text-center text-xs font-medium text-gray-700 border-b">
														Total</th>
													<th
														class="px-3 py-2 text-center text-xs font-medium text-gray-700 border-b">
														Action</th>
												</tr>
											</thead>
											<tbody id="edit-expense-items-tbody">
												<!-- Items will be populated by JS -->
											</tbody>
											<tfoot>
												<tr class="bg-gray-50">
													<td colspan="4" class="px-3 py-2 text-right font-medium text-gray-700">Total
														Amount:</td>
													<td class="px-3 py-2 text-right font-bold text-lg text-gray-900"
														id="edit-total-amount">0.00</td>
													<td></td>
												</tr>
											</tfoot>
										</table>
									</div>
								</div>

								<!-- Payment Breakdown -->
								<div class="grid grid-cols-1 md:grid-cols-1 gap-4 mb-6">
									<div>
										<label class="block text-sm font-medium text-gray-700 mb-2">
											Paid Amount
										</label>
										<input type="number" step="0.01" name="paid_amount" id="obn_edit_expense_paid_amount"
											class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
											placeholder="0.00">
									</div>
								</div>

								<!-- Comments Box -->
								<div class="mb-6">
									<label class="block text-sm font-medium text-gray-700 mb-2">
										Comments
									</label>
									<textarea name="comments" id="obn_edit_expense_comments"
										class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
										rows="3" placeholder="Add any additional comments..."></textarea>
								</div>

								<!-- Action Buttons -->
								<div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
									<button type="button" id="obn-expense-edit-cancel"
										class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
										Back to List
									</button>
									<button type="submit"
										class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
										Update Expense
									</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Expense Category List View -->
					<div id="obn-view-expense-category" class="obn-view-section">
						<?php $exp_cats = $wpdb->get_results("SELECT * FROM {$cat_table} WHERE status = 1 ORDER BY id DESC");
						$cat_nonce = wp_create_nonce('obn_expense_category_action_nonce'); ?>
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-4">
								<h3 class="text-2xl font-bold text-gray-800">Expense Categories</h3>
								<button id="obn-category-show-add"
									class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ New Category</button>
							</div>
							<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
								<div class="relative w-full md:w-80">
									<span
										class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
										<i class="fa-solid fa-magnifying-glass"></i>
									</span>
									<input type="search" id="obn-categories-search"
										class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
										placeholder="Search categories...">
								</div>

								<div class="flex items-center gap-3">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="printBtn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-categories-table" data-title="Expense Categories" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="pdfBtn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-categories-table" data-title="Expense Categories" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="excelBtn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-categories-table" data-title="Expense_List" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="csvBtn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-categories-table" data-title="Expense_List" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last) -->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$cat_cols = ['Name', 'Description'];
												foreach ($cat_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
															data-column="<?php echo $idx; ?>" data-table="#obn-categories-table">
														<span
															class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
								<table id="obn-categories-table" class="w-full text-sm">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Category Name</th>
											<th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
											<th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
											<th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-200">
										<?php if ($exp_cats):
											foreach ($exp_cats as $cat): ?>
												<tr data-id="<?php echo esc_attr($cat->id); ?>" class="hover:bg-gray-50">
													<td class="px-4 py-3 text-gray-800 font-medium">
														<?php echo esc_html($cat->category_name); ?>
													</td>
													<td class="px-4 py-3 text-gray-600">
														<?php echo esc_html(substr($cat->description, 0, 50) . (strlen($cat->description) > 50 ? '...' : '')); ?>
													</td>
													<td class="px-4 py-3 text-center no-export">
														<label class="flex items-center justify-center">
															<input type="checkbox" class="obn-toggle-category-status"
																data-id="<?php echo esc_attr($cat->id); ?>"
																data-nonce="<?php echo esc_attr($cat_nonce); ?>" <?php checked($cat->status, 1); ?>
																style="width:18px;height:18px;cursor:pointer;">
														</label>
													</td>
													<td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
														<button
															class="obn-category-edit px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($cat->id); ?>"
															data-nonce="<?php echo esc_attr($cat_nonce); ?>">Edit</button>
														<button
															class="obn-category-delete px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
															data-id="<?php echo esc_attr($cat->id); ?>"
															data-nonce="<?php echo esc_attr($cat_nonce); ?>">Delete</button>
													</td>
												</tr>
											<?php endforeach; else: ?>
											<tr>
												<td colspan="4" class="px-4 py-8 text-center text-gray-500">No categories found.
												</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- Expense Category Add View -->
					<div id="obn-view-expense-category-add" class="obn-view-section" style="display:none;">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Add Category</h3>
							<form id="obn-category-add-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_insert_expense_category">
								<input type="hidden" name="security" value="<?php echo esc_attr($cat_nonce); ?>">
								<div class="grid grid-cols-1 gap-6">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Category Name <span
												class="text-red-600">*</span></label>
										<input type="text" name="category_name" id="obn_add_category_name"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
										<textarea name="description" id="obn_add_category_desc"
											class="w-full px-4 py-2 border rounded" rows="3"></textarea>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit"
										class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Save
										Category</button>
									<button type="button" id="obn-category-add-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Expense Category Edit View -->
					<div id="obn-view-expense-category-edit" class="obn-view-section" style="display:none;">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Category</h3>
							<form id="obn-category-edit-form"
								class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
								<input type="hidden" name="action" value="obn_update_expense_category">
								<input type="hidden" name="security" value="<?php echo esc_attr($cat_nonce); ?>">
								<input type="hidden" name="id" id="obn_edit_category_id">
								<div class="grid grid-cols-1 gap-6">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Category Name <span
												class="text-red-600">*</span></label>
										<input type="text" name="category_name" id="obn_edit_category_name"
											class="w-full px-4 py-2 border rounded" required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
										<textarea name="description" id="obn_edit_category_desc"
											class="w-full px-4 py-2 border rounded" rows="3"></textarea>
									</div>
								</div>
								<div class="mt-4 flex gap-2">
									<button type="submit" id="obn-category-edit-save"
										class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update
										Category</button>
									<button type="button" id="obn-category-edit-cancel"
										class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Back to
										List</button>
								</div>
							</form>
						</div>
					</div>

					<!-- expense Section -->
					<div id="obn-view-expense" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3>expense</h3>
							<p>Manage expenses.</p>
						</div>
					</div>

					<!-- Coupons Views -->
					<?php
					$coupon_customers = $wpdb->get_results("SELECT id, customer_name, customer_code FROM {$wpdb->prefix}orabooks_db_customers WHERE status = 1 ORDER BY customer_name ASC");
					$master_coupons_list = $wpdb->get_results("SELECT id, name, code, value, type, expire_date FROM {$wpdb->prefix}orabooks_db_coupons WHERE status = 1 ORDER BY name ASC");
					$coupon_nonce = wp_create_nonce('obn_coupon_action_nonce');
					?>

					<!-- 1. Create Customer Coupon View -->
					<div id="obn-view-coupon-create-customer" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-6">
								<h3 class="text-2xl font-bold text-gray-800"><i
										class="fa-solid fa-user-tag text-emerald-600 mr-2"></i>Create Customer Coupon</h3>
								<button
									class="obn-show-coupon-customer-list bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded shadow"><i
										class="fa-solid fa-list mr-2"></i>Back to List</button>
							</div>
							<form id="obn-customer-coupon-create-form" class="space-y-5 max-w-2xl">
								<input type="hidden" name="action" value="obn_insert_customer_coupon">
								<input type="hidden" name="security" value="<?php echo esc_attr($coupon_nonce); ?>">

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Customer Name <span
											class="text-red-500">*</span></label>
									<select name="customer_id"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"
										required>
										<option value="">Select Customer</option>
										<?php if ($coupon_customers) {
											foreach ($coupon_customers as $cust): ?>
												<option value="<?php echo esc_attr($cust->id); ?>">
													<?php echo esc_html($cust->customer_name . ($cust->customer_code ? " ($cust->customer_code)" : '')); ?>
												</option>
											<?php endforeach;
										} ?>
									</select>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Occasion Name <span
											class="text-red-500">*</span></label>
									<select name="coupon_id" id="obn_cc_coupon_id"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"
										required>
										<option value="">Select Occasion/Coupon</option>
										<?php if ($master_coupons_list) {
											foreach ($master_coupons_list as $mc): ?>
												<option value="<?php echo esc_attr($mc->id); ?>"
													data-value="<?php echo esc_attr($mc->value); ?>"
													data-type="<?php echo esc_attr($mc->type); ?>"
													data-expire="<?php echo esc_attr($mc->expire_date); ?>"
													data-code="<?php echo esc_attr($mc->code); ?>">
													<?php echo esc_html($mc->name . " ({$mc->value}" . ($mc->type === 'Percentage' ? '%' : '') . ")"); ?>
												</option>
											<?php endforeach;
										} ?>
									</select>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Coupon Code <span
											class="text-red-500">*</span></label>
									<div class="flex gap-2">
										<input type="text" name="coupon_code" id="obn_cc_code"
											class="flex-1 px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-emerald-500"
											placeholder="Enter or generate" required>
										<button type="button" id="obn_cc_generate"
											class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl"><i
												class="fa-solid fa-wand-magic-sparkles mr-1"></i> Generate</button>
									</div>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Expire Date</label>
									<input type="date" name="expire_date" id="obn_cc_date"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500">
								</div>

								<div class="grid grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Value</label>
										<input type="number" name="coupon_value" id="obn_cc_value" step="0.01"
											class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500">
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
										<select name="coupon_type" id="obn_cc_type"
											class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500">
											<option value="Percentage">Percentage (%)</option>
											<option value="Fixed">Fixed Amount</option>
										</select>
									</div>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
									<textarea name="description" rows="3"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"></textarea>
								</div>

								<div class="pt-4">
									<button type="submit"
										class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-xl shadow-lg transition-all">Save
										Customer Coupon</button>
								</div>
							</form>
						</div>
					</div>

					<!-- 2. Customer Coupons List View -->
					<div id="obn-view-coupon-customer-list" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-6">
								<h3 class="text-2xl font-bold text-gray-800"><i
										class="fa-solid fa-list-check text-teal-600 mr-2"></i>Customer Coupons List</h3>
								<input type="hidden" id="obn_cc_search_nonce" value="<?php echo esc_attr($coupon_nonce); ?>">
								<button
									class="obn-show-coupon-customer-create bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-4 py-2 rounded shadow"><i
										class="fa-solid fa-plus mr-2"></i>Create Customer Coupon</button>
							</div>

							<!-- Filter -->
							<div class="bg-gray-50 p-4 rounded-lg mb-6 flex flex-wrap items-center gap-4">
								<div class="flex-1 min-w-[200px]">
									<input type="search" id="obn_cc_search" placeholder="Search..."
										class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-teal-500 focus:border-teal-500">
								</div>
								<select id="obn_cc_filter_customer"
									class="px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-teal-500 focus:border-teal-500">
									<option value="">All Customers</option>
									<?php if ($coupon_customers)
										foreach ($coupon_customers as $cust)
											echo "<option value='{$cust->id}'>{$cust->customer_name}</option>"; ?>
								</select>
								<select id="obn_cc_filter_type"
									class="px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-teal-500 focus:border-teal-500">
									<option value="">All Types</option>
									<option value="Percentage">Percentage</option>
									<option value="Fixed">Fixed</option>
								</select>
								<select id="obn_cc_filter_status"
									class="px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-teal-500 focus:border-teal-500">
									<option value="">All Status</option>
									<option value="1">Active</option>
									<option value="0">Inactive</option>
								</select>
								<button id="obn_cc_search_btn"
									class="bg-teal-600 text-white px-6 py-2 rounded-lg hover:bg-teal-700 transition shadow-sm font-medium">Search</button>

								<div class="flex items-center gap-3 ml-auto">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="printBtn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-customer-coupons-table" data-title="Customer Coupons List"
											title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="pdfBtn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-customer-coupons-table" data-title="Customer Coupons List"
											title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="excelBtn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-customer-coupons-table" data-title="Customer_Coupons_List"
											title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="csvBtn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-customer-coupons-table" data-title="Customer_Coupons_List"
											title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last) -->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$cc_cols = ['Customer', 'Occasion', 'Code', 'Expire', 'Value', 'Type'];
												foreach ($cc_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-teal-600 rounded"
															data-column="<?php echo $idx; ?>"
															data-table="#obn-customer-coupons-table">
														<span
															class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto">
								<table id="obn-customer-coupons-table" class="min-w-full divide-y divide-gray-200">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left">Customer</th>
											<th class="px-4 py-3 text-left">Occasion</th>
											<th class="px-4 py-3 text-left">Code</th>
											<th class="px-4 py-3 text-left">Expire</th>
											<th class="px-4 py-3 text-left">Value</th>
											<th class="px-4 py-3 text-left">Type</th>
											<th class="px-4 py-3 text-center no-export">Status</th>
											<th class="px-4 py-3 text-right no-export">Action</th>
										</tr>
									</thead>
									<tbody id="obn-customer-coupons-table-body" class="bg-white divide-y divide-gray-200">
										<!-- AJAX Loaded -->
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- 3. Edit Customer Coupon View -->
					<div id="obn-view-coupon-customer-edit" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-6">Edit Customer Coupon</h3>
							<form id="obn-customer-coupon-edit-form" class="space-y-5 max-w-2xl">
								<input type="hidden" name="action" value="obn_update_customer_coupon">
								<input type="hidden" name="security" value="<?php echo esc_attr($coupon_nonce); ?>">
								<input type="hidden" name="id" id="obn_edit_cc_id">

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Customer Name</label>
									<select name="customer_id" id="obn_edit_cc_customer"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50" required>
										<?php if ($coupon_customers) {
											foreach ($coupon_customers as $cust): ?>
												<option value="<?php echo esc_attr($cust->id); ?>">
													<?php echo esc_html($cust->customer_name); ?>
												</option>
											<?php endforeach;
										} ?>
									</select>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Coupon Code</label>
									<input type="text" name="coupon_code" id="obn_edit_cc_code"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50" required>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Expire Date</label>
									<input type="date" name="expire_date" id="obn_edit_cc_date"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50">
								</div>

								<div class="grid grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Value</label>
										<input type="number" name="coupon_value" id="obn_edit_cc_value" step="0.01"
											class="w-full px-4 py-3 border rounded-xl bg-gray-50">
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
										<select name="coupon_type" id="obn_edit_cc_type"
											class="w-full px-4 py-3 border rounded-xl bg-gray-50">
											<option value="Percentage">Percentage (%)</option>
											<option value="Fixed">Fixed Amount</option>
										</select>
									</div>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
									<textarea name="description" id="obn_edit_cc_desc" rows="3"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50"></textarea>
								</div>

								<div class="flex gap-2 pt-4">
									<button type="submit"
										class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl">Update
										Coupon</button>
									<button type="button"
										class="obn-show-coupon-customer-list px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold rounded-xl">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<!-- 4. Create Master Coupon View -->
					<div id="obn-view-coupon-create" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-6">
								<h3 class="text-2xl font-bold text-gray-800"><i
										class="fa-solid fa-ticket text-indigo-600 mr-2"></i>Create Master Coupon</h3>
								<button
									class="obn-coupon-cancel bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded shadow"><i
										class="fa-solid fa-list mr-2"></i>Back to List</button>
							</div>
							<form id="obn-coupon-create-form" class="space-y-5 max-w-2xl">
								<input type="hidden" name="action" value="obn_insert_coupon">
								<input type="hidden" name="security" value="<?php echo esc_attr($coupon_nonce); ?>">

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Occasion Name <span
											class="text-red-500">*</span></label>
									<input type="text" name="occasion_name"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"
										required placeholder="e.g., New Year Sale">
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Expire Date <span
											class="text-red-500">*</span></label>
									<input type="date" name="expire_date"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"
										required>
								</div>

								<div class="grid grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Value <span
												class="text-red-500">*</span></label>
										<input type="number" name="coupon_value" step="0.01"
											class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"
											required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Type <span
												class="text-red-500">*</span></label>
										<select name="coupon_type"
											class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500">
											<option value="Percentage">Percentage (%)</option>
											<option value="Fixed">Fixed Amount</option>
										</select>
									</div>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
									<textarea name="description" rows="3"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"></textarea>
								</div>

								<div class="pt-4">
									<button type="submit"
										class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-xl shadow-lg transition-all">Save
										Master Coupon</button>
								</div>
							</form>
						</div>
					</div>

					<!-- 5. Master Coupons List View -->
					<div id="obn-view-coupon-master" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<div class="flex items-center justify-between mb-6">
								<h3 class="text-2xl font-bold text-gray-800"><i
										class="fa-solid fa-tags text-purple-600 mr-2"></i>Coupons Master</h3>
								<button
									class="obn-show-coupon-create bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-4 py-2 rounded shadow"><i
										class="fa-solid fa-plus mr-2"></i>Create Coupon</button>
							</div>

							<!-- Filter -->
							<div class="bg-gray-50 p-4 rounded-lg mb-4 flex flex-wrap items-center gap-4">
								<input type="text" id="obn_master_search" placeholder="Search coupons..."
									class="flex-1 min-w-[200px] px-3 py-2 border rounded-lg">
								<select id="obn_master_filter_type" class="px-3 py-2 border rounded-lg">
									<option value="">All Types</option>
									<option value="Percentage">Percentage</option>
									<option value="Fixed">Fixed</option>
								</select>
								<select id="obn_master_filter_status" class="px-3 py-2 border rounded-lg">
									<option value="">All Status</option>
									<option value="1">Active</option>
									<option value="0">Inactive</option>
								</select>
								<input type="hidden" id="obn_master_search_nonce"
									value="<?php echo esc_attr($coupon_nonce); ?>">
								<button id="obn_master_search_btn"
									class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Search</button>

								<div class="flex items-center gap-3 ml-auto">
									<div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
										<button id="printBtn"
											class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coupons-master-table" data-title="Coupons Master" title="Print">
											<i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
										</button>
										<button id="pdfBtn"
											class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coupons-master-table" data-title="Coupons Master" title="PDF">
											<i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
										</button>
										<button id="excelBtn"
											class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coupons-master-table" data-title="Coupons_Master" title="Excel">
											<i class="fa-solid fa-file-excel mr-1"></i> <span
												class="hidden sm:inline">Excel</span>
										</button>
										<button id="csvBtn"
											class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
											data-table="#obn-coupons-master-table" data-title="Coupons_Master" title="CSV">
											<i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
										</button>
									</div>

									<!-- Column Visibility (Now Last) -->
									<div class="relative inline-block text-left">
										<button type="button"
											class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
											<i class="fa-solid fa-columns mr-2"></i> Columns
										</button>
										<div
											class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
											<div class="py-1 p-3 space-y-2">
												<?php
												$master_cols = ['Occasion Name', 'Expire Date', 'Value', 'Type'];
												foreach ($master_cols as $idx => $name): ?>
													<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
														<input type="checkbox" checked
															class="obn-col-hide form-checkbox h-4 w-4 text-indigo-600 rounded"
															data-column="<?php echo $idx; ?>"
															data-table="#obn-coupons-master-table">
														<span
															class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="overflow-x-auto">
								<table id="obn-coupons-master-table" class="min-w-full divide-y divide-gray-200">
									<thead class="bg-gray-50 border-b border-gray-200">
										<tr>
											<th class="px-4 py-3 text-left">Occasion Name</th>
											<th class="px-4 py-3 text-left">Expire Date</th>
											<th class="px-4 py-3 text-left">Value</th>
											<th class="px-4 py-3 text-left">Type</th>
											<th class="px-4 py-3 text-center no-export">Status</th>
											<th class="px-4 py-3 text-right no-export">Action</th>
										</tr>
									</thead>
									<tbody id="obn-coupons-table-body" class="bg-white divide-y divide-gray-200">
										<!-- AJAX Loaded -->
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- 6. Edit Master Coupon View -->
					<div id="obn-view-coupon-edit" class="obn-view-section">
						<div class="obn-card p-6 !pt-4">
							<h3 class="text-2xl font-bold text-gray-800 mb-6">Edit Master Coupon</h3>
							<form id="obn-coupon-edit-form" class="space-y-5 max-w-2xl">
								<input type="hidden" name="action" value="obn_update_coupon">
								<input type="hidden" name="security" value="<?php echo esc_attr($coupon_nonce); ?>">
								<input type="hidden" name="id" id="obn_edit_coupon_id">

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Occasion Name</label>
									<input type="text" name="occasion_name" id="obn_edit_coupon_name"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"
										required>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Expire Date</label>
									<input type="date" name="expire_date" id="obn_edit_coupon_date"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"
										required>
								</div>

								<div class="grid grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Value</label>
										<input type="number" name="coupon_value" id="obn_edit_coupon_value" step="0.01"
											class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"
											required>
									</div>
									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
										<select name="coupon_type" id="obn_edit_coupon_type"
											class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500">
											<option value="Percentage">Percentage (%)</option>
											<option value="Fixed">Fixed Amount</option>
										</select>
									</div>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
									<textarea name="description" id="obn_edit_coupon_desc" rows="3"
										class="w-full px-4 py-3 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500"></textarea>
								</div>

								<div class="flex gap-2 pt-4">
									<button type="submit"
										class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl">Update
										Master Coupon</button>
									<button type="button"
										class="obn-coupon-cancel px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold rounded-xl">Cancel</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Assets Sections start here-->
					<div id="obn-view-asset-list" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-list.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-list.php'; ?>
					</div>
					<div id="obn-view-asset-category" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-category.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-category.php'; ?>
					</div>
					<div id="obn-view-asset-add" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-add.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-add.php'; ?>
					</div>
					<div id="obn-view-asset-disposal-list" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-disposal-list.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-disposal-list.php'; ?>
					</div>
					<div id="obn-view-asset-edit" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-edit.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/asset-edit.php'; ?>
					</div>

					<div id="obn-view-depreciation-methods" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/depreciation-methods.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/assets/depreciation-methods.php'; ?>
					</div>
					<!-- Assets Sections end here -->

					<!--Reimbursement section start here-->
					<div id="obn-view-reimbursement-approvals" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/reimbursements/reimbursement-approvals.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/reimbursements/reimbursement-approvals.php'; ?>
					</div>
					<div id="obn-view-reimbursement-add" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/reimbursements/reimbursement-add.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/reimbursements/reimbursement-add.php'; ?>
					</div>
					<div id="obn-view-reimbursement-list" class="obn-view-section">
						<?php if (file_exists(OBN_ACCOUNTING_PLUGIN_DIR . 'templates/reimbursements/reimbursement-list.php'))
							include OBN_ACCOUNTING_PLUGIN_DIR . 'templates/reimbursements/reimbursement-list.php'; ?>
					</div>
					<!--Reimbursement section end here-->

				</div>
			</div>
		</div>
		<style>
			.obn-submenu {
				display: none;
				list-style: none;
				margin: 0;
				padding-left: 0.75rem;
			}

			.obn-submenu li {
				margin: 0.25rem 0;
			}

			.obn-submenu.show {
				display: block;
			}

			.obn-caret {
				float: right;
				font-size: 0.85em;
				opacity: 0.8;
			}

			.obn-nav-link,
			.obn-subnav-link {
				display: block;
				padding: 0.5rem 0.5rem;
				color: inherit;
				text-decoration: none;
			}

			.obn-nav-link.active {
				font-weight: 600;
			}

			.obn-subnav-link.active {
				font-weight: 600;
				padding-left: 0.5rem;
			}

			/* Compact table sizing for Payment Types list */
			#obn-view-setting-payment-types-list .overflow-x-auto {
				max-width: 1100px;
				margin: 0 auto;
			}

			#obn-view-setting-payment-types-list table {
				table-layout: fixed;
				width: 100%;
			}

			#obn-view-setting-payment-types-list th,
			#obn-view-setting-payment-types-list td {
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			#obn-view-setting-payment-types-list th:nth-child(1),
			#obn-view-setting-payment-types-list td:nth-child(1) {
				width: 60px;
			}

			#obn-view-setting-payment-types-list th:nth-child(2),
			#obn-view-setting-payment-types-list td:nth-child(2) {
				width: calc(100% - 320px);
			}

			#obn-view-setting-payment-types-list th:nth-child(3),
			#obn-view-setting-payment-types-list td:nth-child(3) {
				width: 120px;
				text-align: center;
			}

			#obn-view-setting-payment-types-list th:nth-child(4),
			#obn-view-setting-payment-types-list td:nth-child(4) {
				width: 140px;
				text-align: right;
			}

			@media (max-width: 900px) {
				#obn-view-setting-payment-types-list .overflow-x-auto {
					max-width: 100%;
				}

				#obn-view-setting-payment-types-list th:nth-child(2),
				#obn-view-setting-payment-types-list td:nth-child(2) {
					width: auto;
				}
			}

			/* Multi-value input component styles */
			.multi-value-input-container {
				position: relative;
				width: 100%;
			}

			.multi-value-input-wrapper {
				position: relative;
				border: 1px solid #d1d5db !important;
				border-radius: 0.375rem !important;
				background: white !important;
				min-height: 42px !important;
				padding: 4px !important;
				display: flex !important;
				flex-wrap: wrap !important;
				align-items: center !important;
				gap: 4px !important;
				width: 100% !important;
				box-sizing: border-box !important;
			}

			.multi-value-input-wrapper:focus-within {
				border-color: #1569B3 !important;
				outline: none !important;
				box-shadow: 0 0 0 3px rgba(21, 105, 179, 0.12) !important;
			}

			.multi-value-input {
				border: none !important;
				outline: none !important;
				background: transparent !important;
				flex: 1 !important;
				min-width: 120px !important;
				padding: 6px 4px !important;
				font-size: 14px !important;
				line-height: 1.5 !important;
				height: auto !important;
				margin: 0 !important;
				box-shadow: none !important;
			}

			.multi-value-input::placeholder {
				color: #9ca3af !important;
			}

			.multi-value-tags {
				display: flex !important;
				flex-wrap: wrap !important;
				gap: 4px !important;
				align-items: center !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			.multi-value-tag {
				display: inline-flex !important;
				align-items: center !important;
				gap: 6px !important;
				background: #eff6ff !important;
				color: #1d4ed8 !important;
				border: 1px solid #bfdbfe !important;
				border-radius: 9999px !important;
				padding: 4px 8px !important;
				font-size: 13px !important;
				font-weight: 500 !important;
				transition: all 0.2s ease !important;
				animation: tagFadeIn 0.2s ease-out !important;
				margin: 0 !important;
			}

			.multi-value-tag:hover {
				background: #dbeafe !important;
				border-color: #93c5fd !important;
			}

			.multi-value-tag .remove-tag {
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				width: 16px !important;
				height: 16px !important;
				border-radius: 50% !important;
				background: #f87171 !important;
				color: white !important;
				border: none !important;
				cursor: pointer !important;
				font-size: 12px !important;
				font-weight: bold !important;
				line-height: 1 !important;
				transition: all 0.2s ease !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			.multi-value-tag .remove-tag:hover {
				background: #ef4444 !important;
				transform: scale(1.1) !important;
			}

			@keyframes tagFadeIn {
				from {
					opacity: 0;
					transform: scale(0.8);
				}

				to {
					opacity: 1;
					transform: scale(1);
				}
			}

			/* Ensure the wrapper is visible */
			#payment-types-container .multi-value-input-wrapper {
				display: flex !important;
				visibility: visible !important;
				opacity: 1 !important;
			}
		</style>
		<script>
			/*
			// Redundant Vanilla JS Navigation - Handled by assets/js/script.js
			(function(){
				var navLinks = document.querySelectorAll('.obn-sidebar .obn-nav-link');
				var subLinks = document.querySelectorAll('.obn-sidebar .obn-subnav-link');
	
				function clearActive() {
					document.querySelectorAll('.obn-sidebar .active').forEach(function(el){ el.classList.remove('active'); });
				}
	
				navLinks.forEach(function(link){
					var parentLi = link.parentElement;
					var submenu = parentLi ? parentLi.querySelector('.obn-submenu') : null;
					link.addEventListener('click', function(e){
						e.preventDefault();
						// If this link has a submenu, toggle it
						if (submenu) {
							submenu.classList.toggle('show');
							link.classList.toggle('active');
							return;
						}
						// Otherwise switch to a top-level view
						clearActive();
						link.classList.add('active');
						var target = link.getAttribute('data-target');
						if (target) {
							document.querySelectorAll('.obn-view-section').forEach(function(sec){ sec.classList.remove('active'); });
							var show = document.getElementById('obn-view-' + target);
							if (show) show.classList.add('active');
						}
					});
				});
	
				subLinks.forEach(function(slink){
					slink.addEventListener('click', function(e){
						e.preventDefault();
						clearActive();
						// mark parent nav as active too
						var parentNav = slink.closest('li').parentElement.closest('li');
						if (parentNav) {
							var pnavLink = parentNav.querySelector('.obn-nav-link');
							if (pnavLink) pnavLink.classList.add('active');
						}
						slink.classList.add('active');
						var target = slink.getAttribute('data-target');
						if (target) {
							document.querySelectorAll('.obn-view-section').forEach(function(sec){ sec.classList.remove('active'); });
							var show = document.getElementById('obn-view-' + target);
							if (!show) show = document.getElementById('obn-view-' + target.split('-')[0]);
							if (show) show.classList.add('active');
						}
					});
				});
			})();
			*/
		</script>
		<script type="text/javascript">
			// Ensure obn_ajax is defined for inline scripts in case footer scripts haven't loaded yet
			if (typeof obn_ajax === 'undefined') {
				var obn_ajax = {
					ajax_url: '<?php echo get_admin_url(get_current_blog_id(), "admin-ajax.php"); ?>',
					nonce: '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>',
					auth_nonce: '<?php echo wp_create_nonce("obn_auth_nonce"); ?>',
					expense_nonce: '<?php echo wp_create_nonce("obn_expense_action_nonce"); ?>',
					je_nonce: '<?php echo wp_create_nonce("obn_je_action_nonce"); ?>',
					permissions_nonce: '<?php echo wp_create_nonce("obn_permissions_nonce"); ?>'
				};
			}

			jQuery(document).ready(function ($) {

				function showView(id) {
					id = (id || '').replace(/^#/, '');
					if (id.indexOf('obn-view-') !== 0) {
						id = 'obn-view-' + id;
					}

					$('.obn-view-section').removeClass('active').hide();
					$('#' + id).removeClass('hidden').stop(true, true).fadeIn(200).addClass('active');
					// scroll to top of content area for visibility
					$('.obn-main-content').scrollTop(0);
				}
				window.showView = showView;
				window.obn_switch_view = function (slug) {
					slug = (slug || '').replace(/^obn-view-/, '');
					if (!slug) {
						return;
					}

					window.location.hash = 'view=' + slug;
					localStorage.setItem('obn_active_view', slug);
					showView(slug);
				};

				// If a view was requested after a reload, honor it and then clear the flag
				var _afterReloadView = localStorage.getItem('obn-after-reload-view');
				if (_afterReloadView) {
					showView(_afterReloadView);
					localStorage.removeItem('obn-after-reload-view');
					localStorage.removeItem('obn_active_view');
				}

				// Open Add view from list
				$(document).on('click', '#obn-show-currency-add', function (e) {
					e.preventDefault();
					// clear add form
					$('#obn-currency-add-form').trigger('reset');
					showView('obn-view-setting-currency-add');
				});

				// Cancel buttons
				$('#obn-currency-add-cancel, #obn-currency-edit-cancel').on('click', function () {
					showView('obn-view-setting-currency-list');
				});

				// Add form submit
				$(document).on('submit', '#obn-currency-add-form', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-currency-add-save').prop('disabled', true).text('Saving...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert('âœ… ' + response.data.message);
							// ensure we return to Currency list after reload
							localStorage.setItem('obn-after-reload-view', 'obn-view-setting-currency-list');
							location.reload();
						} else {
							alert('âŒ ' + (response.data || 'Insert failed.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); }).always(function () { $('#obn-currency-add-save').prop('disabled', false).text('Save Currency'); });
				});

				// Edit: delegate (table may be dynamic)
				$(document).on('click', '.obn-edit-currency', function () {
					var btn = $(this);
					var id = btn.data('id');
					var name = btn.data('name');
					var code = btn.data('code');
					var symbol = btn.data('symbol');

					// populate edit form
					$('#obn_edit_currency_id').val(id);
					$('#obn_edit_currency_name').val(name);
					$('#obn_edit_currency_code').val(code);
					$('#obn_edit_symbol').val(symbol);

					showView('obn-view-setting-currency-edit');
				});

				// Edit form submit
				$('#obn-currency-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-currency-edit-save').prop('disabled', true).text('Updating...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert('âœ… ' + response.data.message);
							// ensure we return to Currency list after reload
							localStorage.setItem('obn-after-reload-view', 'obn-view-setting-currency-list');
							location.reload();
						} else {
							alert('âŒ ' + (response.data || 'Update failed.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); }).always(function () { $('#obn-currency-edit-save').prop('disabled', false).text('Update Currency'); });
				});

				// Delete (delegate)
				$(document).on('click', '.obn-delete-currency', function () {
					if (!confirm('Are you sure you want to delete this currency?')) return;
					var id = $(this).data('id');
					$.post(obn_ajax.ajax_url, { action: 'obn_delete_currency', id: id, security: obn_ajax.nonce }, function (response) {
						if (response.success) {
							alert('âœ… ' + response.data.message);
							// ensure we return to Currency list after reload
							localStorage.setItem('obn-after-reload-view', 'obn-view-setting-currency-list');
							location.reload();
						} else { alert('âŒ ' + (response.data || 'Delete failed.')); }
					}).fail(function () { alert('âŒ Request failed.'); });
				});

				// Toggle status (delegate)
				$(document).on('change', '.obn-toggle-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.data('status');
					var table = '<?php echo esc_js($wpdb->prefix . "orabooks_db_currency"); ?>';
					var nonce = cb.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_status', table: table, id: id, status: status, security: nonce }, function (response) {
						if (response.success) {
							cb.data('status', response.data.new_status);
						} else {
							alert('âŒ ' + (response.data || 'Toggle failed.'));
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('âŒ Request failed.'); cb.prop('checked', !cb.prop('checked')); });
				});

				// Tax UI handlers
				$('#obn-show-tax-add').on('click', function () {
					$('#obn-tax-add-form')[0].reset();
					showView('obn-view-setting-tax-add');
				});

				// Tax cancel buttons
				$('#obn-tax-add-cancel, #obn-tax-edit-cancel').on('click', function () {
					showView('obn-view-setting-tax-list');
				});

				// Tax add submit
				$('#obn-tax-add-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-tax-add-save').prop('disabled', true).text('Saving...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert('âœ… ' + response.data.message);
							// ensure we return to Tax list after reload
							localStorage.setItem('obn-after-reload-view', 'obn-view-setting-tax-list');
							location.reload();
						} else {
							alert('âŒ ' + (response.data || 'Insert failed.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); }).always(function () { $('#obn-tax-add-save').prop('disabled', false).text('Save Tax'); });
				});


				// Edit tax - populate and show
				$(document).on('click', '.obn-edit-tax', function () {
					var btn = $(this);
					var id = btn.data('id');
					var name = btn.data('name');
					var rate = btn.data('rate');

					$('#obn_edit_tax_id').val(id);
					$('#obn_edit_tax_name').val(name);
					$('#obn_edit_tax_rate').val(rate);

					showView('obn-view-setting-tax-edit');
				});

				// Tax edit submit
				$('#obn-tax-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-tax-edit-save').prop('disabled', true).text('Updating...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert('âœ… ' + response.data.message);
							// keep Tax list open after reload
							localStorage.setItem('obn-after-reload-view', 'obn-view-setting-tax-list');
							location.reload();
						} else {
							alert('âŒ ' + (response.data || 'Update failed.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); }).always(function () { $('#obn-tax-edit-save').prop('disabled', false).text('Update Tax'); });
				});

				// Delete tax
				$(document).on('click', '.obn-delete-tax', function () {
					if (!confirm('Are you sure you want to delete this tax?')) return;
					var id = $(this).data('id');
					$.post(obn_ajax.ajax_url, { action: 'obn_delete_tax', id: id, security: obn_ajax.nonce }, function (response) {
						if (response.success) {
							alert('âœ… ' + response.data.message);
							localStorage.setItem('obn-after-reload-view', 'obn-view-setting-tax-list');
							location.reload();
						} else {
							alert('âŒ ' + (response.data || 'Delete failed.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); });
				});

				// Toggle tax status
				$(document).on('change', '.obn-toggle-tax-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.data('status');
					var table = '<?php echo esc_js($wpdb->prefix . "orabooks_db_tax"); ?>';
					var nonce = cb.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_tax_status', table: table, id: id, status: status, security: nonce }, function (response) {
						if (response.success) {
							cb.data('status', response.data.new_status);
						} else {
							alert('âŒ ' + (response.data || 'Toggle failed.'));
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('âŒ Request failed.'); cb.prop('checked', !cb.prop('checked')); });
				});

				// ===Payment types handlers========-------
				$('#obn-payment-add-cancel, #obn-payment-edit-cancel').on('click', function () {
					showView('obn-view-setting-payment-types-list');
				});

				// =============Add payment type===============-------------
				// Logic moved inside initMultiValueInput for better state management

				// Edit payment - populate
				$(document).on('click', '.obn-edit-payment', function () {
					var btn = $(this);
					$('#obn_edit_payment_id').val(btn.data('id'));
					$('#obn_edit_payment_type').val(btn.data('name'));
					showView('obn-view-setting-payment-types-edit');
				});

				// Update payment
				$('#obn-payment-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-payment-edit-save').prop('disabled', true).text('Updating...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn-after-reload-view', 'obn-view-setting-payment-types-list');
							location.reload();
						} else {
							alert((response.data || 'Update failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { $('#obn-payment-edit-save').prop('disabled', false).text('Update'); });
				});

				// Multi-value input component functionality
				function initPaymentTypeInput() {
					var containerId = 'payment-types-container';
					var hiddenInputId = 'payment-type-hidden';
					var formId = 'obn-payment-add-form';

					var $container = $('#' + containerId);
					var $input = $('#' + containerId + ' input[type="text"]');
					var $tagsContainer = $('#' + containerId + ' #payment-type-tags');
					var $hiddenInput = $('#' + hiddenInputId);
					var $form = $('#' + formId);
					var tags = [];

					if ($container.length === 0) return;

					// Reset state
					function reset() {
						tags = [];
						$tagsContainer.empty();
						$hiddenInput.val('');
						$input.val('');
					}

					function addTag(value) {
						value = value.trim();
						if (!value || tags.includes(value.toLowerCase())) {
							return false;
						}

						var $tag = $('<div style="display: inline-flex; align-items: center; gap: 6px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 9999px; padding: 4px 8px; font-size: 13px; font-weight: 500; margin: 0;">' +
							'<span>' + value + '</span>' +
							'<button type="button" style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #f87171; color: white; border: none; cursor: pointer; font-size: 12px; font-weight: bold; line-height: 1; margin: 0; padding: 0;">×</button>' +
							'</div>');

						$tag.find('button').on('click', function () {
							removeTag(value.toLowerCase());
						});

						$tagsContainer.append($tag);
						tags.push(value.toLowerCase());
						updateHiddenInput();
						return true;
					}

					function removeTag(value) {
						var index = tags.indexOf(value);
						if (index > -1) {
							tags.splice(index, 1);
							$tagsContainer.find('div').each(function () {
								if ($(this).find('span').text().trim().toLowerCase() === value) {
									$(this).remove();
								}
							});
							updateHiddenInput();
						}
					}

					function updateHiddenInput() {
						var displayValues = [];
						$tagsContainer.find('span').each(function () {
							displayValues.push($(this).text().trim());
						});
						$hiddenInput.val(JSON.stringify(displayValues));
					}

					$input.off('keydown keyup').on('keydown keyup', function (e) {
						var value = $input.val().trim();
						if (e.type === 'keydown') {
							if (e.keyCode === 13) {
								e.preventDefault();
								if (addTag(value)) $input.val('');
							} else if (e.keyCode === 8 && !value && tags.length > 0) {
								e.preventDefault();
								var lastValue = $tagsContainer.find('div').last().find('span').text().trim().toLowerCase();
								removeTag(lastValue);
							}
						} else if (e.type === 'keyup') {
							if (value && (value.endsWith(',') || value.endsWith(' '))) {
								if (addTag(value.slice(0, -1))) $input.val('');
							}
						}
					});

					$input.off('keypress').on('keypress', function (e) {
						if (e.keyCode === 13) e.preventDefault();
					});

					$form.off('submit').on('submit', function (e) {
						e.preventDefault();

						// Add pending text as tag before submitting
						var pendingVal = $input.val().trim();
						if (pendingVal) {
							addTag(pendingVal);
							$input.val('');
						}

						var paymentTypes = $hiddenInput.val();
						if (!paymentTypes || paymentTypes === '[]' || paymentTypes === '') {
							alert('Please add at least one payment type.');
							return;
						}

						$('#obn-payment-add-save').prop('disabled', true).text('Saving...');
						$.post(obn_ajax.ajax_url, $form.serialize(), function (response) {
							if (response.success) {
								alert(response.data.message);
								localStorage.setItem('obn-after-reload-view', 'obn-view-setting-payment-types-list');
								location.reload();
							} else {
								alert((response.data || 'Insert failed.'));
							}
						}).fail(function () { alert('Request failed.'); }).always(function () { $('#obn-payment-add-save').prop('disabled', false).text('Save'); });
					});

					return { reset: reset };
				}

				var paymentInputManager = null;

				// Initialize multi-value input for payment types
				$('#obn-show-payment-add').on('click', function () {
					showView('obn-view-setting-payment-types-add');

					if (!paymentInputManager) {
						paymentInputManager = initPaymentTypeInput();
					} else {
						paymentInputManager.reset();
					}
				});

				// Delete payment
				$(document).on('click', '.obn-delete-payment', function () {
					if (!confirm('Are you sure you want to delete this payment type?')) return;
					var id = $(this).data('id');
					$.post(obn_ajax.ajax_url, { action: 'obn_delete_payment_type', id: id, security: obn_ajax.nonce }, function (response) {
						if (response.success) { alert('âœ… ' + response.data.message); localStorage.setItem('obn-after-reload-view', 'obn-view-setting-payment-types-list'); location.reload(); } else { alert('âŒ ' + (response.data || 'Delete failed.')); }
					}).fail(function () { alert('Request failed.'); });
				});

				// Toggle payment status
				$(document).on('change', '.obn-toggle-payment-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.data('status');
					var table = '<?php echo esc_js($wpdb->prefix . "orabooks_db_paymenttypes"); ?>';
					var nonce = cb.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_payment_status', table: table, id: id, status: status, security: nonce }, function (response) {
						if (response.success) {
							cb.data('status', response.data.new_status);
						} else {
							alert((response.data || 'Toggle failed.'));
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('Request failed.'); cb.prop('checked', !cb.prop('checked')); });
				});

				// Accounts handlers (toggle status + delete)
				$(document).on('change', '.obn-toggle-account-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.is(':checked') ? 1 : 0;
					var nonce = cb.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_accounts_status', id: id, status: status, security: nonce }, function (response) {
						if (!response.success) {
							alert('Failed to update status.');
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('AJAX error while updating status.'); cb.prop('checked', !cb.prop('checked')); });
				});

				$(document).on('click', '.obn-delete-account', function () {
					if (!confirm('Are you sure you want to delete this account?')) return;
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_delete_account', id: id, security: nonce }, function (response) {
						if (response.success) {
							alert((response.data.message || 'Account deleted.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-accounts');
							location.reload();
						} else {
							alert((response.data || 'Delete failed.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// Show Add / Edit views
				$(document).on('click', '#obn-show-account-add', function () {
					$('#obn-account-add-form').trigger('reset');
					showView('obn-view-accounts-add');
				});

				$(document).on('click', '#obn-account-add-cancel, #obn-account-edit-cancel', function () {
					showView('obn-view-accounts');
				});

				// Add account submit
				$(document).on('submit', '#obn-account-add-form', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-account-add-save').prop('disabled', true).text('Saving...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn_active_view', 'accounts');
							window.location.hash = 'view=accounts';
							location.reload();
						} else {
							alert((response.data || 'Insert failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { $('#obn-account-add-save').prop('disabled', false).text('Add Account'); });
				});

				// Edit account - populate and show
				$(document).on('click', '.obn-edit-account', function () {
					var id = $(this).data('id');
					// find row data
					var row = $(this).closest('tr');
					var parentName = row.find('td').eq(1).text().trim();
					var code = row.find('td').eq(2).text().trim();
					var name = row.find('td').eq(3).text().trim();
					var balance = row.find('td').eq(4).text().trim().replace(/,/g, '');
					var note = row.find('td').eq(5).text().trim();
					// populate
					$('#obn_edit_account_id').val(id);
					$('#obn_edit_account_code').val(code);
					$('#obn_edit_account_name').val(name);
					$('#obn_edit_opening_balance').val(balance);
					$('#obn_edit_note').val(note);
					// select parent by matching text (best-effort)
					$('#obn_edit_parent_account option').filter(function () { return $(this).text().trim() === parentName; }).prop('selected', true);
					showView('obn-view-accounts-edit');
				});

				// Edit account submit
				$('#obn-account-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-account-edit-save').prop('disabled', true).text('Updating...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn_active_view', 'accounts');
							window.location.hash = 'view=accounts';
							location.reload();
						} else {
							alert((response.data || 'Update failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { $('#obn-account-edit-save').prop('disabled', false).text('Update Account'); });
				});

				// Deposits handlers
				$('#obn-show-deposit-add').on('click', function () {
					$('#obn-deposit-add-form')[0].reset();
					showView('obn-view-accounts-deposit-add');
				});

				$('#obn-deposit-add-cancel, #obn-deposit-edit-cancel').on('click', function () {
					showView('obn-view-accounts-deposit');
				});

				$('#obn-deposit-add-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-deposit-add-save').prop('disabled', true).text('Saving...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn-after-reload-view', 'obn-view-accounts-deposit');
							location.reload();
						} else {
							alert((response.data || 'Insert failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { $('#obn-deposit-add-save').prop('disabled', false).text('Add Deposit'); });
				});

				// Edit deposit - fetch and populate
				$(document).on('click', '.obn-edit-deposit', function () {
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');
					btn.prop('disabled', true);
					$.post(obn_ajax.ajax_url, { action: 'obn_get_deposit', id: id, security: nonce }, function (response) {
						if (response.success) {
							var d = response.data;
							$('#obn_edit_deposit_id').val(d.id);
							$('#obn_edit_deposit_date').val(d.deposit_date);
							$('#obn_edit_reference_no').val(d.reference_no);
							$('#obn_edit_debit_ac').val(d.debit_account_id);
							$('#obn_edit_credit_ac').val(d.credit_account_id);
							$('#obn_edit_deposit_amount').val(d.amount);
							$('#obn_edit_deposit_note').val(d.note);
							showView('obn-view-accounts-deposit-edit');
						} else {
							alert((response.data || 'Failed to fetch deposit.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { btn.prop('disabled', false); });
				});

				// Update deposit
				$('#obn-deposit-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-deposit-edit-save').prop('disabled', true).text('Updating...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn-after-reload-view', 'obn-view-accounts-deposit');
							location.reload();
						} else {
							alert((response.data || 'Update failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { $('#obn-deposit-edit-save').prop('disabled', false).text('Update Deposit'); });
				});

				// Delete deposit
				$(document).on('click', '.obn-delete-deposit', function () {
					if (!confirm('Are you sure you want to delete this deposit?')) return;
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_delete_deposit', id: id, security: nonce }, function (response) {
						if (response.success) {
							alert((response.data.message || 'Deposit deleted.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-accounts-deposit');
							location.reload();
						} else {
							alert((response.data || 'Delete failed.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// Toggle deposit status
				$(document).on('change', '.obn-toggle-deposit-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.is(':checked') ? 1 : 0;
					var nonce = cb.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_deposit_status', id: id, status: status, security: nonce }, function (response) {
						if (!response.success) {
							alert('Failed to update status.');
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('AJAX error while updating status.'); cb.prop('checked', !cb.prop('checked')); });
				});

				// Fiscal Year handlers
				// Fiscal Year Search (Simple jQuery)
				$('#obn-fiscal-year-search').on('keyup', function () {
					var value = $(this).val().toLowerCase();
					$('#obn-fiscal-year-table tbody tr').not(':has(td[colspan])').filter(function () {
						$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
					});
				});

				$(document).on('click', '#obn-show-fiscal-year-add', function (e) {
					e.preventDefault();
					$('#obn-fiscal-year-add-form').trigger('reset');
					showView('obn-view-fiscal-year-add');
				});

				$(document).on('click', '#obn-fiscal-year-add-cancel, #obn-fiscal-year-edit-cancel', function () {
					showView('obn-view-fiscal-year-list');
				});

				$(document).on('submit', '#obn-fiscal-year-add-form', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-fiscal-year-add-save').prop('disabled', true).text('Saving...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn-after-reload-view', 'obn-view-fiscal-year-list');
							location.reload();
						} else {
							alert((response.data || 'Insert failed.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); }).always(function () { $('#obn-fiscal-year-add-save').prop('disabled', false).text('Add Fiscal Year'); });
				});

				$(document).on('click', '.obn-edit-fiscal-year', function () {
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');
					btn.prop('disabled', true);
					$.post(obn_ajax.ajax_url, { action: 'obn_get_fiscal_year', id: id, security: nonce }, function (response) {
						if (response.success) {
							var fy = response.data;
							$('#obn_edit_fiscal_year_id').val(fy.id);
							$('#obn_edit_fiscal_year_name').val(fy.fiscal_year_name);
							$('#obn_edit_start_date').val(fy.start_date);
							$('#obn_edit_end_date').val(fy.end_date);
							$('#obn_edit_fy_description').val(fy.description);
							if (fy.status == 1) {
								$('#obn_edit_fy_status').prop('checked', true);
							} else {
								$('#obn_edit_fy_status').prop('checked', false);
							}
							showView('obn-view-fiscal-year-edit');
						} else {
							alert((response.data || 'Failed to fetch fiscal year.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); }).always(function () { btn.prop('disabled', false); });
				});

				$(document).on('submit', '#obn-fiscal-year-edit-form', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-fiscal-year-edit-save').prop('disabled', true).text('Updating...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn-after-reload-view', 'obn-view-fiscal-year-list');
							location.reload();
						} else {
							alert((response.data || 'Update failed.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); }).always(function () { $('#obn-fiscal-year-edit-save').prop('disabled', false).text('Update Fiscal Year'); });
				});

				$(document).on('click', '.obn-delete-fiscal-year', function () {
					if (!confirm('Are you sure you want to delete this fiscal year?')) return;
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_delete_fiscal_year', id: id, security: nonce }, function (response) {
						if (response.success) {
							alert((response.data.message || 'Fiscal year deleted.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-fiscal-year-list');
							location.reload();
						} else {
							alert((response.data || 'Delete failed.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				$(document).on('change', '.obn-toggle-fiscal-year-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.is(':checked') ? 1 : 0;
					var nonce = cb.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_fiscal_year_status', id: id, status: status, security: nonce }, function (response) {
						if (!response.success) {
							alert('Failed to update status.');
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('AJAX error while updating status.'); cb.prop('checked', !cb.prop('checked')); });
				});

				// Advances handlers
				$('#obn-show-advance-add').on('click', function () {
					$('#obn-advance-add-form')[0].reset();
					showView('obn-view-advance-add');
				});

				$('#obn-advance-add-cancel, #obn-advance-edit-cancel').on('click', function () {
					showView('obn-view-advance-list');
				});

				$('#obn-advance-add-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-advance-add-save').prop('disabled', true).text('Saving...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn-after-reload-view', 'obn-view-advance-list');
							location.reload();
						} else {
							alert((response.data || 'Insert failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { $('#obn-advance-add-save').prop('disabled', false).text('Add Advance'); });
				});

				// Edit advance - fetch and populate
				$(document).on('click', '.obn-edit-advance', function () {
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');
					btn.prop('disabled', true);
					$.post(obn_ajax.ajax_url, { action: 'obn_get_advance', id: id, security: nonce }, function (response) {
						if (response.success) {
							var a = response.data;
							$('#obn_edit_advance_id').val(a.id);
							$('#obn_edit_advance_date').val(a.payment_date);
							$('#obn_edit_advance_customer').val(a.customer_id);
							$('#obn_edit_advance_amount').val(a.amount);
							$('#obn_edit_advance_payment_type').val(a.payment_type);
							$('#obn_edit_advance_note').val(a.note);
							showView('obn-view-advance-edit');
						} else {
							alert((response.data || 'Failed to fetch advance.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { btn.prop('disabled', false); });
				});

				// Update advance
				$('#obn-advance-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var data = form.serialize();
					$('#obn-advance-edit-save').prop('disabled', true).text('Updating...');
					$.post(obn_ajax.ajax_url, data, function (response) {
						if (response.success) {
							alert(response.data.message);
							localStorage.setItem('obn-after-reload-view', 'obn-view-advance-list');
							location.reload();
						} else {
							alert((response.data || 'Update failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { $('#obn-advance-edit-save').prop('disabled', false).text('Update Advance'); });
				});

				// Delete advance
				$(document).on('click', '.obn-delete-advance', function () {
					if (!confirm('Are you sure you want to delete this advance?')) return;
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_delete_advance', id: id, security: nonce }, function (response) {
						if (response.success) {
							alert((response.data.message || 'Advance deleted.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-advance-list');
							location.reload();
						} else {
							alert((response.data || 'Delete failed.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// Toggle advance status
				$(document).on('change', '.obn-toggle-advance-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.is(':checked') ? 1 : 0;
					var nonce = cb.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_advance_status', id: id, status: status, security: nonce }, function (response) {
						if (!response.success) {
							alert('Failed to update status.');
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('AJAX error while updating status.'); cb.prop('checked', !cb.prop('checked')); });
				});

				// ==================== COUPON MASTER HANDLERS ====================

				// Load Master Coupons
				function loadMasterCoupons() {
					var searchTerm = $('#obn_master_search').val();
					var filterType = $('#obn_master_filter_type').val();
					var filterStatus = $('#obn_master_filter_status').val();
					var nonce = $('#obn_master_search_nonce').val() || obn_ajax.nonce;

					$.post(obn_ajax.ajax_url, {
						action: 'obn_search_coupons_master',
						security: nonce,
						search_term: searchTerm,
						filter_type: filterType,
						filter_status: filterStatus
					}, function (response) {
						$('#obn-coupons-table-body').html(response);
					});
				}

				// Search Btn
				$('#obn_master_search_btn').on('click', function () {
					loadMasterCoupons();
				});

				// Initial Load if view is active
				if ($('#obn-view-coupon-master').hasClass('active')) {
					loadMasterCoupons();
				}

				// Show Create Coupon View
				$('.obn-show-coupon-create').on('click', function (e) {
					e.preventDefault();
					$('.obn-view-section').hide();
					$('#obn-view-coupon-create').show();
				});

				// Cancel / Back to List
				$('.obn-coupon-cancel').on('click', function () {
					$('.obn-view-section').hide();
					$('#obn-view-coupon-master').show();
					loadMasterCoupons();
				});

				// Create Coupon Submit
				$('#obn-coupon-create-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Saving...');

					$.post(obn_ajax.ajax_url, form.serialize(), function (response) {
						if (response.success) {
							alert((response.data.message || 'Coupon created successfully.'));
							form[0].reset();
							localStorage.setItem('obn-after-reload-view', 'obn-view-coupon-master');
							location.reload();
						} else {
							alert((response.data || 'Failed to create coupon.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { btn.prop('disabled', false).text(originalText); });
				});

				// Edit Coupon - Fetch & Populate
				$(document).on('click', '.obn-coupon-edit', function () {
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_get_coupon', id: id, security: nonce }, function (response) {
						if (response.success) {
							var c = response.data;
							$('#obn_edit_coupon_id').val(c.id);
							$('#obn_edit_coupon_name').val(c.name);
							$('#obn_edit_coupon_date').val(c.expire_date);
							$('#obn_edit_coupon_value').val(c.value);
							$('#obn_edit_coupon_type').val(c.type);
							$('#obn_edit_coupon_desc').val(c.description);

							$('.obn-view-section').hide();
							$('#obn-view-coupon-edit').show();
						} else {
							alert((response.data || 'Failed to fetch coupon details.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// Update Coupon Submit
				$('#obn-coupon-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Updating...');

					$.post(obn_ajax.ajax_url, form.serialize(), function (response) {
						if (response.success) {
							alert((response.data.message || 'Coupon updated successfully.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-coupon-master');
							location.reload();
						} else {
							alert((response.data || 'Failed to update coupon.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { btn.prop('disabled', false).text(originalText); });
				});

				// Delete Coupon
				$(document).on('click', '.obn-coupon-delete', function () {
					if (!confirm('Are you sure you want to delete this coupon?')) return;

					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_delete_coupon', id: id, security: nonce }, function (response) {
						if (response.success) {
							alert((response.data || 'Coupon deleted.'));
							loadMasterCoupons();
						} else {
							alert((response.data || 'Failed to delete coupon.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); });
				});

				// Toggle Coupon Status
				$(document).on('change', '.obn-toggle-coupon-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.is(':checked') ? 1 : 0;
					var nonce = cb.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_coupon_status', id: id, status: status, security: nonce }, function (response) {
						if (!response.success) {
							alert('Failed to update status.');
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('âš ï¸ Network error.'); cb.prop('checked', !cb.prop('checked')); });
				});


				// ==================== CUSTOMER COUPON HANDLERS ====================

				// Show Create Customer Coupon
				$('.obn-show-coupon-customer-create').on('click', function (e) {
					e.preventDefault();
					$('.obn-view-section').hide();
					$('#obn-view-coupon-create-customer').show();
				});

				// Back to Customer Coupon List
				$('.obn-show-coupon-customer-list').on('click', function (e) {
					e.preventDefault();
					$('.obn-view-section').hide();
					$('#obn-view-coupon-customer-list').show();
					loadCustomerCoupons();
				});

				// Helper: Load Customer Coupons
				function loadCustomerCoupons() {
					var searchTerm = $('#obn_cc_search').val();
					var filterCust = $('#obn_cc_filter_customer').val();
					var filterType = $('#obn_cc_filter_type').val();
					var filterStatus = $('#obn_cc_filter_status').val();
					var nonce = $('#obn_cc_search_nonce').val();

					$.post(obn_ajax.ajax_url, {
						action: 'obn_search_customer_coupons',
						security: nonce,
						search_term: searchTerm,
						filter_customer: filterCust,
						filter_type: filterType,
						filter_status: filterStatus
					}, function (response) {
						$('#obn-customer-coupons-table-body').html(response);
					});
				}

				// Search Cust Coupon
				$('#obn_cc_search_btn').on('click', function () {
					loadCustomerCoupons();
				});

				// Initial Load if view is active
				if ($('#obn-view-coupon-customer-list').hasClass('active')) {
					loadCustomerCoupons();
				}

				// Create Customer Coupon - Generate Code
				$('#obn_cc_generate').on('click', function () {
					var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
					var code = 'CPN-';
					for (var i = 0; i < 8; i++) {
						code += chars.charAt(Math.floor(Math.random() * chars.length));
					}
					$('#obn_cc_code').val(code);
				});

				// Create - Auto-fill from Occasion
				$('#obn_cc_coupon_id').on('change', function () {
					var selectedOption = $(this).find('option:selected');
					var val = selectedOption.data('value');
					var type = selectedOption.data('type');
					var expire = selectedOption.data('expire');
					var code = selectedOption.data('code');

					if (val) $('#obn_cc_value').val(val);
					if (type) $('#obn_cc_type').val(type);
					if (expire) $('#obn_cc_date').val(expire);
					// Typically we generate a unique code for the customer, but we could use the master code as prefix or base
					// Keeping logic flexible: user can click Generate to override
				});

				// Create Customer Coupon Submit
				$('#obn-customer-coupon-create-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Saving...');

					$.post(obn_ajax.ajax_url, form.serialize(), function (response) {
						if (response.success) {
							alert((response.data.message || 'Customer Coupon assigned.'));
							form[0].reset();
							localStorage.setItem('obn-after-reload-view', 'obn-view-coupon-customer-list');
							location.reload();
						} else {
							alert((response.data || 'Failed.'));
						}
					}).fail(function () { alert('âŒ Request failed.'); }).always(function () { btn.prop('disabled', false).text(originalText); });
				});

				// Delete Customer Coupon
				$(document).on('click', '.obn-customer-coupon-delete', function () {
					if (!confirm('Are you sure you want to remove this coupon from the customer?')) return;
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_delete_customer_coupon', id: id, security: nonce }, function (response) {
						if (response.success) {
							alert('Deleted.');
							loadCustomerCoupons();
						} else {
							alert('Failed.');
						}
					});
				});

				// Edit Customer Coupon - Fetch
				$(document).on('click', '.obn-customer-coupon-edit', function () {
					var id = $(this).data('id');
					var nonce = $(this).data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_get_customer_coupon', id: id, security: nonce }, function (response) {
						if (response.success) {
							var c = response.data;
							$('#obn_edit_cc_id').val(c.id);
							$('#obn_edit_cc_customer').val(c.customer_id);
							$('#obn_edit_cc_code').val(c.coupon_code);
							$('#obn_edit_cc_date').val(c.expire_date);
							$('#obn_edit_cc_value').val(c.coupon_value);
							$('#obn_edit_cc_type').val(c.coupon_type);
							$('#obn_edit_cc_desc').val(c.description);

							$('.obn-view-section').hide();
							$('#obn-view-coupon-customer-edit').show();
						} else {
							alert('Error fetching data.');
						}
					});
				});

				// Update Customer Coupon Submit
				$('#obn-customer-coupon-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					$.post(obn_ajax.ajax_url, form.serialize(), function (response) {
						if (response.success) {
							alert('Updated.');
							localStorage.setItem('obn-after-reload-view', 'obn-view-coupon-customer-list');
							location.reload();
						} else {
							alert('Failed.');
						}
					});
				});

				// Toggle Customer Coupon Status
				$(document).on('change', '.obn-toggle-customer-coupon-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.is(':checked') ? 1 : 0;
					var nonce = cb.data('nonce');

					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_customer_coupon_status', id: id, status: status, security: nonce }, function (response) {
						if (!response.success) {
							alert('Failed to update status.');
							cb.prop('checked', !cb.prop('checked'));
						}
					});
				});

				// ==================== QUOTATION HANDLERS ====================

				// Show Add Quotation Form
				$('#obn-quotation-show-add, #obn-quotation-add-link').on('click', function (e) {
					e.preventDefault();
					$('.obn-view-section').hide();
					$('#obn-view-quotation-add').show();
					document.getElementById('obn_add_quotation_date').focus();
				});

				// Add quotation form submission
				// Add quotation form submission
				/*
				$('#obn-quotation-add-form').on('submit', function(e){
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Saving...');

					var formData = {
						action: 'obn_insert_quotation',
						security: form.find('input[name="security"]').val(),
						quotation_date: form.find('#obn_add_quotation_date').val(),
						customer_id: form.find('#obn_add_quotation_customer').val(),
						warehouse_id: form.find('#obn_add_quotation_warehouse').val(),
						grand_total: form.find('#obn_add_quotation_total').val(),
						quotation_status: form.find('#obn_add_quotation_status').val(),
						reference_no: form.find('#obn_add_quotation_ref').val(),
						quotation_code: form.find('#obn_add_quotation_code').val(),
						note: form.find('#obn_add_quotation_note').val()
					};

					$.post(obn_ajax.ajax_url, formData, function(response){
						if (response.success) {
							alert('âœ… ' + (response.data.message || 'Quotation saved.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-quotation-list');
							location.reload();
						} else {
							alert('âŒ ' + (response.data || 'Insert failed.'));
						}
					}).fail(function(){ alert('âŒ Request failed.'); }).always(function(){ btn.prop('disabled', false).text(originalText); });
				});

				// Cancel Add Quotation
				$('#obn-quotation-add-cancel').on('click', function(){
					$('.obn-view-section').hide();
					$('#obn-view-quotation-list').show();
				});
				*/

				// Edit quotation - fetch and populate
				// Edit quotation - fetch and populate
				/*
				$(document).on('click', '.obn-quotation-edit', function(){
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					var formData = {
						action: 'obn_get_quotation',
						security: nonce,
						quotation_id: id
					};

					$.post(obn_ajax.ajax_url, formData, function(response){
						if (response.success) {
							var q = response.data;
							$('#obn_edit_quotation_id').val(q.id);
							$('#obn_edit_quotation_code').val(q.quotation_code);
							$('#obn_edit_quotation_date').val(q.quotation_date);
							$('#obn_edit_quotation_customer').val(q.customer_id);
							$('#obn_edit_quotation_warehouse').val(q.warehouse_id);
							$('#obn_edit_quotation_total').val(q.grand_total);
							$('#obn_edit_quotation_status').val(q.quotation_status);
							$('#obn_edit_quotation_ref').val(q.reference_no);
							$('#obn_edit_quotation_note').val(q.quotation_note);

							$('.obn-view-section').hide();
							$('#obn-view-quotation-edit').show();
						} else {
							alert('âŒ ' + (response.data || 'Failed to fetch quotation.'));
						}
					}).fail(function(){ alert('âŒ Request failed.'); });
				});

				// Update quotation form submission
				$('#obn-quotation-edit-form').on('submit', function(e){
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Updating...');

					var formData = {
						action: 'obn_update_quotation',
						security: form.find('input[name="security"]').val(),
						quotation_id: form.find('#obn_edit_quotation_id').val(),
						quotation_date: form.find('#obn_edit_quotation_date').val(),
						customer_id: form.find('#obn_edit_quotation_customer').val(),
						warehouse_id: form.find('#obn_edit_quotation_warehouse').val(),
						grand_total: form.find('#obn_edit_quotation_total').val(),
						quotation_status: form.find('#obn_edit_quotation_status').val(),
						reference_no: form.find('#obn_edit_quotation_ref').val(),
						note: form.find('#obn_edit_quotation_note').val()
					};

					$.post(obn_ajax.ajax_url, formData, function(response){
						if (response.success) {
							alert('âœ… ' + (response.data.message || 'Quotation updated.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-quotation-list');
							location.reload();
						} else {
							alert('âŒ ' + (response.data || 'Update failed.'));
						}
					}).fail(function(){ alert('âŒ Request failed.'); }).always(function(){ btn.prop('disabled', false).text(originalText); });
				});

				// Cancel Edit Quotation
				$('#obn-quotation-edit-cancel').on('click', function(){
					$('.obn-view-section').hide();
					$('#obn-view-quotation-list').show();
				});
				*/

				// Delete quotation
				$(document).on('click', '.obn-quotation-delete', function () {
					if (!confirm('Are you sure you want to delete this quotation?')) return;

					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					var formData = {
						action: 'obn_delete_quotation',
						security: nonce,
						quotation_id: id
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							alert((response.data || 'Quotation deleted.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-quotation-list');
							location.reload();
						} else {
							alert((response.data || 'Delete failed.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// ==================== EXPENSE HANDLERS ====================

				// Show Add Expense Form
				$('#obn-expense-show-add').on('click', function (e) {
					e.preventDefault();
					$('.obn-view-section').hide();
					$('#obn-view-expense-add').show();
					document.getElementById('obn_add_expense_date').focus();
				});

				// Supplier address display
				$('#obn_add_supplier_id').on('change', function () {
					var selectedOption = $(this).find('option:selected');
					var address = selectedOption.data('address') || '';
					$('#billing_address').val(address);
				});

				// Dynamic expense table functionality
				var expenseRowCount = 1;

				// Add new expense row
				$('#add-expense-row').on('click', function () {
					expenseRowCount++;
					var newRow = `
						<tr class="expense-row" data-row="${expenseRowCount}">
							<td class="px-3 py-2 text-sm text-gray-900 border-b">${expenseRowCount}</td>
							<td class="px-3 py-2 border-b">
								<select name="expense_account[]" class="expense-account w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
									<option value="">Select Account</option>
									<?php
									foreach ($expense_accounts as $account) {
										echo '<option value="' . esc_attr($account->id) . '">' . esc_html($account->account_name) . ' (' . esc_html($account->account_code) . ')</option>';
									}
									?>
								</select>
							</td>
							<td class="px-3 py-2 border-b">
								<input type="text" name="expense_description[]" class="expense-description w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Description" required>
							</td>
							<td class="px-3 py-2 border-b">
								<input type="number" step="0.01" name="expense_amount[]" class="expense-amount w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="0.00" required>
							</td>
							<td class="px-3 py-2 text-right text-sm font-medium text-gray-900 border-b expense-total">0.00</td>
							<td class="px-3 py-2 text-center border-b">
								<button type="button" class="remove-expense-row text-red-600 hover:text-red-800 text-sm">
									<i class="fa-solid fa-trash"></i>
								</button>
							</td>
						</tr>
					`;
					$('#expense-items-tbody').append(newRow);
					updateRowNumbers();
				});

				// Remove expense row
				$(document).on('click', '.remove-expense-row', function () {
					if ($('#expense-items-tbody tr').length > 1) {
						$(this).closest('tr').remove();
						updateRowNumbers();
						calculateTotal();
					} else {
						alert('At least one row is required.');
					}
				});

				// Update row numbers
				function updateRowNumbers() {
					$('#expense-items-tbody tr').each(function (index) {
						$(this).find('td:first').text(index + 1);
						$(this).attr('data-row', index + 1);
					});
				}

				// Calculate total amount
				function calculateTotal() {
					var total = 0;
					$('.expense-amount').each(function () {
						var amount = parseFloat($(this).val()) || 0;
						total += amount;
						$(this).closest('tr').find('.expense-total').text(amount.toFixed(2));
					});

					var prevTotal = parseFloat($('#total-amount').text()) || 0;
					var currentPaid = parseFloat($('#obn_add_expense_paid_amount').val()) || 0;

					$('#total-amount').text(total.toFixed(2));

					// If paid amount was matching total or is empty/zero, keep it synced
					if (currentPaid === prevTotal || currentPaid === 0 || $('#obn_add_expense_paid_amount').val() === '') {
						$('#obn_add_expense_paid_amount').val(total.toFixed(2));
					}
				}

				// Auto-calculate on amount change
				$(document).on('input', '.expense-amount', function () {
					calculateTotal();
				});

				// Add expense form submission
				$('#obn-expense-add-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Saving...');

					// Collect expense items data
					var expenseItems = [];
					$('.expense-row').each(function () {
						expenseItems.push({
							account_id: $(this).find('.expense-account').val(),
							description: $(this).find('.expense-description').val(),
							amount: $(this).find('.expense-amount').val()
						});
					});

					var totalAmount = parseFloat($('#total-amount').text()) || 0;
					var paidAmount = parseFloat(form.find('#obn_add_expense_paid_amount').val()) || 0;
					var paymentStatus = 'Partial';

					if (paidAmount >= totalAmount) {
						paymentStatus = 'Paid';
					} else if (paidAmount <= 0) {
						paymentStatus = 'Due';
					}

					var formData = {
						action: 'obn_insert_expense',
						security: form.find('input[name="security"]').val(),
						expense_date: form.find('#obn_add_expense_date').val(),
						reference_no: form.find('#obn_add_expense_ref').val(),
						supplier_id: form.find('#obn_add_supplier_id').val(),
						payment_type: form.find('#obn_add_expense_payment_type').val(),
						bank_account_id: form.find('#obn_add_expense_account').val(),
						billing_address: form.find('#billing_address').val(),
						expense_items: JSON.stringify(expenseItems),
						total_amount: totalAmount,
						paid_amount: paidAmount,
						payment_status: paymentStatus,
						comments: form.find('#obn_add_expense_comments').val()
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							alert((response.data.message || 'Expense saved.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-expense-list');
							location.reload();
						} else {
							alert((response.data || 'Insert failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { btn.prop('disabled', false).text(originalText); });
				});

				// Cancel Add Expense - Back to List
				$(document).on('click', '#obn-expense-add-cancel', function (e) {
					e.preventDefault();
					console.log('Back to List clicked'); // Debug log
					// Hide all view sections
					$('.obn-view-section').removeClass('active').hide();
					// Show expense list
					$('#obn-view-expense-list').stop().fadeIn(200).addClass('active');
					// Scroll to top
					$('.obn-main-content').scrollTop(0);
				});

				// View expense modal
				$(document).on('click', '.obn-expense-view', function () {
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					var formData = {
						action: 'obn_get_expense',
						security: nonce,
						id: id
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							var e = response.data.expense;
							var items = response.data.items;

							// Populate basic details
							$('#view_expense_date').text(e.expense_date);
							$('#view_expense_ref').text(e.reference_no || '-');
							$('#view_expense_supplier').text(e.supplier_id ? btn.closest('tr').find('td:eq(1)').text().trim() : 'No Supplier');

							var payTypeDisplay = e.payment_type || '-';
							if (e.payment_type === 'Bank' && e.bank_account_name) {
								payTypeDisplay += ' (' + e.bank_account_name + ')';
							}
							$('#view_expense_payment_type').text(payTypeDisplay);

							// Populate Items
							var tbody = $('#view_expense_items_tbody');
							tbody.empty();
							var total = 0;

							if (items && items.length > 0) {
								items.forEach(function (item, index) {
									var amount = parseFloat(item.amount) || 0;
									total += amount;
									var row = `
										<tr>
											<td class="px-4 py-3 text-sm text-gray-900 border-b">${index + 1}</td>
											<td class="px-4 py-3 text-sm text-gray-900 border-b">${item.account_name || item.account_id}</td>
											<td class="px-4 py-3 text-sm text-gray-900 border-b">${item.description}</td>
											<td class="px-4 py-3 text-sm text-gray-900 border-b text-right">${amount.toFixed(2)}</td>
										</tr>
									`;
									tbody.append(row);
								});
							} else {
								tbody.append('<tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No items found</td></tr>');
							}

							$('#view_expense_total').text(total.toFixed(2));
							$('#view_expense_paid').text(parseFloat(e.paid_amount || 0).toFixed(2));
							var due = total - parseFloat(e.paid_amount || 0);
							$('#view_expense_due').text(due > 0 ? due.toFixed(2) : '0.00');

							var statusColor = e.payment_status === 'Paid' ? 'bg-green-500' : (e.payment_status === 'Partial' ? 'bg-yellow-500' : 'bg-red-500');
							$('#view_expense_status').text(e.payment_status || 'Due').removeClass('bg-green-500 bg-yellow-500 bg-red-500').addClass(statusColor);

							$('#view_expense_comments').text(e.comments || '-');

							// Show modal
							var modal = $('#obn-expense-view-modal');
							modal.removeClass('hidden');
							setTimeout(function () {
								modal.find('> div').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
							}, 10);
						} else {
							alert('Failed to fetch expense details.');
						}
					}).fail(function () { alert('Request failed.'); });
				});
				// Close view modal
				$(document).on('click', '.obn-close-modal', function () {
					var modal = $('#obn-expense-view-modal');
					modal.find('> div').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
					setTimeout(function () {
						modal.addClass('hidden');
					}, 300);
				});

				// Journal Entry View
				$(document).on('click', '.obn-view-je', function () {
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					$.post(obn_ajax.ajax_url, {
						action: 'obn_get_journal_entry',
						security: nonce,
						id: id
					}, function (response) {
						if (response.success) {
							var je = response.data.entry;
							var lines = response.data.lines;

							$('#view_je_date').text(je.entry_date);
							$('#view_je_ref').text(je.reference_no || '-');
							$('#view_je_status').text(je.status || 'Posted');

							var statusClass = je.status === 'Posted' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
							$('#view_je_status').attr('class', 'px-2 py-0.5 rounded text-xs font-bold ' + statusClass);

							$('#view_je_user').text(je.creator_name || 'Admin');
							$('#view_je_description').text(je.description || 'No description provided.');

							var tbody = $('#view_je_lines_tbody');
							tbody.empty();
							var totalDebit = 0;
							var totalCredit = 0;

							lines.forEach(function (line) {
								var debit = parseFloat(line.debit) || 0;
								var credit = parseFloat(line.credit) || 0;
								totalDebit += debit;
								totalCredit += credit;

								var row = `
									<tr>
										<td class="px-4 py-3 text-sm text-gray-900 border-b">
											<div class="font-medium">${line.account_name}</div>
											<div class="text-xs text-gray-500">${line.account_code || ''}</div>
										</td>
										<td class="px-4 py-3 text-sm text-gray-600 border-b">${line.description || ''}</td>
										<td class="px-4 py-3 text-sm text-gray-900 border-b text-right">${debit > 0 ? debit.toFixed(2) : '-'}</td>
										<td class="px-4 py-3 text-sm text-gray-900 border-b text-right">${credit > 0 ? credit.toFixed(2) : '-'}</td>
									</tr>
								`;
								tbody.append(row);
							});

							$('#view_je_total_debit').text(totalDebit.toFixed(2));
							$('#view_je_total_credit').text(totalCredit.toFixed(2));

							// Show modal
							var modal = $('#obn-journal-view-modal');
							modal.removeClass('hidden');
							setTimeout(function () {
								modal.find('> div').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
							}, 10);
						} else {
							alert(response.data || 'Failed to fetch journal entry details.');
						}
					}).fail(function () {
						alert('Request failed. Please try again.');
					});
				});

				// Close journal view modal
				$(document).on('click', '.obn-close-je-modal', function () {
					var modal = $('#obn-journal-view-modal');
					modal.find('> div').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
					setTimeout(function () {
						modal.addClass('hidden');
					}, 300);
				});

				// Edit expense - fetch and populate
				$(document).on('click', '.obn-expense-edit', function () {
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					var formData = {
						action: 'obn_get_expense',
						security: nonce,
						id: id
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							var e = response.data.expense;
							var items = response.data.items;

							$('#obn_edit_expense_id').val(e.id);
							$('#obn_edit_expense_date').val(e.expense_date);
							$('#obn_edit_expense_ref').val(e.reference_no);
							$('#obn_edit_supplier_id').val(e.supplier_id);
							$('#obn_edit_expense_payment_type').val(e.payment_type);
							$('#obn_edit_expense_account').val(e.account_id);
							$('#edit_billing_address').val(e.billing_address);
							$('#obn_edit_expense_status').val(e.payment_status);
							$('#obn_edit_expense_paid_amount').val(e.paid_amount);
							$('#obn_edit_expense_comments').val(e.comments);

							// Clear and populate items table
							$('#edit-expense-items-tbody').empty();
							if (items && items.length > 0) {
								items.forEach(function (item, index) {
									var rowNum = index + 1;
									var row = `
										<tr class="edit-expense-row" data-row="${rowNum}">
											<td class="px-3 py-2 text-sm text-gray-900 border-b">${rowNum}</td>
											<td class="px-3 py-2 border-b">
												<select name="expense_account[]" class="edit-expense-account w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
													<option value="">Select Account</option>
													<?php
													foreach ($expense_accounts as $account) {
														echo '<option value="' . esc_attr($account->id) . '">' . esc_html($account->account_name) . ' (' . esc_html($account->account_code) . ')</option>';
													}
													?>
												</select>
											</td>
											<td class="px-3 py-2 border-b">
												<input type="text" name="expense_description[]" class="edit-expense-description w-full px-2 py-1 border border-gray-300 rounded text-sm" value="${item.description}" placeholder="Description" required>
											</td>
											<td class="px-3 py-2 border-b">
												<input type="number" step="0.01" name="expense_amount[]" class="edit-expense-amount w-full px-2 py-1 border border-gray-300 rounded text-sm" value="${item.amount}" placeholder="0.00" required>
											</td>
											<td class="px-3 py-2 text-right text-sm font-medium text-gray-900 border-b edit-expense-total">${parseFloat(item.amount).toFixed(2)}</td>
											<td class="px-3 py-2 text-center border-b">
												<button type="button" class="remove-edit-expense-row text-red-600 hover:text-red-800 text-sm">
													<i class="fa-solid fa-trash"></i>
												</button>
											</td>
										</tr>
									`;
									var $row = $(row);
									$row.find('.edit-expense-account').val(item.account_id);
									$('#edit-expense-items-tbody').append($row);
								});
							}
							calculateEditTotal();

							$('.obn-view-section').hide();
							$('#obn-view-expense-edit').show();
						} else {
							alert((response.data || 'Failed to fetch expense.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// Edit row functionality
				$(document).on('click', '#edit-add-expense-row', function () {
					var rowCount = $('#edit-expense-items-tbody tr').length + 1;
					var newRow = `
						<tr class="edit-expense-row" data-row="${rowCount}">
							<td class="px-3 py-2 text-sm text-gray-900 border-b">${rowCount}</td>
							<td class="px-3 py-2 border-b">
								<select name="expense_account[]" class="edit-expense-account w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
									<option value="">Select Account</option>
									<?php
									foreach ($expense_accounts as $account) {
										echo '<option value="' . esc_attr($account->id) . '">' . esc_html($account->account_name) . ' (' . esc_html($account->account_code) . ')</option>';
									}
									?>
								</select>
							</td>
							<td class="px-3 py-2 border-b">
								<input type="text" name="expense_description[]" class="edit-expense-description w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Description" required>
							</td>
							<td class="px-3 py-2 border-b">
								<input type="number" step="0.01" name="expense_amount[]" class="edit-expense-amount w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="0.00" required>
							</td>
							<td class="px-3 py-2 text-right text-sm font-medium text-gray-900 border-b edit-expense-total">0.00</td>
							<td class="px-3 py-2 text-center border-b">
								<button type="button" class="remove-edit-expense-row text-red-600 hover:text-red-800 text-sm">
									<i class="fa-solid fa-trash"></i>
								</button>
							</td>
						</tr>
					`;
					$('#edit-expense-items-tbody').append(newRow);
					updateEditRowNumbers();
				});

				$(document).on('click', '.remove-edit-expense-row', function () {
					if ($('#edit-expense-items-tbody tr').length > 1) {
						$(this).closest('tr').remove();
						updateEditRowNumbers();
						calculateEditTotal();
					} else {
						alert('At least one row is required.');
					}
				});

				function updateEditRowNumbers() {
					$('#edit-expense-items-tbody tr').each(function (index) {
						$(this).find('td:first').text(index + 1);
						$(this).attr('data-row', index + 1);
					});
				}

				function calculateEditTotal() {
					var total = 0;
					$('.edit-expense-amount').each(function () {
						var amount = parseFloat($(this).val()) || 0;
						total += amount;
						$(this).closest('tr').find('.edit-expense-total').text(amount.toFixed(2));
					});

					var prevTotal = parseFloat($('#edit-total-amount').text()) || 0;
					var currentPaid = parseFloat($('#obn_edit_expense_paid_amount').val()) || 0;

					$('#edit-total-amount').text(total.toFixed(2));

					// If paid amount was matching total or is empty/zero, keep it synced
					if (currentPaid === prevTotal || currentPaid === 0 || $('#obn_edit_expense_paid_amount').val() === '') {
						$('#obn_edit_expense_paid_amount').val(total.toFixed(2));
					}
				}

				$(document).on('input', '.edit-expense-amount', function () {
					calculateEditTotal();
				});

				// Update expense form submission
				$('#obn-expense-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Updating...');

					var expenseItems = [];
					$('.edit-expense-row').each(function () {
						expenseItems.push({
							account_id: $(this).find('.edit-expense-account').val(),
							description: $(this).find('.edit-expense-description').val(),
							amount: $(this).find('.edit-expense-amount').val()
						});
					});

					var totalAmount = parseFloat($('#edit-total-amount').text()) || 0;
					var paidAmount = parseFloat(form.find('#obn_edit_expense_paid_amount').val()) || 0;
					var paymentStatus = 'Partial';

					if (paidAmount >= totalAmount) {
						paymentStatus = 'Paid';
					} else if (paidAmount <= 0) {
						paymentStatus = 'Due';
					}

					var formData = {
						action: 'obn_update_expense',
						security: form.find('input[name="security"]').val(),
						id: form.find('#obn_edit_expense_id').val(),
						expense_date: form.find('#obn_edit_expense_date').val(),
						reference_no: form.find('#obn_edit_expense_ref').val(),
						supplier_id: form.find('#obn_edit_supplier_id').val(),
						payment_type: form.find('#obn_edit_expense_payment_type').val(),
						bank_account_id: form.find('#obn_edit_expense_account').val(),
						billing_address: form.find('#edit_billing_address').val(),
						expense_items: JSON.stringify(expenseItems),
						total_amount: totalAmount,
						paid_amount: paidAmount,
						payment_status: paymentStatus,
						comments: form.find('#obn_edit_expense_comments').val()
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							alert((response.data.message || 'Expense updated.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-expense-list');
							location.reload();
						} else {
							alert((response.data || 'Update failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { btn.prop('disabled', false).text(originalText); });
				});

				// Cancel Edit Expense
				$(document).on('click', '#obn-expense-edit-cancel', function () {
					$('.obn-view-section').hide();
					$('#obn-view-expense-list').show();
				});

				// Delete expense
				$(document).on('click', '.obn-expense-delete', function () {
					if (!confirm('Are you sure you want to delete this expense?')) return;

					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					var formData = {
						action: 'obn_delete_expense',
						security: nonce,
						id: id
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							alert((response.data || 'Expense deleted.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-expense-list');
							location.reload();
						} else {
							alert((response.data || 'Delete failed.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// Toggle expense status
				$(document).on('change', '.obn-toggle-expense-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.is(':checked') ? 1 : 0;
					var nonce = cb.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_expense_status', id: id, status: status, security: nonce }, function (response) {
						if (!response.success) {
							alert('Failed to update status.');
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('AJAX error while updating status.'); cb.prop('checked', !cb.prop('checked')); });
				});

				// ==================== EXPENSE CATEGORY HANDLERS ====================

				// Show Add Category Form
				$('#obn-category-show-add').on('click', function (e) {
					e.preventDefault();
					$('.obn-view-section').hide();
					$('#obn-view-expense-category-add').show();
					document.getElementById('obn_add_category_name').focus();
				});

				// Add category form submission
				$('#obn-category-add-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Saving...');

					var formData = {
						action: 'obn_insert_expense_category',
						security: form.find('input[name="security"]').val(),
						category_name: form.find('#obn_add_category_name').val(),
						description: form.find('#obn_add_category_desc').val()
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							alert((response.data.message || 'Category saved.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-expense-category');
							location.reload();
						} else {
							alert((response.data || 'Insert failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { btn.prop('disabled', false).text(originalText); });
				});

				// Cancel Add Category
				$('#obn-category-add-cancel').on('click', function () {
					$('.obn-view-section').hide();
					$('#obn-view-expense-category').show();
				});

				// Edit category - fetch and populate
				$(document).on('click', '.obn-category-edit', function () {
					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					var formData = {
						action: 'obn_get_expense_category',
						security: nonce,
						id: id
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							var c = response.data;
							$('#obn_edit_category_id').val(c.id);
							$('#obn_edit_category_name').val(c.category_name);
							$('#obn_edit_category_desc').val(c.description);

							$('.obn-view-section').hide();
							$('#obn-view-expense-category-edit').show();
						} else {
							alert((response.data || 'Failed to fetch category.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// Update category form submission
				$('#obn-category-edit-form').on('submit', function (e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');
					var originalText = btn.text();
					btn.prop('disabled', true).text('Updating...');

					var formData = {
						action: 'obn_update_expense_category',
						security: form.find('input[name="security"]').val(),
						id: form.find('#obn_edit_category_id').val(),
						category_name: form.find('#obn_edit_category_name').val(),
						description: form.find('#obn_edit_category_desc').val()
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							alert((response.data.message || 'Category updated.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-expense-category');
							location.reload();
						} else {
							alert((response.data || 'Update failed.'));
						}
					}).fail(function () { alert('Request failed.'); }).always(function () { btn.prop('disabled', false).text(originalText); });
				});

				// Cancel Edit Category
				$('#obn-category-edit-cancel').on('click', function () {
					$('.obn-view-section').hide();
					$('#obn-view-expense-category').show();
				});

				// Delete category
				$(document).on('click', '.obn-category-delete', function () {
					if (!confirm('Are you sure you want to delete this category?')) return;

					var btn = $(this);
					var id = btn.data('id');
					var nonce = btn.data('nonce');

					var formData = {
						action: 'obn_delete_expense_category',
						security: nonce,
						id: id
					};

					$.post(obn_ajax.ajax_url, formData, function (response) {
						if (response.success) {
							alert((response.data || 'Category deleted.'));
							localStorage.setItem('obn-after-reload-view', 'obn-view-expense-category');
							location.reload();
						} else {
							alert((response.data || 'Delete failed.'));
						}
					}).fail(function () { alert('Request failed.'); });
				});

				// Toggle category status
				$(document).on('change', '.obn-toggle-category-status', function () {
					var cb = $(this);
					var id = cb.data('id');
					var status = cb.is(':checked') ? 1 : 0;
					var nonce = cb.data('nonce');
					$.post(obn_ajax.ajax_url, { action: 'obn_toggle_expense_category_status', id: id, status: status, security: nonce }, function (response) {
						if (!response.success) {
							alert('Failed to update status.');
							cb.prop('checked', !cb.prop('checked'));
						}
					}).fail(function () { alert('AJAX error while updating status.'); cb.prop('checked', !cb.prop('checked')); });
				});


				// ==================== COMMON LIST HANDLERS (Search, Export, Column Hide) ====================

				// 1. Column Visibility Toggle
				$(document).on('click', '.obn-column-toggle-btn', function (e) {
					e.preventDefault();
					e.stopPropagation();
					$('.obn-column-dropdown').not($(this).next('.obn-column-dropdown')).addClass('hidden');
					$(this).next('.obn-column-dropdown').toggleClass('hidden');
				});

				$(document).on('click', function (e) {
					if (!$(e.target).closest('.obn-column-toggle-btn, .obn-column-dropdown').length) {
						$('.obn-column-dropdown').addClass('hidden');
					}
				});

				$(document).on('change', '.obn-col-hide', function () {
					const column = $(this).data('column');
					const isChecked = $(this).is(':checked');
					const targetTable = $(this).data('table');
					const $table = $(targetTable);

					$table.find('thead tr th').eq(column).toggle(isChecked);
					$table.find('tbody tr').each(function () {
						$(this).find('td').eq(column).toggle(isChecked);
					});
				});

				// 2. Search Logic
				const searchMappings = [
					{ input: '#obn-accounts-search', table: '#obn-accounts-table' },
					{ input: '#obn-payment-types-search', table: '#obn-payment-types-table' },
					{ input: '#obn-deposits-search', table: '#obn-deposits-table' },
					{ input: '#obn-advances-search', table: '#obn-advances-table' },
					{ input: '#obn-expenses-search', table: '#obn-expenses-table' },
					{ input: '#obn-categories-search', table: '#obn-categories-table' },
					{ input: '#obn-currency-search', table: '#obn-currency-table' },
					{ input: '#obn-tax-search', table: '#obn-tax-table' },
					{ input: '#obn_master_search', table: '#obn-coupons-master-table' },
					{ input: '#obn_cc_search', table: '#obn-customer-coupons-table' },
					{ input: '#obn-mt-search', table: '#obn-money-transfer-table' },
					{ input: '#obn-cash-transactions-search', table: '#obn-cash-transactions-table' },
					{ input: '#obn-quotation-search', table: '#obn-quotations-table' }
				];

				searchMappings.forEach(mapping => {
					$(document).on('keyup', mapping.input, function () {
						const value = $(this).val().toLowerCase();
						$(mapping.table + ' tbody tr').filter(function () {
							$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
						});
					});
				});

				// 3. Export Helper: Get Table Data
				function getTableData(tableId) {
					const data = [];
					const headers = [];

					// Get Headers
					$(tableId + ' thead th:not(.no-export)').each(function () {
						if ($(this).css('display') !== 'none') {
							headers.push($(this).text().trim());
						}
					});
					data.push(headers);

					// Get Body Rows
					$(tableId + ' tbody tr').each(function () {
						if ($(this).css('display') !== 'none') {
							const row = [];
							$(this).find('td:not(.no-export)').each(function () {
								if ($(this).css('display') !== 'none') {
									row.push($(this).text().trim());
								}
							});
							if (row.length > 0) data.push(row);
						}
					});
					return data;
				}

				// 4. Print Logic
				$(document).on('click', '.obn-print-btn', function () {
					const tableId = $(this).data('table');
					const title = $(this).data('title') || 'Report';
					const tableData = getTableData(tableId);

					if (tableData.length <= 1) {
						alert('No data available to print.');
						return;
					}

					const printWindow = window.open('', '_blank', 'height=800,width=1000');
					printWindow.document.write('<!DOCTYPE html><html><head><title>' + title + '</title>');
					printWindow.document.write('<style>');
					printWindow.document.write('body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; padding: 40px; color: #1f2937; line-height: 1.5; }');
					printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 25px; font-size: 11px; }');
					printWindow.document.write('th, td { border: 1px solid #e5e7eb; padding: 10px 8px; text-align: left; }');
					printWindow.document.write('th { background-color: #f9fafb; font-weight: 800; text-transform: uppercase; color: #4b5563; font-size: 10px; letter-spacing: 0.05em; }');
					printWindow.document.write('.header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f3f4f6; padding-bottom: 15px; }');
					printWindow.document.write('.header h1 { margin: 0; font-size: 24px; color: #111827; }');
					printWindow.document.write('.header p { color: #6b7280; margin-top: 5px; font-size: 13px; }');
					printWindow.document.write('.footer { margin-top: 25px; text-align: right; font-size: 10px; color: #9ca3af; }');
					printWindow.document.write('</style></head><body>');
					printWindow.document.write('<div class="header"><h1>' + title + '</h1><p>Financial Statement - Generated on ' + new Date().toLocaleString() + '</p></div>');

					printWindow.document.write('<table><thead><tr>');
					tableData[0].forEach(header => printWindow.document.write('<th>' + header + '</th>'));
					printWindow.document.write('</tr></thead><tbody>');

					for (let i = 1; i < tableData.length; i++) {
						printWindow.document.write('<tr>');
						tableData[i].forEach(cell => printWindow.document.write('<td>' + cell + '</td>'));
						printWindow.document.write('</tr>');
					}

					printWindow.document.write('</tbody></table>');
					printWindow.document.write('<div class="footer">OraBooks Accounting System</div>');
					printWindow.document.write('</body></html>');
					printWindow.document.close();

					setTimeout(() => {
						printWindow.focus();
						printWindow.print();
					}, 500);
				});

				// 5. CSV Export
				$(document).on('click', '.obn-csv-btn', function () {
					const tableId = $(this).data('table');
					const title = $(this).data('title') || 'report';
					const tableData = getTableData(tableId);
					if (tableData.length <= 1) return alert('No data available.');

					let csvContent = "";
					tableData.forEach(row => {
						const escapedRow = row.map(text => '"' + String(text).replace(/"/g, '""') + '"');
						csvContent += escapedRow.join(",") + "\r\n";
					});

					const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
					const url = URL.createObjectURL(blob);
					const link = document.createElement("a");
					link.setAttribute("href", url);
					link.setAttribute("download", title + "_" + new Date().toISOString().slice(0, 10) + ".csv");
					document.body.appendChild(link);
					link.click();
					document.body.removeChild(link);
				});

				// 6. Excel Export (Proper XLSX)
				$(document).on('click', '.obn-excel-btn', function () {
					if (typeof XLSX === 'undefined') return alert('Excel library not loaded.');
					const tableId = $(this).data('table');
					const title = $(this).data('title') || 'report';
					const tableData = getTableData(tableId);
					if (tableData.length <= 1) return alert('No data available.');

					const ws = XLSX.utils.aoa_to_sheet(tableData);
					const wb = XLSX.utils.book_new();
					XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
					XLSX.writeFile(wb, title + "_" + new Date().toISOString().slice(0, 10) + ".xlsx");
				});

				// 7. PDF Export (Proper jsPDF)
				$(document).on('click', '.obn-pdf-btn', function () {
					if (typeof window.jspdf === 'undefined') return alert('PDF library not loaded.');
					const tableId = $(this).data('table');
					const title = ($(this).data('title') || 'Report').replace(/_/g, ' ');
					const tableData = getTableData(tableId);
					if (tableData.length <= 1) return alert('No data available.');

					const { jsPDF } = window.jspdf;
					const doc = new jsPDF('l', 'mm', 'a4');

					doc.setFontSize(18);
					doc.text(title, 14, 20);
					doc.setFontSize(10);
					doc.setTextColor(100);
					doc.text('Generated on: ' + new Date().toLocaleString(), 14, 28);

					const headers = tableData[0];
					const rows = tableData.slice(1);

					doc.autoTable({
						head: [headers],
						body: rows,
						startY: 35,
						theme: 'grid',
						headStyles: { fillColor: [79, 70, 229], textColor: 255, fontStyle: 'bold' },
						styles: { fontSize: 8, cellPadding: 3 },
						margin: { top: 35 }
					});

					doc.save(title.replace(/\s+/g, '_').toLowerCase() + "_" + new Date().getTime() + ".pdf");
				});

				// 8. Money Transfer Handlers
				$(document).on('click', '#obn-money-transfer-show-add', function () {
					$('.obn-view-section').hide();
					$('#obn-view-money-transfer-add').fadeIn();
				});

				$(document).on('click', '#obn-money-transfer-add-cancel, #obn-money-transfer-edit-cancel', function () {
					$('.obn-view-section').hide();
					$('#obn-view-money-transfer-list').fadeIn();
				});

				$(document).on('submit', '#obn-money-transfer-add-form, #obn-money-transfer-edit-form', function (e) {
					e.preventDefault();
					const form = $(this);
					const btn = form.find('button[type="submit"]');
					const originalText = btn.text();
					btn.prop('disabled', true).text('Processing...');

					$.post(obn_ajax.ajax_url, form.serialize(), function (res) {
						if (res.success) {
							alert('Success: ' + (res.data.message || 'Operation successful.'));
							$('.obn-view-section').hide();
							$('#obn-view-money-transfer-list').fadeIn();
							if (typeof obn_mt_refresh === 'function') obn_mt_refresh();
						} else {
							alert('Error: ' + (res.data || 'Failed to process request.'));
						}
					}).always(() => btn.prop('disabled', false).text(originalText));
				});

				$(document).on('click', '.obn-money-transfer-edit', function (e) {
					e.preventDefault();
					const btn = $(this);
					const id = btn.data('id');

					$.post(obn_ajax.ajax_url, {
						action: 'obn_get_money_transfer',
						id: id,
						security: btn.data('nonce')
					}, function (res) {
						if (res.success) {
							const t = res.data;
							$('#obn_edit_transfer_id').val(t.id);
							$('#obn_edit_transfer_code').val(t.transfer_code);
							$('#obn_edit_transfer_date').val(t.transfer_date);
							$('#obn_edit_mt_reference_no').val(t.reference_no);
							$('#obn_edit_debit_account').val(t.debit_account_id);
							$('#obn_edit_credit_account').val(t.credit_account_id);
							$('#obn_edit_amount').val(t.amount);
							$('#obn_edit_mt_note').val(t.note);

							$('.obn-view-section').hide();
							$('#obn-view-money-transfer-edit').fadeIn();
						} else {
							alert(res.data || 'Error fetching transfer');
						}
					});
				});

				$(document).on('click', '.obn-money-transfer-delete', function (e) {
					e.preventDefault();
					if (!confirm('Are you sure you want to delete this transfer?')) return;
					const btn = $(this);
					const id = btn.data('id');
					const row = btn.closest('tr');

					$.post(obn_ajax.ajax_url, {
						action: 'obn_delete_money_transfer',
						id: id,
						security: btn.data('nonce')
					}, function (res) {
						if (res.success) {
							row.fadeOut(function () { $(this).remove(); });
						} else {
							alert(res.data || 'Error deleting transfer');
						}
					});
				});

				// 9. Quotation Handlers
				$(document).on('click', '#obn-quotation-show-add', function () {
					$('.obn-view-section').hide();
					$('#obn-view-quotation-add').fadeIn();
					$(document).trigger('obn:quotation:add');
				});

				$(document).on('click', '.obn-quotation-edit', function () {
					const id = $(this).data('id');
					$('.obn-view-section').hide();
					$('#obn-view-quotation-edit').fadeIn();
					$(document).trigger('obn:quotation:edit', [id]);
				});

				$(document).on('click', '.obn-quotation-view-invoice', function () {
					const id = $(this).data('id');
					$('.obn-view-section').hide();
					$('#obn-view-quotation-invoice').fadeIn();
					$(document).trigger('obn:quotation:invoice', [id]);
				});

				$(document).on('click', '.obn-quotation-delete', function (e) {
					e.preventDefault();
					if (!confirm('Are you sure you want to delete this quotation?')) return;
					const btn = $(this);
					const id = btn.data('id');
					const row = btn.closest('tr');

					$.post(obn_ajax.ajax_url, {
						action: 'obn_delete_quotation',
						id: id,
						security: obn_ajax.nonce
					}, function (res) {
						if (res.success) {
							row.fadeOut(function () { $(this).remove(); });
						} else {
							alert(res.data || 'Error deleting quotation');
						}
					});
				});

				// Filter Quotations
				// Filter Quotations (AJAX)
				$(document).on('click', '#obn-quotation-filter-btn', function (e) {
					e.preventDefault();
					var btn = $(this);
					var originalText = btn.html();

					var warehouse = $('#filter_warehouse_id').val();
					var fromDate = $('#filter_from_date').val();
					var toDate = $('#filter_to_date').val();
					var user = $('#filter_user_id').val();

					// Update URL without reload (for bookmarking/refresh)
					var url = new URL(window.location.href);
					if (warehouse) url.searchParams.set('warehouse_id', warehouse); else url.searchParams.delete('warehouse_id');
					if (fromDate) url.searchParams.set('from_date', fromDate); else url.searchParams.delete('from_date');
					if (toDate) url.searchParams.set('to_date', toDate); else url.searchParams.delete('to_date');
					if (user) url.searchParams.set('user_id', user); else url.searchParams.delete('user_id');
					window.history.pushState({}, '', url);

					// Show loading state
					btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Filtering...');
					$('#obn-quotations-table tbody').html('<tr><td colspan="9" class="px-4 py-8 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block"></i>Loading data...</td></tr>');

					$.post(obn_ajax.ajax_url, {
						action: 'obn_filter_quotations',
						security: obn_ajax.nonce,
						warehouse_id: warehouse,
						from_date: fromDate,
						to_date: toDate,
						user_id: user
					}, function (response) {
						if (response.success) {
							$('#obn-quotations-table tbody').html(response.data.html);
						} else {
							$('#obn-quotations-table tbody').html('<tr><td colspan="9" class="px-4 py-8 text-center text-red-500">Failed to load data.</td></tr>');
						}
					}).fail(function () {
						$('#obn-quotations-table tbody').html('<tr><td colspan="9" class="px-4 py-8 text-center text-red-500">Request failed.</td></tr>');
					}).always(function () {
						btn.prop('disabled', false).html(originalText);
					});
				});

				// --- Opening Balance Handlers ---
				$(document).on('click', '.ob-tabs button, #ob-tabs button', function () {
					const target = $(this).data('target');
					$('.ob-tab-pane').hide();
					$(target).fadeIn();

					$('#ob-tabs button').removeClass('active-tab bg-blue-50 text-blue-700 border-blue-600 font-bold');
					$('#ob-tabs button').addClass('border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-300');

					$(this).addClass('active-tab bg-blue-50 text-blue-700 border-blue-600 font-bold');
					$(this).removeClass('border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-300');
				});

				function calculateOB() {
					let totalDebit = 0;
					let totalCredit = 0;

					$('.ob-input').each(function () {
						let val = parseFloat($(this).val()) || 0;
						if ($(this).hasClass('ob-debit')) totalDebit += val;
						if ($(this).hasClass('ob-credit')) totalCredit += val;
					});

					let totalInv = 0;
					$('.ob-inv-row').each(function () {
						let qty = parseFloat($(this).find('.ob-inv-qty').val()) || 0;
						let cost = parseFloat($(this).find('.ob-inv-cost').val()) || 0;
						let subtotal = qty * cost;
						$(this).find('.ob-inv-subtotal').text(subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 }));
						totalInv += subtotal;
					});
					$('#ob-total-inventory').text(totalInv.toLocaleString(undefined, { minimumFractionDigits: 2 }));

					totalDebit += totalInv;

					$('#ob-summary-debit').text(totalDebit.toLocaleString(undefined, { minimumFractionDigits: 2 }));
					$('#ob-summary-credit').text(totalCredit.toLocaleString(undefined, { minimumFractionDigits: 2 }));

					let diff = totalDebit - totalCredit;
					$('#ob-summary-diff').text(diff.toLocaleString(undefined, { minimumFractionDigits: 2 }));

					if (Math.abs(diff) > 0.001) {
						$('#ob-summary-diff').addClass('text-rose-600').removeClass('text-emerald-600');
					} else {
						$('#ob-summary-diff').addClass('text-emerald-600').removeClass('text-rose-600');
					}
				}

				$(document).on('input', '.ob-input, .ob-inv-qty, .ob-inv-cost', calculateOB);

				// Calculate initial totals based on populated inputs
				calculateOB();

				$(document).on('input', '.ob-debit', function () {
					if ($(this).val() > 0) $(this).closest('tr').find('.ob-credit').val('');
					calculateOB();
				});
				$(document).on('input', '.ob-credit', function () {
					if ($(this).val() > 0) $(this).closest('tr').find('.ob-debit').val('');
					calculateOB();
				});

				$('#obn-opening-balance-form').on('submit', function (e) {
					e.preventDefault();
					const form = $(this);
					const btn = $('#obn-opening-balance-save');
					const formData = form.serialize();

					Swal.fire({
						title: 'Save Opening Balances?',
						text: "This will update your starting financial figures.",
						icon: 'question',
						showCancelButton: true,
						confirmButtonColor: '#1569B3',
						cancelButtonColor: '#39B54A',
						confirmButtonText: 'Yes, Save it!'
					}).then((result) => {
						if (result.isConfirmed) {
							btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing...');

							$.post(obn_ajax.ajax_url, formData, function (res) {
								if (res.success) {
									Swal.fire({
										title: 'Success!',
										text: res.data.message,
										icon: 'success',
										timer: 1500,
										showConfirmButton: false
									}).then(() => {
										location.reload();
									});
								} else {
									Swal.fire('Error', res.data.message || res.data || 'Failed to save balances.', 'error');
									btn.prop('disabled', false).html('<i class="fa-solid fa-cloud-arrow-up mr-3 text-2xl"></i> Save & Process Balances');
								}
							}).fail(function () {
								Swal.fire('Error', 'AJAX Request failed. Please check network.', 'error');
								btn.prop('disabled', false).html('<i class="fa-solid fa-cloud-arrow-up mr-3 text-2xl"></i> Save & Process Balances');
							});
						}
					});
				});

				$(document).on('click', '#obn-opening-balance-reset', function () {
					if (confirm('HARD RESET: This will clear all values on this page. Continue?')) {
						$('#obn-opening-balance-form')[0].reset();
						$('.ob-inv-subtotal').text('0.00');
						calculateOB();
					}
				});

			});
		</script>
		<style>
			@media print {
				body * {
					visibility: hidden;
				}

				#obn-expense-view-modal,
				#obn-expense-view-modal *,
				#obn-journal-view-modal,
				#obn-journal-view-modal * {
					visibility: visible;
				}

				#obn-expense-view-modal,
				#obn-journal-view-modal {
					position: absolute;
					left: 0;
					top: 0;
					width: 100%;
					background: transparent !important;
					box-shadow: none !important;
				}

				#obn-expense-view-modal .border-t,
				#obn-expense-view-modal .obn-close-modal,
				#obn-journal-view-modal .border-t,
				#obn-journal-view-modal .no-print,
				#obn-journal-view-modal .obn-close-je-modal {
					display: none !important;
				}
			}
		</style>
		<?php
		return ob_get_clean();
	}

}



