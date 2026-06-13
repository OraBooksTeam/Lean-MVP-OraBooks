jQuery(document).ready(function($) {
    // Clear any form persistence when navigating away from settings
    $(document).on('click', '.orabooks-admin-tabs .nav-tab', function(e) {
        // Only clear if it's a navigation to a different page
        if (!$(this).hasClass('nav-tab-active')) {
            // Clear any stored form data
            $('form').trigger('reset');
            sessionStorage.removeItem('orabooks_settings_form');
            localStorage.removeItem('orabooks_settings_form');
        }
    });
    
    // Force page reload when navigating between admin pages
    $(document).on('click', '.orabooks-admin-tabs .nav-tab', function(e) {
        var targetUrl = $(this).attr('href');
        var currentUrl = window.location.href;
        
        // If clicking on a different tab, force full page navigation
        if (targetUrl && targetUrl !== currentUrl && !targetUrl.includes('#')) {
            window.location.href = targetUrl;
            return false;
        }
    });

    // Feature assignment modal handling
    $(document).on('click', '.feature-assign-btn', function() {
        var levelId = $(this).data('level-id');
        var levelName = $(this).data('level-name');
        
        $('#orabooks-feature-modal .modal-title').text('Assign Features - ' + levelName);
        $('#orabooks-feature-modal').show();
        
        // Load feature assignment form via AJAX
        $.ajax({
            url: orabooksAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'orabooks_load_feature_assignment_form',
                level_id: levelId,
                nonce: orabooksAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#orabooks-feature-modal .modal-body').html(response.data.form);
                    $('#orabooks-feature-modal').data('level-id', levelId);
                }
            }
        });
    });
    
    // Save feature assignments - Updated to work with both modal IDs
    // Note: If button has inline onclick, it will handle the click first
    $(document).on('click', '#save-feature-assignments', function(e) {
        // Check if button has inline onclick handler - if so, let it handle it
        var $btn = $(this);
        if ($btn.attr('onclick')) {
            // Inline handler will process it, just prevent default
            return;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
        // Try both modal IDs for compatibility
        var $modal = $('#feature-modal').length ? $('#feature-modal') : $('#orabooks-feature-modal');
        var levelId = $modal.data('level-id') || $modal.attr('data-level-id');
        var features = {};
        
        // Collect all feature checkboxes (both checked and unchecked)
        $('.feature-checkbox').each(function() {
            var $checkbox = $(this);
            var featureKey = $checkbox.val();
            var featureName = $checkbox.data('feature-name') || $checkbox.attr('data-feature-name') || '';
            var $accessSelect = $('#access_type_' + featureKey);
            var accessType = $accessSelect.length ? $accessSelect.val() : 'full';
            
            features[featureKey] = {
                enabled: $checkbox.is(':checked') ? 'yes' : 'no',
                name: featureName,
                access_type: accessType
            };
        });
        
        if (!levelId) {
            alert('Error: Level ID not found');
            return;
        }
        
        // Show saving state
        var originalText = $btn.text();
        $btn.text('Saving...').prop('disabled', true);
        
        $.ajax({
            url: orabooksAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'orabooks_save_feature_assignments',
                level_id: levelId,
                features: features,
                nonce: orabooksAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $modal.hide();
                    alert('Feature assignments saved successfully!');
                    // Optionally reload the page to show updated data
                    // location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error saving features. Please check console for details.');
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Close modal
    $(document).on('click', '.close-modal, .modal-backdrop', function() {
        $('#orabooks-feature-modal').hide();
    });
    
    // Revenue chart date initialization
    function initRevenueChartDates() {
        var endDate = new Date();
        var startDate = new Date();
        startDate.setMonth(startDate.getMonth() - 11);
        
        $('#orabooksStartDate').val(startDate.toISOString().split('T')[0]);
        $('#orabooksEndDate').val(endDate.toISOString().split('T')[0]);
    }
    
    // Load revenue data
    function loadRevenueData(type, start, end) {
        $('.orabooks-dashboard-chart').addClass('loading');
        
        $.ajax({
            url: orabooksAdmin.ajax_url,
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'orabooks_get_revenue_data',
                type: type,
                start: start,
                end: end,
                nonce: orabooksAdmin.nonce
            },
            success: function(response) {
                if (response && response.labels && response.totals) {
                    renderRevenueChart(response.labels, response.totals, response.is_demo_data);
                } else {
                    renderRevenueChart(['No data'], [0], true);
                }
            },
            error: function() {
                renderRevenueChart(['Error loading data'], [0], true);
            },
            complete: function() {
                $('.orabooks-dashboard-chart').removeClass('loading');
            }
        });
    }
    
    // Render revenue chart
    function renderRevenueChart(labels, data, isDemo) {
        var ctx = document.getElementById('orabooksRevenueChart');
        if (!ctx) return;
        
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not loaded');
            return;
        }
        
        // Get the canvas element
        var canvas = ctx;
        if (ctx.tagName !== 'CANVAS') {
            canvas = ctx.querySelector('canvas');
        }
        if (!canvas) {
            console.error('Canvas element not found');
            return;
        }
        
        // Destroy existing chart properly using Chart.js API
        if (window.orabooksRevenueChart) {
            try {
                if (typeof window.orabooksRevenueChart.destroy === 'function') {
                    window.orabooksRevenueChart.destroy();
                }
            } catch (e) {
                console.warn('Error destroying chart:', e);
            }
            window.orabooksRevenueChart = null;
        }
        
        // Also check if Chart.js has a chart registered on this canvas
        if (typeof Chart !== 'undefined' && Chart.getChart) {
            var existingChart = Chart.getChart(canvas);
            if (existingChart) {
                try {
                    existingChart.destroy();
                } catch (e) {
                    console.warn('Error destroying existing chart:', e);
                }
            }
        }
        
        var chartColor = isDemo ? '#6c757d' : orabooksAdmin.primary_color;
        
        window.orabooksRevenueChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: isDemo ? 'Demo Revenue' : 'Revenue',
                    data: data,
                    borderColor: chartColor,
                    backgroundColor: isDemo ? 'rgba(108, 117, 125, 0.1)' : 'rgba(67, 166, 45, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Initialize dashboard
    if ($('.orabooks-dashboard').length) {
        initRevenueChartDates();
        
        $('#orabooksFilterApply').on('click', function(e) {
            e.preventDefault();
            var type = $('#orabooksFilterType').val();
            var start = $('#orabooksStartDate').val();
            var end = $('#orabooksEndDate').val();
            loadRevenueData(type, start, end);
        });
        
        // Load initial data
        setTimeout(function() {
            var type = $('#orabooksFilterType').val();
            var start = $('#orabooksStartDate').val();
            var end = $('#orabooksEndDate').val();
            loadRevenueData(type, start, end);
        }, 1000);
    }
    
    // Table row actions
    $('.orabooks-delete-level').on('click', function() {
        if (!confirm('Are you sure you want to delete this level?')) return;
        
        var levelId = $(this).data('level-id');
        $.ajax({
            url: orabooksAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'orabooks_delete_level',
                id: levelId,
                nonce: orabooksAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
    
    // Clear settings form data when leaving settings page
    $(document).on('click', '.orabooks-admin-tabs .nav-tab:not([href*="settings"])', function() {
        // Clear any form data from localStorage/sessionStorage
        localStorage.removeItem('orabooks_settings_form');
        sessionStorage.removeItem('orabooks_settings_form');
        
        // Reset any forms on the page
        $('form').each(function() {
            this.reset();
        });
    });

    // Settings tab functionality
    $('.orabooks-settings-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active classes from all tabs and panes
        $('.orabooks-settings-tabs .nav-tab').removeClass('nav-tab-active');
        $('.orabooks-settings-tabs .tab-pane').removeClass('active');
        
        // Add active classes to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding pane
        var target = $(this).attr('href').substring(1);
        $('#' + target).addClass('active');
    });

    // Test remote connection
    window.testRemoteConnection = function() {
        const result = document.getElementById('connection-result');
        result.innerHTML = 'Testing...';
        result.style.color = '#6b7280';
        
        $.ajax({
            url: orabooksAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'orabooks_test_remote_connection',
                nonce: orabooksAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    result.innerHTML = '✅ Connection successful!';
                    result.style.color = '#059669';
                } else {
                    result.innerHTML = '❌ Connection failed: ' + response.data;
                    result.style.color = '#dc2626';
                }
            },
            error: function() {
                result.innerHTML = '❌ Network error';
                result.style.color = '#dc2626';
            }
        });
    };

    // Level management functions
    window.openLevelModal = function() {
        $('#level-modal').show();
        $('#level-form')[0].reset();
        $('#level_id').val('0');
        $('#level-modal-title').text('Add New Level');
    };

    window.closeLevelModal = function() {
        $('#level-modal').hide();
    };

    window.editLevel = function(levelId) {
        $.ajax({
            url: orabooksAdmin.ajax_url,
            method: 'POST',
            data: {
                action: 'orabooks_get_level',
                level_id: levelId,
                nonce: orabooksAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const level = response.data;
                    $('#level_id').val(level.id);
                    $('#level_name').val(level.name);
                    $('#level_description').val(level.description || '');
                    $('#group_id').val(level.group_id);
                    $('#price').val(level.price);
                    $('#billing_period').val(level.billing_period);
                    // Currency is now hardcoded to BDT
                    $('#is_active').prop('checked', level.is_active == 1);
                    $('#level-modal-title').text('Edit Level');
                    $('#level-modal').show();
                }
            }
        });
    };

    window.deleteLevel = function(levelId) {
        if (confirm('Are you sure you want to delete this level? This action cannot be undone.')) {
            $.ajax({
                url: orabooksAdmin.ajax_url,
                method: 'POST',
                data: {
                    action: 'orabooks_delete_level',
                    level_id: levelId,
                    nonce: orabooksAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }
            });
        }
    };

    // Level form submission - REMOVED (handled in levels.php to prevent duplicates)
    // The form submission is now handled in templates/admin/levels.php with proper double submission prevention

    // Prevent form persistence on page refresh
    $(window).on('beforeunload', function() {
        // Clear any form data when leaving the page
        if (window.location.href.indexOf('orabooks-membership-settings') === -1) {
            localStorage.removeItem('orabooks_settings_form');
            sessionStorage.removeItem('orabooks_settings_form');
        }
    });

    // Initialize settings tabs
    if ($('.orabooks-settings-tabs').length) {
        // Show first tab by default
        $('.orabooks-settings-tabs .nav-tab:first').addClass('nav-tab-active');
        $('.orabooks-settings-tabs .tab-pane:first').addClass('active');
    }
});