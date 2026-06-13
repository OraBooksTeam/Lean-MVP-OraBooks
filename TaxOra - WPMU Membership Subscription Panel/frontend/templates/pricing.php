<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title(''); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('taxora-pricing-page'); ?>>
    <div class="taxora-pricing-container">
        <!-- Header -->
        <header class="taxora-pricing-header">
            <div class="taxora-container">
                <h1 class="taxora-pricing-title">Simple, Transparent Pricing</h1>
                <p class="taxora-pricing-subtitle">Choose the perfect plan for your business needs</p>
            </div>
        </header>
        
        <!-- Pricing Plans -->
        <section class="taxora-pricing-plans">
            <div class="taxora-container">
                <?php
                $plans = $this->get_all_plans();
                foreach ($plans as $plan) {
                    $is_popular = $plan->is_popular ? 'taxora-plan-popular' : '';
                    $is_featured = $plan->id == 2 ? 'taxora-plan-featured' : '';
                    ?>
                    <div class="taxora-plan-card <?php echo $is_popular; ?> <?php echo $is_featured; ?>">
                        <div class="taxora-plan-header">
                            <h3 class="taxora-plan-name"><?php echo esc_html($plan->name); ?></h3>
                            <div class="taxora-plan-badges">
                                <?php if ($plan->is_popular): ?>
                                    <span class="taxora-badge taxora-badge-popular">Most Popular</span>
                                <?php endif; ?>
                                <?php if ($plan->id == 2): ?>
                                    <span class="taxora-badge taxora-badge-featured">Recommended</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="taxora-plan-price">
                            <div class="taxora-price-amount">$<?php echo number_format($plan->price_monthly, 2); ?></div>
                            <div class="taxora-price-period">per month</div>
                            <div class="taxora-price-yearly">
                                $<?php echo number_format($plan->price_yearly, 2); ?> <span class="taxora-price-period">per year</span>
                                <span class="taxora-price-save">Save 20%</span>
                            </div>
                        </div>
                        <div class="taxora-plan-features">
                            <?php
                                $features = json_decode($plan->features, true);
                                foreach ($features as $feature => $enabled) {
                                    if ($enabled) {
                                        echo '<div class="taxora-feature taxora-feature-enabled">' . esc_html(ucfirst(str_replace('_', ' ', $feature))) . '</div>';
                                    } else {
                                        echo '<div class="taxora-feature taxora-feature-disabled">' . esc_html(ucfirst(str_replace('_', ' ', $feature))) . '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="taxora-plan-users">
                            <span class="taxora-users-label">Users:</span>
                            <span class="taxora-users-value"><?php echo $plan->max_users == -1 ? 'Unlimited' : $plan->max_users; ?></span>
                        </div>
                        <div class="taxora-plan-actions">
                            <a href="#register" class="taxora-button taxora-button-primary">Choose Plan</a>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </section>
        
        <!-- FAQ Section -->
        <section class="taxora-pricing-faq">
            <div class="taxora-container">
                <h2 class="taxora-section-title">Frequently Asked Questions</h2>
                <div class="taxora-faq-grid">
                    <div class="taxora-faq-item">
                        <h3>Can I change plans anytime?</h3>
                        <p>Yes! You can upgrade or downgrade your plan at any time. Changes take effect immediately.</p>
                    </div>
                    <div class="taxora-faq-item">
                        <h3>Is there a setup fee?</h3>
                        <p>No setup fees. You only pay for the plan you choose.</p>
                    </div>
                    <div class="taxora-faq-item">
                        <h3>Can I cancel anytime?</h3>
                        <p>Yes, you can cancel your subscription at any time. No cancellation fees apply.</p>
                    </div>
                    <div class="taxora-faq-item">
                        <h3>What payment methods do you accept?</h3>
                        <p>We accept all major credit cards, PayPal, and bank transfers for Enterprise plans.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- CTA Section -->
        <section class="taxora-pricing-cta">
            <div class="taxora-container">
                <div class="taxora-cta-content">
                    <h2>Ready to Get Started?</h2>
                    <p>Join thousands of businesses already using TaxOra Membership Platform</p>
                    <div class="taxora-cta-buttons">
                        <a href="#register" class="taxora-button taxora-button-large taxora-button-primary">Start Free Trial</a>
                        <a href="#contact" class="taxora-button taxora-button-large taxora-button-outline">Contact Sales</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
