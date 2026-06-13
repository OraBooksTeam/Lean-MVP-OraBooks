<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
?>

<div class="wrap orabooks-admin">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Membership Levels</h1>
            <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Manage subscription tiers and pricing plans</p>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>

    <!-- Modern Action Buttons -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
        <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between;">
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                <button type="button" onclick="openLevelModal()" style="background: linear-gradient(135deg, #3b82f6 0%, #4f46e5 100%); color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; border: none; cursor: pointer;">Add New Level</button>
                <a href="?page=orabooks-membership-groups" style="background: white; border: 1px solid #d1d5db; color: #374151; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none;">Manage Groups</a>
            </div>
        </div>
    </div>

    <div class="orabooks-admin-content">
        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">All Membership Levels</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Manage and organize your subscription tiers</p>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; background: #f9fafb; border-radius: 0.5rem; padding: 0.5rem 1rem;">
                        <select id="group-filter" style="background: transparent; border: none; font-size: 0.875rem;">
                            <option value="">All Groups</option>
                            <?php
                            $groups = $wpdb->get_results("SELECT * FROM {$wpdb->orabooks_groups} ORDER BY name");
                            foreach ($groups as $group) {
                                echo '<option value="' . esc_attr($group->id) . '">' . esc_html($group->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem; background: #f9fafb; border-radius: 0.5rem; padding: 0.5rem 1rem;">
                        <input type="text" id="level-search" placeholder="Search levels..." style="background: transparent; border: none; font-size: 0.875rem; width: 200px;">
                    </div>
                </div>
            </div>

            <div id="levels-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
                <?php
                $levels = $wpdb->get_results("
                    SELECT l.*, g.name as group_name,
                           (SELECT COUNT(*) FROM {$wpdb->orabooks_orders} o WHERE o.level_id = l.id AND o.status = 'completed') as subscriber_count
                    FROM {$wpdb->orabooks_levels} l
                    LEFT JOIN {$wpdb->orabooks_groups} g ON l.group_id = g.id
                    ORDER BY g.name, l.price ASC
                ");
                
                if ($levels) {
                    foreach ($levels as $level) {
                        // Use hardcoded BDT symbol and position
                        $symbol = '৳';
                        $price_display = number_format($level->price, 2) . $symbol;
                        
                        echo '<div class="level-card" data-level-id="' . esc_attr($level->id) . '" data-group-id="' . esc_attr($level->group_id) . '" style="background: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); transition: all 0.2s ease; position: relative; overflow: hidden;">';
                        
                        // Header with name and label
                        echo '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">';
                        echo '<div style="flex: 1;">';
                        echo '<h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin: 0 0 0.5rem 0;">' . esc_html($level->name) . '</h3>';
                        if (!empty($level->label)) {
                            echo '<span class="level-label-badge" style="background:#e5f6ea;color:#065f46;padding:4px 8px;border-radius:12px;font-size:12px;font-weight:500;display:inline-block;margin-bottom:0.5rem;">' . esc_html($level->label) . '</span>';
                        }
                        echo '</div>';
                        echo '<span class="status-badge ' . ($level->is_active ? 'active' : 'inactive') . '" style="position: absolute; top: 1rem; right: 1rem;">' . ($level->is_active ? 'Active' : 'Inactive') . '</span>';
                        echo '</div>';
                        
                        // Description
                        if (!empty($level->description)) {
                            echo '<p style="color: #6b7280; font-size: 0.875rem; margin: 0 0 1rem 0; line-height: 1.5;">' . esc_html($level->description) . '</p>';
                        }
                        
                        // Plan details grid
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">';
                        echo '<div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem;">';
                        echo '<div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Price</div>';
                        echo '<div style="font-size: 1.125rem; font-weight: 700; color: #1f2937;">' . $price_display . '</div>';
                        echo '</div>';
                        echo '<div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem;">';
                        echo '<div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Billing</div>';
                        echo '<div style="font-size: 0.875rem; font-weight: 600; color: #1f2937;">' . esc_html(ucfirst(str_replace('-', ' ', (string)$level->billing_period))) . '</div>';
                        echo '</div>';
                        echo '</div>';
                        
                        // Additional info
                        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0.75rem; background: #f3f4f6; border-radius: 0.5rem;">';
                        echo '<div>';
                        echo '<div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Group</div>';
                        echo '<div style="font-size: 0.875rem; font-weight: 600; color: #1f2937;">' . esc_html($level->group_name ?: 'No Group') . '</div>';
                        echo '</div>';
                        echo '<div style="text-align: right;">';
                        echo '<div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Subscribers</div>';
                        echo '<div style="font-size: 1.125rem; font-weight: 700; color: #1f2937;">' . intval($level->subscriber_count) . '</div>';
                        echo '</div>';
                        echo '</div>';
                        
                        // Action buttons
                        echo '<div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">';
                        echo '<button type="button" class="button button-small" onclick="editLevel(' . esc_attr($level->id) . ')" style="flex: 1; min-width: 80px;">Edit</button>';
                        echo '<button type="button" class="button button-small" onclick="openFeatureAssignment(' . esc_attr($level->id) . ', \'' . esc_js($level->name) . '\')" style="flex: 1; min-width: 80px;">Features</button>';
                        echo '<button type="button" class="button button-small button-link-delete" onclick="deleteLevel(' . esc_attr($level->id) . ')" style="flex: 1; min-width: 80px;">Delete</button>';
                        echo '</div>';
                        
                        echo '</div>';
                    }
                } else {
                    echo '<div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: #f9fafb; border-radius: 0.75rem; border: 2px dashed #d1d5db;">';
                    echo '<div style="font-size: 1.125rem; color: #6b7280; margin-bottom: 1rem;">No levels found</div>';
                    echo '<button type="button" onclick="openLevelModal()" class="button button-primary">Create your first level</button>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Level Modal -->
<div id="level-modal" class="orabooks-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="level-modal-title">Add New Level</h3>
            <span class="close" onclick="closeLevelModal()">&times;</span>
        </div>
        <form id="level-form" method="post">
            <input type="hidden" name="action" value="orabooks_save_level">
            <input type="hidden" name="level_id" id="level_id" value="0">
            <?php wp_nonce_field('orabooks_save_level', 'orabooks_nonce'); ?>
            
            <div class="modal-body">
                <table class="form-table">
                    <tr>
                        <th><label for="level_name">Level Name *</label></th>
                        <td>
                            <input type="text" id="level_name" name="level_name" class="regular-text" required>
                            <div id="level-name-error" class="field-error" style="display: none;"></div>
                            <p class="description">The name of this membership level (e.g., "Basic", "Pro", "Enterprise")</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="level_label">Plan Label</label></th>
                        <td>
                            <input type="text" id="level_label" name="level_label" class="regular-text" maxlength="50" placeholder="Free, Popular, Recommended">
                            <p class="description">Optional short label shown on plan cards (e.g., "Free", "Popular")</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="level_description">Description</label></th>
                        <td>
                            <textarea id="level_description" name="level_description" class="regular-text" rows="3" placeholder="Describe what this level includes"></textarea>
                            <p class="description">Optional description shown to users</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="group_id">Group *</label></th>
                        <td>
                            <select id="group_id" name="group_id" required>
                                <option value="">Select Group</option>
                                <?php
                                foreach ($groups as $group) {
                                    echo '<option value="' . esc_attr($group->id) . '">' . esc_html($group->name) . '</option>';
                                }
                                ?>
                            </select>
                            <div id="group-id-error" class="field-error" style="display: none;"></div>
                            <p class="description">Which group this level belongs to</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="price">Price</label></th>
                        <td>
                            <input type="number" id="price" name="price" step="0.01" min="0" value="0" class="small-text">
                            <p class="description">Set to 0 for free plans. Use decimal values (e.g., 29.99)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="billing_period">Billing Period *</label></th>
                        <td>
                            <select id="billing_period" name="billing_period" required>
                                <option value="free">Free</option>
                                <option value="one-time">One Time</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                                <option value="lifetime">Lifetime</option>
                            </select>
                            <p class="description">How often users are billed for this level</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="trial_days">Trial Days</label></th>
                        <td>
                            <input type="number" id="trial_days" name="trial_days" min="0" value="0" class="small-text">
                            <p class="description">Number of free trial days (0 for no trial)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Currency</label></th>
                        <td>
                            <p><strong>Bangladeshi Taka (BDT)</strong></p>
                            <p class="description">All levels use Bangladeshi Taka</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="is_active">Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                Active (visible to users)
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="button" onclick="closeLevelModal()">Cancel</button>
                <button type="submit" class="button button-primary">Save Level</button>
            </div>
        </form>
    </div>
</div>

<!-- Feature Assignment Modal -->
<div id="feature-modal" class="orabooks-modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 id="feature-modal-title">Assign Features</h3>
            <span class="close" onclick="closeFeatureModal()">&times;</span>
        </div>
        <div class="modal-body" id="feature-modal-body">
            <!-- Feature form will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="button" onclick="closeFeatureModal()">Cancel</button>
            <button type="button" class="button button-primary" id="save-feature-assignments" onclick="saveFeatureAssignments()">Save Features</button>
        </div>
    </div>
</div>

<script type="text/javascript">
// Level Modal Functions
function openLevelModal() {
    console.log('Opening level modal...');
    document.getElementById('level-modal').style.display = 'block';
    document.getElementById('level-form').reset();
    document.getElementById('level_id').value = '0';
    document.getElementById('level-modal-title').textContent = 'Add New Level';
    document.getElementById('billing_period').value = 'monthly';
    document.getElementById('is_active').checked = true;
    // Currency is now hardcoded to BDT and doesn't need to be set
    
    // Clear any previous errors
    hideError(document.getElementById('level-name-error'));
    hideError(document.getElementById('group-id-error'));
    
    // Reset submission flag when opening modal
    isSubmitting = false;
    
    // Re-enable submit button
    const submitBtn = document.querySelector('#level-form button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Level';
    }
}

function closeLevelModal() {
    document.getElementById('level-modal').style.display = 'none';
}

function editLevel(levelId) {
    if (!levelId || levelId <= 0) {
        alert('Invalid level ID');
        return;
    }
    
    // Show loading state
    document.getElementById('level-modal-title').textContent = 'Loading...';
    document.getElementById('level-modal').style.display = 'block';
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'orabooks_get_level',
            'level_id': levelId,
            'nonce': '<?php echo wp_create_nonce('orabooks-admin-nonce'); ?>'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const level = data.data;
            document.getElementById('level_id').value = level.id;
            document.getElementById('level_name').value = level.name;
                    document.getElementById('level_label').value = level.label || '';
            document.getElementById('level_description').value = level.description || '';
            document.getElementById('group_id').value = level.group_id;
            document.getElementById('price').value = level.price;
            document.getElementById('billing_period').value = level.billing_period;
            document.getElementById('trial_days').value = level.trial_days || 0;
            // Currency is now hardcoded to BDT
            document.getElementById('is_active').checked = level.is_active == 1;
            document.getElementById('level-modal-title').textContent = 'Edit Level: ' + level.name;
            
            // Clear any errors
            hideError(document.getElementById('level-name-error'));
            hideError(document.getElementById('group-id-error'));
        } else {
            alert('Error loading level: ' + (data.data || 'Unknown error'));
            closeLevelModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading level data. Please check console for details.');
        closeLevelModal();
    });
}

function deleteLevel(levelId) {
    if (!levelId || levelId <= 0) {
        alert('Invalid level ID');
        return;
    }
    
    if (confirm('Are you sure you want to delete this level? This action cannot be undone.')) {
        console.log('Deleting level ID:', levelId);
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'action': 'orabooks_delete_level',
                'id': levelId,
                'nonce': '<?php echo wp_create_nonce('orabooks-admin-nonce'); ?>'
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Delete response:', data);
            if (data.success) {
                alert('Level deleted successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting level. Please check console for details.');
        });
    }
}

// Feature Assignment Functions
function openFeatureAssignment(levelId, levelName) {
    console.log('Opening feature assignment for level:', levelId, levelName);
    
    document.getElementById('feature-modal').style.display = 'block';
    document.getElementById('feature-modal-title').textContent = 'Assign Features - ' + levelName;
    document.getElementById('feature-modal-body').innerHTML = '<p>Loading features...</p>';
    
    // Load features via AJAX
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'orabooks_load_feature_assignment_form',
            'level_id': levelId,
            'nonce': '<?php echo wp_create_nonce('orabooks-admin-nonce'); ?>'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Feature assignment response:', data);
        if (data.success) {
            document.getElementById('feature-modal-body').innerHTML = data.data.form;
            document.getElementById('feature-modal').setAttribute('data-level-id', levelId);
        } else {
            document.getElementById('feature-modal-body').innerHTML = '<p>Error loading features: ' + (data.data || 'Unknown error') + '</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('feature-modal-body').innerHTML = '<p>Error loading features. Please check console.</p>';
    });
}

