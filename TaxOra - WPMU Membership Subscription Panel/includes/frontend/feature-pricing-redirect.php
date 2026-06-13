<?php
/**
 * Feature to Pricing Page Redirect with Highlighting
 * 
 * This file handles redirecting users from feature pages to pricing page
 * with the appropriate plan highlighted based on the feature they clicked.
 */

if (!defined('ABSPATH')) exit;

/**
 * Redirect from feature page to pricing page with highlighting
 */
function orabooks_feature_to_pricing_redirect() {
    // Check if feature parameter exists
    if (isset($_GET['feature'])) {
        $feature_slug = sanitize_text_field($_GET['feature']);
        
        // Get pricing page URL
        $pricing_page = orabooks_get_page_by_title('Pricing');
        if (!$pricing_page) {
            // Try alternative page names
            $pricing_page = orabooks_get_page_by_title('Plans');
            if (!$pricing_page) {
                $pricing_page = orabooks_get_page_by_title('Membership Plans');
                if (!$pricing_page) {
                    $pricing_page = orabooks_get_page_by_title('Upgrade Plan');
                }
            }
        }
        
        if ($pricing_page) {
            $pricing_url = get_permalink($pricing_page->ID);
            $pricing_url = add_query_arg('highlight_feature', $feature_slug, $pricing_url);
            wp_redirect($pricing_url);
            exit;
        }
    }
}
add_action('template_redirect', 'orabooks_feature_to_pricing_redirect');

/**
 * Enhanced pricing shortcode with feature highlighting
 * Replaces or enhances the existing pricing shortcode
 */
