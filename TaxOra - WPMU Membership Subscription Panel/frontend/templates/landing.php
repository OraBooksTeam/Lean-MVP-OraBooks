<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title(''); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('taxora-landing-page'); ?>>
    <div class="taxora-landing-container">
        <!-- Hero Section -->
        <section class="taxora-hero">
            <div class="taxora-hero-content">
                <div class="taxora-hero-text">
                    <h1 class="taxora-hero-title">Professional Membership Management</h1>
                    <p class="taxora-hero-subtitle">Grow your business with our powerful subscription platform</p>
                    <div class="taxora-hero-features">
                        <div class="taxora-feature">
                            <div class="taxora-feature-icon">✓</div>
                            <div class="taxora-feature-text">Unlimited Members</div>
                        </div>
                        <div class="taxora-feature">
                            <div class="taxora-feature-icon">✓</div>
                            <div class="taxora-feature-text">Advanced Analytics</div>
                        </div>
                        <div class="taxora-feature">
                            <div class="taxora-feature-icon">✓</div>
                            <div class="taxora-feature-text">Priority Support</div>
                        </div>
                        <div class="taxora-feature">
                            <div class="taxora-feature-icon">✓</div>
                            <div class="taxora-feature-text">Secure Payments</div>
                        </div>
                    </div>
                </div>
                <div class="taxora-hero-cta">
                    <a href="#pricing" class="taxora-cta-button taxora-cta-primary">Get Started</a>
                    <a href="#pricing" class="taxora-cta-button taxora-cta-secondary">View Plans</a>
                </div>
            </div>
        </section>
        
        <!-- Pricing Section -->
        <section id="pricing" class="taxora-pricing">
            <div class="taxora-container">
                <h2 class="taxora-section-title">Choose Your Plan</h2>
                <div class="taxora-pricing-grid">
                    <?php
                    $plans = $this->get_all_plans();
                    foreach ($plans as $plan) {
                        $is_popular = $plan->is_popular ? 'taxora-plan-popular' : '';
                        $is_featured = $plan->id == 2 ? 'taxora-plan-featured' : '';
                        ?>
                        <div class="taxora-plan-card <?php echo $is_popular; ?> <?php echo $is_featured; ?>">
                            <div class="taxora-plan-header">
                                <h3 class="taxora-plan-name"><?php echo esc_html($plan->name); ?></h3>
                                <?php if ($plan->is_popular): ?>
                                    <span class="taxora-plan-badge taxora-badge-popular">Most Popular</span>
                                <?php endif; ?>
                                <?php if ($plan->id == 2): ?>
                                    <span class="taxora-plan-badge taxora-badge-featured">Recommended</span>
                                <?php endif; ?>
                            </div>
                            <div class="taxora-plan-price">
                                <div class="taxora-price-amount">$<?php echo number_format($plan->price_monthly, 2); ?></div>
                                <div class="taxora-price-period">per month</div>
                                <div class="taxora-price-yearly">
                                    $<?php echo number_format($plan->price_yearly, 2); ?> <span class="taxora-price-period">per year</span>
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
            </div>
        </section>
        
        <!-- Features Section -->
        <section class="taxora-features">
            <div class="taxora-container">
                <h2 class="taxora-section-title">Why Choose TaxOra?</h2>
                <div class="taxora-features-grid">
                    <div class="taxora-feature-item">
                        <div class="taxora-feature-icon">🚀</div>
                        <div class="taxora-feature-content">
                            <h3>Lightning Fast</h3>
                            <p>Optimized performance with instant access</p>
                        </div>
                    </div>
                    <div class="taxora-feature-item">
                        <div class="taxora-feature-icon">🔒</div>
                        <div class="taxora-feature-content">
                            <h3>Secure & Reliable</h3>
                            <p>Enterprise-grade security with 99.9% uptime</p>
                        </div>
                    </div>
                    <div class="taxora-feature-item">
                        <div class="taxora-feature-icon">📊</div>
                        <div class="taxora-feature-content">
                            <h3>Advanced Analytics</h3>
                            <p>Detailed insights and reporting tools</p>
                        </div>
                    </div>
                    <div class="taxora-feature-item">
                        <div class="taxora-feature-icon">💬</div>
                        <div class="taxora-feature-content">
                            <h3>24/7 Support</h3>
                            <p>Expert assistance whenever you need it</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Testimonials Section -->
        <section class="taxora-testimonials">
            <div class="taxora-container">
                <h2 class="taxora-section-title">What Our Customers Say</h2>
                <div class="taxora-testimonials-grid">
                    <div class="taxora-testimonial">
                        <div class="taxora-testimonial-content">
                            <p>"TaxOra transformed our business operations. The analytics alone helped us increase efficiency by 40%."</p>
                            <div class="taxora-testimonial-author">- Sarah Johnson, CEO</div>
                        </div>
                    </div>
                    <div class="taxora-testimonial">
                        <div class="taxora-testimonial-content">
                            <p>"The best membership platform we've ever used. Highly recommended!"</p>
                            <div class="taxora-testimonial-author">- Michael Chen, Business Owner</div>
                        </div>
                    </div>
                    <div class="taxora-testimonial">
                        <div class="taxora-testimonial-content">
                            <p>"Simple, powerful, and exactly what we needed for our growing company."</p>
                            <div class="taxora-testimonial-author">- Emily Davis, Startup Founder</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- CTA Section -->
        <section class="taxora-cta">
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