function closeFeatureModal() {
    document.getElementById('feature-modal').style.display = 'none';
}

function saveFeatureAssignments(event) {
    // Prevent default and stop propagation to avoid double submission
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const modal = document.getElementById('feature-modal');
    if (!modal) {
        console.error('Feature modal not found');
        return false;
    }
    
    const levelId = modal.getAttribute('data-level-id');
    if (!levelId) {
        alert('Error: Level ID not found');
        return false;
    }
    
    const features = {};
    
    // Collect all features (both checked and unchecked)
    document.querySelectorAll('.feature-checkbox').forEach(checkbox => {
        const featureKey = checkbox.value;
        const featureName = checkbox.getAttribute('data-feature-name') || '';
        const accessTypeSelect = document.getElementById('access_type_' + featureKey);
        const accessType = accessTypeSelect ? accessTypeSelect.value : 'full';
        
        // Collect granular limits if they exist
        const settings = {};
        const manager = document.getElementById('limitation_manager_' + featureKey);
        if (manager) {
            // Check for granular limits
            const granularLimits = manager.querySelectorAll('.granular-limit');
            if (granularLimits.length > 0) {
                granularLimits.forEach(input => {
                    const limitId = input.getAttribute('data-limit-id');
                    settings[limitId] = input.value;
                });
            } else {
                // Fallback for general limit
                const generalLimit = manager.querySelector('.general-limit') || document.getElementById('limit_' + featureKey);
                if (generalLimit) {
                    settings.limit = generalLimit.value;
                }
            }
        }
        
        features[featureKey] = {
            enabled: checkbox.checked ? 'yes' : 'no',
            name: featureName,
            access_type: accessType,
            settings: settings
        };
    });
    
    // Show saving state
    const saveBtn = document.querySelector('#feature-modal #save-feature-assignments');
    if (!saveBtn) {
        console.error('Save button not found');
        return false;
    }
    
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;
    
    console.log('Saving features:', features);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'orabooks_save_feature_assignments',
            'level_id': levelId,
            'features': JSON.stringify(features),
            'nonce': '<?php echo wp_create_nonce('orabooks-admin-nonce'); ?>'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Save feature assignments response:', data);
        if (data.success) {
            alert('Feature assignments saved successfully!');
            closeFeatureModal();
            // Optionally reload to show updated data
            // location.reload();
        } else {
            alert('Error: ' + (data.data || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving features. Please check console for details.');
    })
    .finally(() => {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
    
    return false; // Prevent any default behavior
}

// Form Validation Functions
function validateLevelName() {
    const levelName = document.getElementById('level_name').value.trim();
    const errorElement = document.getElementById('level-name-error');
    
    if (!levelName) {
        showError(errorElement, 'Level name is required');
        return false;
    } else {
        hideError(errorElement);
        return true;
    }
}

function validateGroup() {
    const groupId = document.getElementById('group_id').value;
    const errorElement = document.getElementById('group-id-error');
    
    if (!groupId) {
        showError(errorElement, 'Please select a group');
        return false;
    } else {
        hideError(errorElement);
        return true;
    }
}

function showError(errorElement, message) {
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

function hideError(errorElement) {
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

// Validate entire form before submission
function validateForm() {
    const isNameValid = validateLevelName();
    const isGroupValid = validateGroup();
    
    return isNameValid && isGroupValid;
}

// Level Form Submission - FIXED (No Double Submission)
let isSubmitting = false; // Global flag to prevent double submission

document.getElementById('level-form').addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation(); // Prevent event bubbling
    
    // Prevent double submission
    if (isSubmitting) {
        console.log('Already submitting, please wait...');
        return false;
    }
    
    // Validate form first
    if (!validateForm()) {
        alert('Please fix the errors in the form before saving.');
        return false;
    }
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Set submitting flag and show loading state
    isSubmitting = true;
    submitBtn.textContent = 'Saving...';
    submitBtn.disabled = true;
    
    // Prepare data for AJAX - convert FormData to object
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Add nonce and ensure action is correct
    data.nonce = '<?php echo wp_create_nonce('orabooks-admin-nonce'); ?>';
    data.action = 'orabooks_save_level';
    
    console.log('Sending level data:', data);
    
    // Send AJAX request
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(response => {
        console.log('Level save response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(responseData => {
        console.log('Level save server response:', responseData);
        
        if (responseData.success) {
            alert('Level saved successfully!');
            // Close modal and reload after a short delay
            setTimeout(() => {
                closeLevelModal();
                location.reload();
            }, 1500);
        } else {
            const errorMessage = responseData.data || 'Unknown server error';
            throw new Error(errorMessage);
        }
    })
    .catch(error => {
        console.error('Save level error:', error);
        alert('Failed to save level: ' + error.message);
    })
    .finally(() => {
        // Reset submitting flag and button state
        isSubmitting = false;
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
    
    return false; // Ensure no default submission
});

// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
    const levelNameInput = document.getElementById('level_name');
    const groupSelect = document.getElementById('group_id');
    
    if (levelNameInput) {
        levelNameInput.addEventListener('blur', function() {
            validateLevelName();
        });
        
        levelNameInput.addEventListener('input', function() {
            // Clear error when user starts typing
            if (this.value.trim()) {
                hideError(document.getElementById('level-name-error'));
            }
        });
    }
    
    if (groupSelect) {
        groupSelect.addEventListener('change', function() {
            validateGroup();
        });
    }
});

// Filter levels
document.addEventListener('DOMContentLoaded', function() {
    const groupFilter = document.getElementById('group-filter');
    const levelSearch = document.getElementById('level-search');
    
    if (groupFilter) {
        groupFilter.addEventListener('change', filterLevels);
    }
    if (levelSearch) {
        levelSearch.addEventListener('input', filterLevels);
    }
});

function filterLevels() {
    const groupFilter = document.getElementById('group-filter')?.value || '';
    const searchFilter = document.getElementById('level-search')?.value.toLowerCase() || '';
    const cards = document.querySelectorAll('.level-card');
    
    cards.forEach(card => {
        const groupId = card.getAttribute('data-group-id');
        const levelName = card.querySelector('h3')?.textContent.toLowerCase() || '';
        const levelDesc = card.querySelector('p')?.textContent.toLowerCase() || '';
        
        const groupMatch = !groupFilter || groupId === groupFilter;
        const searchMatch = !searchFilter || 
                           levelName.includes(searchFilter) || 
                           levelDesc.includes(searchFilter);
        
        card.style.display = (groupMatch && searchMatch) ? '' : 'none';
    });
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('orabooks-modal')) {
        e.target.style.display = 'none';
    }
});
</script>

<style>
.orabooks-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 90%;
    max-width: 600px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    flex: 1;
}

.close {
    color: #6b7280;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    color: #374151;
}

.modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    text-align: right;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.active {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.inactive {
    background: #fef3c7;
    color: #92400e;
}

.button-link-delete {
    background: #dc2626 !important;
    border-color: #dc2626 !important;
    color: white !important;
}

.button-link-delete:hover {
    background: #b91c1c !important;
    border-color: #b91c1c !important;
}

.feature-item {
    display: flex;
    align-items: center;
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 10px;
    background: #f9fafb;
}

.feature-toggle {
    display: flex;
    align-items: center;
    flex: 1;
}

/* Card-based layout styles */
.level-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.level-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border-color: #d1d5db;
}

.level-card:hover .button {
    transform: translateY(0);
}

.level-card .button {
    transition: all 0.2s ease;
}

.level-card .button:hover {
    transform: translateY(-1px);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    #levels-container {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .level-card {
        padding: 1rem;
    }
    
    .level-card h3 {
        font-size: 1.125rem;
    }
}

@media (max-width: 480px) {
    .level-card {
        padding: 0.75rem;
    }
    
    .level-card .button {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
}

.feature-icon {
    font-size: 24px;
    margin-right: 12px;
}

.feature-info {
    flex: 1;
}

.feature-info strong {
    display: block;
    margin-bottom: 4px;
}

.feature-desc {
    font-size: 12px;
    color: #6b7280;
}

.access-type-select {
    margin-left: 10px;
    min-width: 120px;
}

.field-error {
    color: #dc2626;
    font-size: 12px;
    margin-top: 4px;
    display: none;
}

.form-table input.error,
.form-table select.error {
    border-color: #dc2626;
    box-shadow: 0 0 0 1px #dc2626;
}
</style>