function orabooks_pricing_with_highlight_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => '3',
        'show_features' => 'yes'
    ), $atts);
    
    $highlighted_feature = isset($_GET['highlight_feature']) ? sanitize_text_field($_GET['highlight_feature']) : '';
    
    // Get all levels
    global $wpdb;
    orabooks_handle_multisite_tables();
    $levels = $wpdb->get_results("SELECT * FROM {$wpdb->orabooks_levels} ORDER BY price ASC");
    
    if (empty($levels)) {
        return '<p>No pricing plans available at this time.</p>';
    }
    
    ob_start();
    ?>
    <div class="orabooks-pricing-container">
        <div class="orabooks-pricing-grid columns-<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($levels as $level): ?>
                <?php
                // Get features assigned to this level
                $features = $wpdb->get_results($wpdb->prepare(
                    "SELECT feature_key as slug, feature_name as name FROM {$wpdb->orabooks_feature_assignments}
                    WHERE level_id = %d AND access_type != 'none'
                    ORDER BY feature_name ASC",
                    $level->id
                ));
                
                // Check if this level has the highlighted feature
                $is_highlighted = false;
                $highlighted_feature_name = '';
                foreach ($features as $feature) {
                    if ($feature->slug === $highlighted_feature) {
                        $is_highlighted = true;
                        $highlighted_feature_name = $feature->name;
                        break;
                    }
                }
                
                $highlight_class = $is_highlighted ? 'highlighted-plan' : '';
                $popular_class = ($level->is_popular == 1) ? 'popular-plan' : '';
                ?>
                
                <div class="orabooks-pricing-card <?php echo $highlight_class; ?> <?php echo $popular_class; ?>">
                    <?php if ($is_highlighted): ?>
                        <div class="highlight-badge">
                            <span class="badge-icon">⭐</span>
                            <span class="badge-text">Includes <?php echo esc_html($highlighted_feature_name); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($level->is_popular == 1 && !$is_highlighted): ?>
                        <div class="popular-badge">Most Popular</div>
                    <?php endif; ?>
                    
                    <div class="pricing-card-header">
                        <h3 class="plan-name"><?php echo esc_html($level->name); ?></h3>
                        <?php if (!empty($level->description)): ?>
                            <p class="plan-description"><?php echo esc_html($level->description); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pricing-card-price">
                        <span class="currency">$</span>
                        <span class="amount"><?php echo esc_html(number_format($level->price, 2)); ?></span>
                        <span class="period">/<?php echo esc_html($level->billing_period); ?></span>
                    </div>
                    
                    <?php if ($atts['show_features'] === 'yes' && !empty($features)): ?>
                        <div class="pricing-card-features">
                            <h4>Features Included:</h4>
                            <ul class="features-list">
                                <?php foreach ($features as $feature): ?>
                                    <?php 
                                    $feature_highlight = ($feature->slug === $highlighted_feature) ? 'highlighted-feature' : '';
                                    ?>
                                    <li class="<?php echo $feature_highlight; ?>">
                                        <span class="feature-icon">✓</span>
                                        <span class="feature-name"><?php echo esc_html($feature->name); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="pricing-card-footer">
                        <?php if (is_user_logged_in()): ?>
                            <button class="orabooks-select-plan-btn" 
                                    data-level-id="<?php echo esc_attr($level->id); ?>"
                                    data-level-name="<?php echo esc_attr($level->name); ?>"
                                    data-level-price="<?php echo esc_attr($level->price); ?>">
                                Select <?php echo esc_html($level->name); ?>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="login-btn">
                                Sign In to View Pricing
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
    .orabooks-pricing-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }
    
    .orabooks-pricing-grid {
        display: grid;
        gap: 30px;
        margin-top: 30px;
    }
    
    .orabooks-pricing-grid.columns-2 {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }
    
    .orabooks-pricing-grid.columns-3 {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
    
    .orabooks-pricing-grid.columns-4 {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
    
    .orabooks-pricing-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 30px;
        position: relative;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .orabooks-pricing-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .orabooks-pricing-card.highlighted-plan {
        border-color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        border-width: 3px;
        box-shadow: 0 8px 30px rgba(67, 166, 45, 0.3);
        transform: scale(1.05);
    }
    
    .orabooks-pricing-card.popular-plan {
        border-color: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
    }
    
    .highlight-badge {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, #2d7a1f);
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(67, 166, 45, 0.4);
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    
    .badge-icon {
        font-size: 16px;
    }
    
    .popular-badge {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
        color: white;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .pricing-card-header {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .plan-name {
        font-size: 24px;
        font-weight: 700;
        color: #333;
        margin: 0 0 10px 0;
    }
    
    .plan-description {
        font-size: 14px;
        color: #666;
        margin: 0;
    }
    
    .pricing-card-price {
        text-align: center;
        margin: 30px 0;
        padding: 20px 0;
        border-top: 1px solid #e0e0e0;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .currency {
        font-size: 24px;
        color: #666;
        vertical-align: top;
    }
    
    .amount {
        font-size: 48px;
        font-weight: 700;
        color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
    }
    
    .period {
        font-size: 16px;
        color: #666;
    }
    
    .pricing-card-features {
        margin: 25px 0;
    }
    
    .pricing-card-features h4 {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin: 0 0 15px 0;
    }
    
    .features-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .features-list li {
        padding: 10px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #555;
        font-size: 14px;
    }
    
    .features-list li.highlighted-feature {
        background: linear-gradient(90deg, rgba(67, 166, 45, 0.1), transparent);
        padding: 12px 10px;
        margin: 0 -10px;
        border-radius: 6px;
        font-weight: 600;
        color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
    }
    
    .feature-icon {
        width: 20px;
        height: 20px;
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
    }
    
    .highlighted-feature .feature-icon {
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        box-shadow: 0 0 0 3px rgba(67, 166, 45, 0.2);
    }
    
    .pricing-card-footer {
        margin-top: 30px;
    }
    
    .orabooks-select-plan-btn,
    .login-btn {
        width: 100%;
        padding: 15px 30px;
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        text-decoration: none;
        display: block;
    }
    
    .orabooks-select-plan-btn:hover,
    .login-btn:hover {
        background: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .highlighted-plan .orabooks-select-plan-btn {
        background: linear-gradient(135deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, #2d7a1f);
        box-shadow: 0 4px 12px rgba(67, 166, 45, 0.4);
    }
    
    @media (max-width: 768px) {
        .orabooks-pricing-grid {
            grid-template-columns: 1fr !important;
        }
        
        .orabooks-pricing-card.highlighted-plan {
            transform: scale(1);
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('.orabooks-select-plan-btn').on('click', function() {
            var levelId = $(this).data('level-id');
            var levelName = $(this).data('level-name');
            var levelPrice = $(this).data('level-price');
            
            // Show payment gateway selection modal
            orabooksShowPaymentModal(levelId, levelName, levelPrice);
        });
    });
    
    function orabooksShowPaymentModal(levelId, levelName, levelPrice) {
        // This function should trigger the payment gateway selection
        // You can customize this based on your existing payment flow
        
        // For now, we'll trigger a custom event that your payment system can listen to
        jQuery(document).trigger('orabooks_plan_selected', {
            level_id: levelId,
            level_name: levelName,
            level_price: levelPrice
        });
        
        // Or redirect to payment page
        var paymentUrl = '<?php echo home_url("/payment"); ?>?level_id=' + levelId;
        window.location.href = paymentUrl;
    }
    </script>
    <?php
    
    return ob_get_clean();
}
add_shortcode('orabooks_pricing_enhanced', 'orabooks_pricing_with_highlight_shortcode');

/**
 * Modify features shortcode to add feature parameter to links
 */
function orabooks_features_with_pricing_link_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => '3',
        'show_description' => 'yes'
    ), $atts);
    
    // Check user access
    if (!is_user_logged_in()) {
        return '<div class="orabooks-message info">
            <p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to view available features.</p>
        </div>';
    }
    
    $user_id = get_current_user_id();
    $user_level = get_user_meta($user_id, 'orabooks_level', true);
    
    // Get all unique features from assignments
    global $wpdb;
    orabooks_handle_multisite_tables();
    $features = $wpdb->get_results("SELECT DISTINCT feature_key as slug, feature_name as name, '📦' as icon, '' as description FROM {$wpdb->orabooks_feature_assignments} ORDER BY feature_name ASC");
    
    if (empty($features)) {
        return '<p>No features available at this time.</p>';
    }
    
    // Get pricing page URL
    $pricing_page = orabooks_get_page_by_title('Pricing');
    $pricing_url = $pricing_page ? get_permalink($pricing_page->ID) : home_url('/pricing');
    
    ob_start();
    ?>
    <div class="orabooks-features-container">
        <div class="orabooks-features-grid columns-<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($features as $feature): ?>
                <?php
                // Check if user has access to this feature
                $has_access = false;
                if ($user_level) {
                    $assignment = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->orabooks_feature_assignments} 
                        WHERE feature_key = %s AND level_id = %d AND access_type != 'none'",
                        $feature->slug,
                        $user_level
                    ));
                    $has_access = !empty($assignment);
                }
                
                $feature_link = $has_access ? 
                    home_url('/' . $feature->slug) : 
                    add_query_arg('highlight_feature', $feature->slug, $pricing_url);
                ?>
                
                <div class="orabooks-feature-card <?php echo $has_access ? 'has-access' : 'no-access'; ?>">
                    <div class="feature-icon">
                        <?php echo !empty($feature->icon) ? $feature->icon : '📦'; ?>
                    </div>
                    
                    <h3 class="feature-name"><?php echo esc_html($feature->name); ?></h3>
                    
                    <?php if ($atts['show_description'] === 'yes' && !empty($feature->description)): ?>
                        <p class="feature-description"><?php echo esc_html($feature->description); ?></p>
                    <?php endif; ?>
                    
                    <div class="feature-footer">
                        <?php if ($has_access): ?>
                            <a href="<?php echo esc_url($feature_link); ?>" class="feature-btn access-btn">
                                Access Feature
                            </a>
                        <?php else: ?>
                            <a href="<?php echo esc_url($feature_link); ?>" class="feature-btn upgrade-btn">
                                View Plans
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
    .orabooks-features-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }
    
    .orabooks-features-grid {
        display: grid;
        gap: 25px;
    }
    
    .orabooks-features-grid.columns-2 {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }
    
    .orabooks-features-grid.columns-3 {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
    
    .orabooks-features-grid.columns-4 {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    
    .orabooks-feature-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .orabooks-feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .orabooks-feature-card.has-access {
        border-color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
    }
    
    .feature-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }
    
    .feature-name {
        font-size: 20px;
        font-weight: 600;
        color: #333;
        margin: 0 0 10px 0;
    }
    
    .feature-description {
        font-size: 14px;
        color: #666;
        margin: 0 0 20px 0;
        line-height: 1.6;
    }
    
    .feature-footer {
        margin-top: 20px;
    }
    
    .feature-btn {
        display: inline-block;
        padding: 12px 30px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .access-btn {
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        color: white;
    }
    
    .access-btn:hover {
        background: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
        transform: translateY(-2px);
    }
    
    .upgrade-btn {
        background: #f0f0f0;
        color: #333;
        border: 2px solid <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
    }
    
    .upgrade-btn:hover {
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        color: white;
    }
    
    @media (max-width: 768px) {
        .orabooks-features-grid {
            grid-template-columns: 1fr !important;
        }
    }
    </style>
    <?php
    
    return ob_get_clean();
}
add_shortcode('orabooks_features_enhanced', 'orabooks_features_with_pricing_link_shortcode');
