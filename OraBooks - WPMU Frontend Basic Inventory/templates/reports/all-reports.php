<?php
if (!defined('ABSPATH')) exit;
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">All Reports</h1>
    <p class="text-gray-500 mt-1">Access all your business reports from one place.</p>
</div>

<div class="space-y-8">
    <!-- Purchase & Sales Reports -->
    <section>
        <div class="flex items-center mb-4">
            <div class="p-2 bg-blue-100 text-blue-600 rounded-lg mr-3">
                <i class="fa-solid fa-cart-shopping text-xl"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-700">Purchase & Sales Reports</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <a href="?view=sales-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-chart-bar"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Sales Report</h3>
                <p class="text-xs text-gray-500 mt-1">Detailed list of all sales transactions.</p>
            </a>

            <a href="?view=purchase-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-green-50 text-green-500 rounded-lg flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Purchase Report</h3>
                <p class="text-xs text-gray-500 mt-1">Detailed list of all purchase transactions.</p>
            </a>

            <a href="?view=sales-summary-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-500 rounded-lg flex items-center justify-center group-hover:bg-indigo-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Sales Summary Report</h3>
                <p class="text-xs text-gray-500 mt-1">Summary of sales by date and category.</p>
            </a>
        </div>
    </section>

    <!-- Payment & Due Reports -->
    <section>
        <div class="flex items-center mb-4">
            <div class="p-2 bg-yellow-100 text-yellow-600 rounded-lg mr-3">
                <i class="fa-solid fa-money-bill-transfer text-xl"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-700">Payment & Due Reports</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <a href="?view=customer-due-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-yellow-50 text-yellow-600 rounded-lg flex items-center justify-center group-hover:bg-yellow-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Customer Due Report</h3>
                <p class="text-xs text-gray-500 mt-1">Pending payments from customers.</p>
            </a>

            <a href="?view=sales-payment-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-orange-50 text-orange-500 rounded-lg flex items-center justify-center group-hover:bg-orange-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-money-check-dollar"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Sales & Payment Report</h3>
                <p class="text-xs text-gray-500 mt-1">Combined sales and payment history.</p>
            </a>

            <a href="?view=customer-payment-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Customer Payment Report</h3>
                <p class="text-xs text-gray-500 mt-1">Detailed customer payment logs.</p>
            </a>

            <a href="?view=supplier-payment-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-red-50 text-red-500 rounded-lg flex items-center justify-center group-hover:bg-red-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-file-circle-check"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Supplier Payment Report</h3>
                <p class="text-xs text-gray-500 mt-1">Detailed supplier payment logs.</p>
            </a>

            <a href="?view=supplier-due-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-red-50 text-red-600 rounded-lg flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Supplier Due Report</h3>
                <p class="text-xs text-gray-500 mt-1">Pending payments to suppliers.</p>
            </a>
        </div>
    </section>

    <!-- Inventory Reports -->
    <section>
        <div class="flex items-center mb-4">
            <div class="p-2 bg-green-100 text-green-600 rounded-lg mr-3">
                <i class="fa-solid fa-warehouse text-xl"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-700">Inventory Reports</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <a href="?view=stock-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-green-50 text-green-500 rounded-lg flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Stock Report</h3>
                <p class="text-xs text-gray-500 mt-1">Current stock levels and valuation.</p>
            </a>

            <a href="?view=stock-transfer-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-truck-moving"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Stock Transfer Report</h3>
                <p class="text-xs text-gray-500 mt-1">History of stock movements between warehouses.</p>
            </a>
        </div>
    </section>

    <!-- General Accounting Reports -->
    <section>
        <div class="flex items-center mb-4">
            <div class="p-2 bg-purple-100 text-purple-600 rounded-lg mr-3">
                <i class="fa-solid fa-calculator text-xl"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-700">General Accounting Reports</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <a href="?view=profit-loss-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-purple-50 text-purple-500 rounded-lg flex items-center justify-center group-hover:bg-purple-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Profit & Loss Report</h3>
                <p class="text-xs text-gray-500 mt-1">Revenue, expenses, and net profit over time.</p>
            </a>

            <a href="?view=journal-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-pink-50 text-pink-500 rounded-lg flex items-center justify-center group-hover:bg-pink-500 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-book"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Journal Report</h3>
                <p class="text-xs text-gray-500 mt-1">List of all journal entries.</p>
            </a>

            <a href="?view=trial-balance-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-fuchsia-50 text-fuchsia-600 rounded-lg flex items-center justify-center group-hover:bg-fuchsia-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Trial Balance Report</h3>
                <p class="text-xs text-gray-500 mt-1">Verification of debits and credits.</p>
            </a>

            <a href="?view=income-statement-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-violet-50 text-violet-600 rounded-lg flex items-center justify-center group-hover:bg-violet-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Income Statement</h3>
                <p class="text-xs text-gray-500 mt-1">Financial performance statement.</p>
            </a>

            <a href="?view=balance-sheet-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-scale-unbalanced"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Balance Sheet</h3>
                <p class="text-xs text-gray-500 mt-1">Assets, liabilities, and equity snapshot.</p>
            </a>

            <a href="?view=ledger-report" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-slate-50 text-slate-600 rounded-lg flex items-center justify-center group-hover:bg-slate-600 group-hover:text-white transition-colors">
                        <i class="fa-solid fa-book-journal-whills"></i>
                    </div>
                </div>
                <h3 class="font-bold text-gray-800">Ledger Report</h3>
                <p class="text-xs text-gray-500 mt-1">Individual account transactions.</p>
            </a>
        </div>
    </section>
</div>
