<?php
/**
 * OraBooks Frontend Pages with Tailwind CSS
 * Creates all required pages with Tailwind styling
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Membership Plans Page - [orabooks_levels]
 */
function orabooks_levels_shortcode($atts) {
    ob_start();
    
    // Get membership levels from database
    global $wpdb;
    if (function_exists('orabooks_handle_multisite_tables')) {
        orabooks_handle_multisite_tables();
    }
    
    $levels = $wpdb->get_results("SELECT * FROM {$wpdb->orabooks_levels} WHERE is_active = 1 ORDER BY price ASC");
    $current_user_id = get_current_user_id();
    $current_level = get_user_meta($current_user_id, 'orabooks_level', true);
    
    ?>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Choose Your Plan</h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Select the perfect plan for your business needs. All plans include core features with varying levels of advanced functionality.
                </p>
            </div>
            
            <!-- Pricing Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($levels as $level): ?>
                    <?php
                    $is_current_plan = ($current_level == $level->id);
                    $is_free = ($level->price == 0);
                    ?>
                    <div class="relative bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all duration-300 hover:scale-105 hover:shadow-2xl <?php echo $is_current_plan ? 'ring-4 ring-green-500 ring-opacity-50' : ''; ?>">
                        <?php if ($is_current_plan): ?>
                            <div class="absolute top-0 right-0 bg-green-500 text-white px-4 py-1 rounded-bl-lg text-sm font-semibold">
                                Current Plan
                            </div>
                        <?php endif; ?>
                        
                        <div class="p-8">
                            <div class="text-center mb-6">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2"><?php echo esc_html($level->name); ?></h3>
                                <div class="text-4xl font-bold text-green-600">
                                    <?php if ($is_free): ?>
                                        Free
                                    <?php else: ?>
                                        $<?php echo number_format($level->price, 2); ?>
                                        <span class="text-lg text-gray-500">/<?php echo esc_html($level->billing_period); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-8">
                                <?php echo wpautop(wp_kses_post($level->description)); ?>
                            </div>
                            
                            <div class="space-y-3 mb-8">
                                <?php 
                                $features = !empty($level->features) ? json_decode($level->features, true) : [];
                                if (!empty($features) && is_array($features)):
                                    foreach ($features as $feature):
                                ?>
                                    <div class="flex items-center text-gray-700">
                                        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <span><?php echo esc_html($feature); ?></span>
                                    </div>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                            </div>
                            
                            <div class="space-y-3">
                                <?php if ($is_current_plan): ?>
                                    <button class="w-full bg-gray-300 text-gray-700 py-3 px-6 rounded-lg font-semibold cursor-not-allowed" disabled>
                                        Current Plan
                                    </button>
                                <?php elseif ($is_free): ?>
                                    <a href="<?php echo wp_nonce_url(add_query_arg(['action' => 'orabooks_activate_free_plan', 'level_id' => $level->id], home_url()), 'orabooks_activate_free_plan_' . $level->id); ?>" 
                                       class="w-full bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors duration-200 text-center block">
                                        Activate Free Plan
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo home_url('/checkout/?level_id=' . $level->id); ?>" 
                                       class="w-full bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors duration-200 text-center block">
                                        Choose Plan
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo home_url('/features/'); ?>" 
                                   class="w-full border border-gray-300 hover:border-gray-400 text-gray-700 py-3 px-6 rounded-lg font-semibold transition-colors duration-200 text-center block">
                                    View Features
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- FAQ Section -->
            <div class="mt-16 bg-white rounded-2xl shadow-xl p-8">
                <h2 class="text-3xl font-bold text-center text-gray-900 mb-8">Frequently Asked Questions</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-semibold text-gray-900 mb-2">Can I change plans later?</h3>
                            <p class="text-gray-600">Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately.</p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 mb-2">Is there a contract?</h3>
                            <p class="text-gray-600">No, all plans are month-to-month. You can cancel anytime without penalties.</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-semibold text-gray-900 mb-2">What payment methods do you accept?</h3>
                            <p class="text-gray-600">We accept all major credit cards, PayPal, and bank transfers.</p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 mb-2">Is my data secure?</h3>
                            <p class="text-gray-600">Yes, we use industry-standard encryption and security measures to protect your data.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}
add_shortcode('orabooks_levels', 'orabooks_levels_shortcode');

/**
 * Checkout Page - [orabooks_checkout]
 */
