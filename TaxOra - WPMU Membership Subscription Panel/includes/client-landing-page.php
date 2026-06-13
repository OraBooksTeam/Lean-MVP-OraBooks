<?php
/**
 * Client Landing Page with Purchased Features
 * 
 * Displays all purchased features on client site with direct access
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client Features Dashboard Shortcode
 * Usage: [orabooks_client_dashboard]
 */
function orabooks_client_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return do_shortcode('[orabooks_client_home]');
    }
    
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    
    // Get user's subscription/level
    $user_level = get_user_meta($user_id, 'orabooks_level', true);
    
    if (!$user_level) {
        return '<div class="orabooks-message warning">
            <p>You don\'t have an active subscription. <a href="' . home_url('/upgrade-plan') . '">View Plans</a></p>
        </div>';
    }
    
    // Get level details
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $level = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d",
        $user_level
    ));
    
    // Get assigned features
    // Get assigned features
    $assignments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->orabooks_feature_assignments} WHERE level_id = %d",
        $user_level
    ));

    $available_features = orabooks_get_available_features();
    $features = array();

    if ($assignments) {
        foreach ($assignments as $assignment) {
            $key = $assignment->feature_key;
            $feat = new stdClass();
            
            if (isset($available_features[$key])) {
                $def = $available_features[$key];
                $feat->name = $def['name'];
                $feat->description = isset($def['description']) ? $def['description'] : '';
                $feat->icon = isset($def['icon']) ? $def['icon'] : '📦';
                $feat->slug = $key;
                $feat->category = isset($def['category']) ? $def['category'] : 'default';
                $feat->url = isset($def['url']) ? $def['url'] : '';
            } else {
                // Fallback from DB assignment
                $feat->name = $assignment->feature_name;
                $feat->description = '';
                $feat->icon = '📦';
                $feat->slug = $key;
                $feat->category = 'default';
                $feat->url = '';
            }
            $features[] = $feat;
        }
    }
    
    ob_start();
    ?>
    <div class="orabooks-client-dashboard">
        <!-- Welcome Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <?php 
                $company_name = get_user_meta($user_id, 'billing_company', true);
                $display_name = !empty($company_name) ? $company_name : $user->display_name;
                ?>
                <h1><?php echo esc_html($display_name); ?> Panel! 👋</h1>
                <p class="subtitle">Your <?php echo $level ? esc_html($level->name) : 'Premium'; ?> Dashboard</p>
            </div>
            
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo count($features); ?></div>
                        <div class="stat-label">Features</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $level ? esc_html($level->name) : 'Premium'; ?></div>
                        <div class="stat-label">Plan</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🌐</div>
                    <div class="stat-info">
                        <div class="stat-value">Active</div>
                        <div class="stat-label">Status</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Features Grid -->
        <div class="dashboard-features">
            <h2>Your Features</h2>
            <p class="section-subtitle">Click any feature to access its dashboard</p>
            
            <div class="features-grid">
                <?php if (!empty($features)): ?>
                    <?php foreach ($features as $feature): ?>
                        <?php
                        // Determine feature URL
                        $feature_url = home_url('/' . $feature->slug);
                        
                        // Check if feature has a custom URL
                        if (!empty($feature->url)) {
                            $feature_url = $feature->url;
                        }
                        
                        // Get feature icon
                        $icon = !empty($feature->icon) ? $feature->icon : '📦';
                        
                        // Get feature color based on category
                        $category_colors = array(
                            'business' => '#43a62d',
                            'productivity' => '#2d7a1d',
                            'analytics' => '#ff9800',
                            'communication' => '#9c27b0',
                            'default' => '#607d8b'
                        );
                        $color = isset($category_colors[$feature->category]) ? $category_colors[$feature->category] : $category_colors['default'];
                        ?>
                        
                        <a href="<?php echo esc_url($feature_url); ?>" class="feature-card" data-feature="<?php echo esc_attr($feature->slug); ?>">
                            <div class="feature-icon" style="background: <?php echo esc_attr($color); ?>;">
                                <?php echo $icon; ?>
                            </div>
                            <div class="feature-content">
                                <h3 class="feature-name"><?php echo esc_html($feature->name); ?></h3>
                                <?php if (!empty($feature->description)): ?>
                                    <p class="feature-description"><?php echo esc_html($feature->description); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="feature-arrow">→</div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-features">
                        <p>No features available in your plan.</p>
                        <a href="<?php echo home_url('/upgrade-plan'); ?>" class="upgrade-btn">Upgrade Plan</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="dashboard-actions">
            <h2>Quick Actions</h2>
            
            <div class="actions-grid">
                <a href="<?php echo home_url('/upgrade-plan'); ?>" class="action-card">
                    <div class="action-icon">⬆️</div>
                    <div class="action-content">
                        <h3>Upgrade Plan</h3>
                        <p>Get access to more features</p>
                    </div>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=orabooks-site-customization'); ?>" class="action-card">
                    <div class="action-icon">🎨</div>
                    <div class="action-content">
                        <h3>Customize Site</h3>
                        <p>Change theme, logo, and menu</p>
                    </div>
                </a>
                
                <a href="<?php echo home_url('/my-account'); ?>" class="action-card">
                    <div class="action-icon">👤</div>
                    <div class="action-content">
                        <h3>My Account</h3>
                        <p>Manage your profile and settings</p>
                    </div>
                </a>
                
                <a href="<?php echo network_site_url('/support'); ?>" class="action-card">
                    <div class="action-icon">❓</div>
                    <div class="action-content">
                        <h3>Get Support</h3>
                        <p>Contact our support team</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
    
    <style>
    .orabooks-client-dashboard {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }
    
    /* Header */
    .dashboard-header {
        background: linear-gradient(135deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, <?php echo ORABOOKS_SECONDARY_COLOR; ?>);
        color: white;
        padding: 40px;
        border-radius: 16px;
        margin-bottom: 40px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }
    
    .welcome-section h1 {
        margin: 0 0 10px 0;
        font-size: 32px;
        font-weight: 700;
        color: white;
    }
    
    .welcome-section .subtitle {
        margin: 0;
        font-size: 18px;
        opacity: 0.9;
    }
    
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }
    
    .stat-card {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        padding: 20px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .stat-icon {
        font-size: 32px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: white;
    }
    
    .stat-label {
        font-size: 14px;
        opacity: 0.9;
    }
    
    /* Features Section */
    .dashboard-features {
        margin-bottom: 40px;
    }
    
    .dashboard-features h2 {
        font-size: 28px;
        margin: 0 0 10px 0;
        color: #333;
    }
    
    .section-subtitle {
        color: #666;
        margin: 0 0 30px 0;
    }
    
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .feature-card {
        background: white;
        border: none;
        border-radius: 12px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        text-decoration: none;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    
    .feature-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        transform: scaleY(0);
        transition: transform 0.3s ease;
    }
    
    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        border-color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
    }
    
    .feature-card:hover::before {
        transform: scaleY(1);
    }
    
    .feature-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .feature-content {
        flex: 1;
    }
    
    .feature-name {
        margin: 0 0 5px 0;
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }
    
    .feature-description {
        margin: 0;
        font-size: 14px;
        color: #666;
        line-height: 1.5;
    }
    
    .feature-arrow {
        font-size: 24px;
        color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        opacity: 0;
        transform: translateX(-10px);
        transition: all 0.3s ease;
    }
    
    .feature-card:hover .feature-arrow {
        opacity: 1;
        transform: translateX(0);
    }
    
    /* Actions Section */
    .dashboard-actions {
        margin-bottom: 40px;
    }
    
    .dashboard-actions h2 {
        font-size: 28px;
        margin: 0 0 30px 0;
        color: #333;
    }
    
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .action-card {
        background: white;
        border: none;
        border-radius: 12px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        /* border-color removed */
    }
    
    .action-icon {
        font-size: 32px;
    }
    
    .action-content h3 {
        margin: 0 0 5px 0;
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    
    .action-content p {
        margin: 0;
        font-size: 14px;
        color: #666;
    }
    
    .no-features {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        background: #f8f9fa;
        border-radius: 12px;
    }
    
    .no-features p {
        font-size: 18px;
        color: #666;
        margin: 0 0 20px 0;
    }
    
    .upgrade-btn {
        display: inline-block;
        padding: 12px 30px;
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .upgrade-btn:hover {
        background: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 30px 20px;
        }
        
        .welcome-section h1 {
            font-size: 24px;
        }
        
        .quick-stats {
            grid-template-columns: 1fr;
        }
        
        .features-grid,
        .actions-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Add click animation
        $('.feature-card').on('click', function(e) {
            $(this).addClass('clicking');
            setTimeout(() => {
                $(this).removeClass('clicking');
            }, 300);
        });
    });
    </script>
    <?php
    
    return ob_get_clean();
}
/* 
 * The orabooks_client_dashboard shortcode is now handled by 
 * Orabooks_Client_Dashboard_Manager in includes/frontend/client-dashboard-manager.php
 */
// add_shortcode('orabooks_client_dashboard', 'orabooks_client_dashboard_shortcode');

/**
 * Client Homepage Shortcode
 * Usage: [orabooks_client_home]
 */
if (!function_exists('orabooks_client_home_shortcode')) {
function orabooks_client_home_shortcode() {
    ob_start();
    ?>
    <div class="orabooks-saas-home">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <span class="badge-new">New Features Available</span>
                <h1>Manage Your Business <br> <span class="text-gradient">With Confidence</span></h1>
                <p class="hero-subtitle">The all-in-one platform for modern businesses. Streamline operations, track finances, and boost productivity tailored to your needs.</p>
                <div class="hero-actions">
                    <?php if (is_user_logged_in()): ?>
                        <?php 
                        $dashboard_page_id = get_option('orabooks_dashboard_page');
                        $dashboard_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : home_url('/ora-dashboard');
                        ?>
                        <a href="<?php echo esc_url($dashboard_url); ?>" class="btn-primary">Go to Dashboard</a>
                        <a href="<?php echo home_url('/features'); ?>" class="btn-secondary">View My Features</a>
                    <?php else: ?>
                        <!-- Get Started Free button removed -->
                    <?php endif; ?>
                </div>
                <div class="trust-indicators">
                    <span>Trusted by 500+ Businesses</span>
                    <div class="trust-avian">
                        <span class="avatar" style="background: #FF5722;">👨‍💼</span>
                        <span class="avatar" style="background: #2196F3;">👩‍💻</span>
                        <span class="avatar" style="background: #4CAF50;">🧑‍🎨</span>
                        <span class="avatar-more">+5k</span>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="visual-card main-card">
                    <div class="card-header">
                        <div class="dot red"></div>
                        <div class="dot yellow"></div>
                        <div class="dot green"></div>
                    </div>
                    <div class="card-body">
                        <div class="graph-placeholder">
                            <div class="bar" style="height: 40%"></div>
                            <div class="bar" style="height: 70%"></div>
                            <div class="bar" style="height: 50%"></div>
                            <div class="bar" style="height: 85%"></div>
                            <div class="bar active" style="height: 100%"></div>
                        </div>
                        <div class="stats-row">
                            <div class="stat">
                                <span class="label">Revenue</span>
                                <span class="value">$12.4k</span>
                            </div>
                            <div class="stat">
                                <span class="label">Growth</span>
                                <span class="value success">+24%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="visual-card floating-card card-1">
                    <span>🚀 Performance</span>
                </div>
                <div class="visual-card floating-card card-2">
                    <span>👥 Team Sync</span>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section">
            <div class="section-header">
                <h2>Why Choose Our Platform?</h2>
                <p>Powerful tools designed to help your business grow.</p>
            </div>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon-wrapper" style="background: rgba(33, 150, 243, 0.1); color: #2196F3;">
                        <span class="feature-icon">📊</span>
                    </div>
                    <h3>Advanced Analytics</h3>
                    <p>Track your performance with real-time data and detailed insights to make informed decisions.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon-wrapper" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                        <span class="feature-icon">⚡</span>
                    </div>
                    <h3>Lightning Fast</h3>
                    <p>Built with modern technology for speed, security, and reliability you can count on.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon-wrapper" style="background: rgba(156, 39, 176, 0.1); color: #9C27B0;">
                        <span class="feature-icon">🤝</span>
                    </div>
                    <h3>Team Collaboration</h3>
                    <p>Invite your team, assign roles, and work together seamlessly in one unified workspace.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon-wrapper" style="background: rgba(255, 152, 0, 0.1); color: #FF9800;">
                        <span class="feature-icon">💰</span>
                    </div>
                    <h3>Financial Tools</h3>
                    <p>Manage invoices, expenses, and payroll comfortably from a single dashboard.</p>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="testimonials-section">
            <div class="section-header">
                <h2>What Our Users Say</h2>
                <p>Join hundreds of satisfied business owners.</p>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p class="quote">"This platform has completely transformed how we manage our day-to-day operations. Highly recommended!"</p>
                    <div class="author">
                        <div class="author-avatar">👩</div>
                        <div class="author-info">
                            <strong>Sarah J.</strong>
                            <span>Small Business Owner</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p class="quote">"The analytics features are incredible. I can finally see where my business is heading in real-time."</p>
                    <div class="author">
                        <div class="author-avatar">👨</div>
                        <div class="author-info">
                            <strong>Mike T.</strong>
                            <span>Freelancer</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p class="quote">"Simple, intuitive, and powerful. Exactly what I needed to organize my agency's workflow."</p>
                    <div class="author">
                        <div class="author-avatar">👩‍💼</div>
                        <div class="author-info">
                            <strong>Emily R.</strong>
                            <span>Agency Director</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <div class="cta-content">
                <h2>Ready to scale your business?</h2>
                <p>Join thousands of businesses that trust us to power their growth.</p>
                <div class="cta-actions">
                    <?php if (!is_user_logged_in()): ?>
                        <a href="<?php echo esc_url(network_site_url('wp-signup.php')); ?>" class="btn-primary btn-white">Start Your Free Trial</a>
                    <?php else: ?>
                        <?php 
                        $dashboard_page_id = get_option('orabooks_dashboard_page');
                        $dashboard_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : home_url('/ora-dashboard');
                        ?>
                        <a href="<?php echo esc_url($dashboard_url); ?>" class="btn-primary btn-white">Go to Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Simple Footer -->
        <footer class="simple-footer">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><?php echo get_bloginfo('name'); ?></h3>
                    <p>&copy; <?php echo date('Y'); ?> All rights reserved.</p>
                </div>

            </div>
        </footer>
        
        <style>
        .orabooks-saas-home {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .orabooks-saas-home * {
            box-sizing: border-box;
        }

        /* Buttons */
        .btn-primary, .btn-secondary {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .btn-primary {
            background: linear-gradient(135deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, <?php echo ORABOOKS_SECONDARY_COLOR; ?>);
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: #333;
            border: none;
            margin-left: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #ccc;
            color: #333;
        }

        .btn-white {
            background: white !important;
            color: <?php echo ORABOOKS_PRIMARY_COLOR; ?> !important;
        }

        /* Hero Section */
        .hero-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 100px 20px;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
        }

        .hero-content {
            flex: 1;
            padding-right: 50px;
            z-index: 2;
        }

        .badge-new {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }

        .hero-content h1 {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.1;
            margin: 0 0 20px 0;
            letter-spacing: -1px;
            color: #1a1a1a;
        }

        .text-gradient {
            background: linear-gradient(135deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, <?php echo ORABOOKS_SECONDARY_COLOR; ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 20px;
            color: #666;
            margin: 0 0 40px 0;
            max-width: 500px;
        }

        .trust-indicators {
            margin-top: 40px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .trust-avian {
            display: flex;
        }

        .avatar, .avatar-more {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            margin-left: -10px;
            font-size: 12px;
        }

        .avatar:first-child { margin-left: 0; }
        .avatar-more { background: #f0f0f0; color: #666; font-size: 10px; font-weight: 700; }

        /* Hero Visual */
        .hero-visual {
            flex: 1;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .visual-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            border: none;
        }

        .main-card {
            width: 400px;
            padding: 20px;
            z-index: 1;
            position: relative;
        }

        .card-header {
            display: flex;
            gap: 6px;
            margin-bottom: 20px;
        }

        .dot { width: 10px; height: 10px; border-radius: 50%; }
        .red { background: #ff5f57; }
        .yellow { background: #ffbd2e; }
        .green { background: #28ca41; }

        .graph-placeholder {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 150px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .bar {
            width: 12%;
            background: #ebecf0;
            border-radius: 4px 4px 0 0;
            transition: height 1s ease;
        }

        .bar.active {
            background: linear-gradient(to top, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, <?php echo ORABOOKS_SECONDARY_COLOR; ?>);
        }

        .stats-row {
            display: flex;
            justify-content: space-between;
        }

        .stat {
            display: flex;
            flex-direction: column;
        }

        .stat .label { font-size: 12px; color: #999; }
        .stat .value { font-size: 18px; font-weight: 700; color: #333; }
        .stat .value.success { color: #4CAF50; }

        .floating-card {
            position: absolute;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 14px;
            animation: float 6s ease-in-out infinite;
            z-index: 5;
        }

        .card-1 {
            top: -20px;
            right: 20px;
            animation-delay: 0s;
        }

        .card-2 {
            bottom: -10px;
            left: -10px;
            animation-delay: 3s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Features Section */
        .features-section {
            padding: 100px 20px;
            background: #f9fafb;
        }

        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 60px;
        }

        .section-header h2 { font-size: 36px; font-weight: 800; margin-bottom: 15px; color: #1a1a1a; }
        .section-header p { font-size: 18px; color: #666; }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-item {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
        }

        .feature-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .feature-item h3 { font-size: 20px; font-weight: 700; margin-bottom: 10px; color: #2d3748; }

        /* Testimonials */
        .testimonials-section {
            padding: 100px 20px;
            background: white;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .testimonial-card {
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            border: none;
        }

        .stars { margin-bottom: 15px; font-size: 14px; }
        .quote { font-style: italic; color: #555; margin-bottom: 20px; font-size: 16px; }
        
        .author { display: flex; align-items: center; gap: 12px; }
        .author-avatar {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .author-info { display: flex; flex-direction: column; }
        .author-info strong { font-size: 14px; color: #333; }
        .author-info span { font-size: 12px; color: #888; }

        /* CTA Section */
        .cta-section {
            padding: 100px 20px;
            background: linear-gradient(135deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, <?php echo ORABOOKS_SECONDARY_COLOR; ?>);
            color: white;
            text-align: center;
        }

        .cta-content { max-width: 800px; margin: 0 auto; }
        .cta-content h2 { font-size: 42px; font-weight: 800; margin-bottom: 20px; color: white; }
        .cta-content p { font-size: 20px; margin-bottom: 40px; opacity: 0.9; }

        /* Footer */
        .simple-footer {
            padding: 40px 20px;
            background: #1a1a1a;
            color: white;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-brand h3 { margin: 0 0 5px 0; font-size: 20px; }
        .footer-brand p { margin: 0; color: #888; font-size: 14px; }
        
        .footer-links { display: flex; gap: 20px; }
        .footer-links a { color: #ccc; text-decoration: none; transition: color 0.2s; }
        .footer-links a:hover { color: white; }

        @media (max-width: 768px) {
            .hero-section {
                flex-direction: column;
                text-align: center;
                padding-top: 60px;
            }
            
            .hero-content { padding-right: 0; margin-bottom: 60px; }
            .hero-content h1 { font-size: 36px; }
            .trust-indicators { justify-content: center; flex-direction: column; }
            
            .hero-actions {
                justify-content: center;
                flex-direction: column;
                align-items: center;
            }
            
            .btn-secondary { margin-left: 0; margin-top: 10px; width: 100%; max-width: 250px; text-align: center; }
            .btn-primary { width: 100%; max-width: 250px; text-align: center; }
            
            /* Make hero visual card wider on mobile */
            .main-card { 
                width: 90%; 
                max-width: 400px;
                margin: 0 auto;
            }
            
            .hero-visual {
                width: 100%;
                padding: 0 20px;
            }
            
            .footer-content { flex-direction: column; gap: 20px; text-align: center; }
        }
        </style>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('orabooks_client_home', 'orabooks_client_home_shortcode');
}

// Removed redundant orabooks_create_client_dashboard_page function
// Page creation is handled by includes/client-default-pages.php