function orabooks_checkout_shortcode($atts) {
    ob_start();
    
    $level_id = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;
    
    if (!$level_id) {
        echo '<div class="text-center py-12"><h2 class="text-2xl font-bold text-red-600">No plan selected</h2><p>Please <a href="' . home_url('/levels/') . '">choose a plan</a> first.</p></div>';
        return ob_get_clean();
    }
    
    global $wpdb;
    if (function_exists('orabooks_handle_multisite_tables')) {
        orabooks_handle_multisite_tables();
    }
    
    $level = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d AND is_active = 1", $level_id));
    
    if (!$level) {
        echo '<div class="text-center py-12"><h2 class="text-2xl font-bold text-red-600">Plan not found</h2><p>Please <a href="' . home_url('/levels/') . '">choose a plan</a>.</p></div>';
        return ob_get_clean();
    }
    
    if (!is_user_logged_in()) {
        $login_url = wp_login_url(add_query_arg(['level_id' => $level_id], home_url('/checkout/')));
        echo '<div class="text-center py-12"><h2 class="text-2xl font-bold text-gray-900">Login Required</h2><p>Please <a href="' . esc_url($login_url) . '">login</a> to continue with checkout.</p></div>';
        return ob_get_clean();
    }
    
    $current_user = wp_get_current_user();
    
    ?>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Complete Your Purchase</h1>
                <p class="text-gray-600">You're just a few steps away from activating your <?php echo esc_html($level->name); ?> plan</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6 sticky top-4">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h3>
                        
                        <div class="space-y-4">
                            <div class="flex justify-between items-center pb-4 border-b">
                                <div>
                                    <h4 class="font-semibold text-gray-900"><?php echo esc_html($level->name); ?></h4>
                                    <p class="text-sm text-gray-600"><?php echo esc_html($level->billing_period); ?> billing</p>
                                </div>
                                <div class="text-2xl font-bold text-green-600">
                                    $<?php echo number_format($level->price, 2); ?>
                                </div>
                            </div>
                            
                            <?php 
                            $features = !empty($level->features) ? json_decode($level->features, true) : [];
                            if (!empty($features) && is_array($features)):
                            ?>
                                <div class="pb-4 border-b">
                                    <h5 class="font-semibold text-gray-900 mb-2">Included Features:</h5>
                                    <ul class="space-y-2">
                                        <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                                            <li class="flex items-center text-sm text-gray-700">
                                                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                <?php echo esc_html($feature); ?>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($features) > 3): ?>
                                            <li class="text-sm text-gray-500">+<?php echo count($features) - 3; ?> more features</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <div class="space-y-2">
                                <div class="flex justify-between text-gray-600">
                                    <span>Subtotal</span>
                                    <span>$<?php echo number_format($level->price, 2); ?></span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Tax</span>
                                    <span>$0.00</span>
                                </div>
                                <div class="flex justify-between text-xl font-bold text-gray-900 pt-2 border-t">
                                    <span>Total</span>
                                    <span>$<?php echo number_format($level->price, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Checkout Form -->
                <div class="lg:col-span-2">
                    <form id="checkout-form" class="bg-white rounded-xl shadow-lg p-8">
                        <div class="space-y-6">
                            <!-- Account Information -->
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 mb-4">Account Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                        <input type="text" value="<?php echo esc_attr($current_user->first_name); ?>" readonly
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                        <input type="text" value="<?php echo esc_attr($current_user->last_name); ?>" readonly
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                        <input type="email" value="<?php echo esc_attr($current_user->user_email); ?>" readonly
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Method -->
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 mb-4">Payment Method</h3>
                                <div class="space-y-3">
                                    <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value="credit_card" checked class="mr-3">
                                        <div class="flex-1">
                                            <div class="font-medium text-gray-900">Credit Card</div>
                                            <div class="text-sm text-gray-600">Visa, Mastercard, American Express</div>
                                        </div>
                                        <div class="flex space-x-2">
                                            <div class="w-8 h-5 bg-blue-600 rounded"></div>
                                            <div class="w-8 h-5 bg-red-600 rounded"></div>
                                            <div class="w-8 h-5 bg-blue-800 rounded"></div>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value="paypal" class="mr-3">
                                        <div class="flex-1">
                                            <div class="font-medium text-gray-900">PayPal</div>
                                            <div class="text-sm text-gray-600">Pay with your PayPal account</div>
                                        </div>
                                        <div class="text-blue-600 font-semibold">PayPal</div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Credit Card Form (shown when credit card is selected) -->
                            <div id="credit-card-form" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                                    <input type="text" placeholder="1234 5678 9012 3456" maxlength="19"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                                        <input type="text" placeholder="MM/YY" maxlength="5"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                                        <input type="text" placeholder="123" maxlength="4"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Cardholder Name</label>
                                    <input type="text" placeholder="John Doe"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div>
                                <label class="flex items-start">
                                    <input type="checkbox" required class="mt-1 mr-3">
                                    <span class="text-sm text-gray-600">
                                        I agree to the <a href="#" class="text-green-600 hover:text-green-700">Terms of Service</a> 
                                        and <a href="#" class="text-green-600 hover:text-green-700">Privacy Policy</a>. 
                                        I understand this is a recurring subscription that can be cancelled at any time.
                                    </span>
                                </label>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="pt-4">
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-4 px-6 rounded-lg font-semibold text-lg transition-colors duration-200">
                                    Complete Purchase - $<?php echo number_format($level->price, 2); ?>
                                </button>
                                
                                <p class="text-center text-sm text-gray-600 mt-4">
                                    <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Secure payment powered by SSL encryption
                                </p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const creditCardForm = document.getElementById('credit-card-form');
        
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                if (this.value === 'credit_card') {
                    creditCardForm.style.display = 'block';
                } else {
                    creditCardForm.style.display = 'none';
                }
            });
        });
        
        // Format card number
        const cardNumberInput = document.querySelector('input[placeholder="1234 5678 9012 3456"]');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\s/g, '');
                let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                e.target.value = formattedValue;
            });
        }
        
        // Format expiry date
        const expiryInput = document.querySelector('input[placeholder="MM/YY"]');
        if (expiryInput) {
            expiryInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.slice(0, 2) + '/' + value.slice(2, 4);
                }
                e.target.value = value;
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
}
add_shortcode('orabooks_checkout', 'orabooks_checkout_shortcode');
