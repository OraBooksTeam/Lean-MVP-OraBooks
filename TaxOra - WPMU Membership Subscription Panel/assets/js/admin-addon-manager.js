/**
 * Professional Addon Manager JavaScript for TaxOra Membership Plugin
 * Modern UX patterns, proper error handling, and logical workflows
 */

// ===== GLOBAL VARIABLES =====
const orabooksAddonManager = {
    init() {
        this.bindEvents();
        this.setupTooltips();
        this.setupSearch();
    },
    
    bindEvents() {
        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
        
        // Close modals on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeAllModals();
            }
        });
    },
    
    closeAllModals() {
        document.querySelectorAll('.plan-features-modal').forEach(modal => modal.remove());
    },
    
    setupTooltips() {
        // Add tooltips to addon items
        const addonItems = document.querySelectorAll('.addon-item');
        addonItems.forEach(item => {
            const description = item.querySelector('.addon-description p');
            if (description) {
                item.setAttribute('title', description.textContent.trim());
                item.classList.add('has-tooltip');
            }
        });
    },
    
    setupSearch() {
        const searchInput = document.getElementById('addon-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterAddons(e.target.value);
            });
        }
    },
    
    filterAddons(searchTerm) {
        const addonItems = document.querySelectorAll('.addon-item');
        const term = searchTerm.toLowerCase();
        
        addonItems.forEach(item => {
            const addonName = item.querySelector('.addon-header h4')?.textContent.toLowerCase() || '';
            const addonDesc = item.querySelector('.addon-description p')?.textContent.toLowerCase() || '';
            
            if (term === '' || addonName.includes(term) || addonDesc.includes(term)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    },
    
    showLoading(element) {
        element.classList.add('loading');
        element.disabled = true;
    },
    
    hideLoading(element) {
        element.classList.remove('loading');
        element.disabled = false;
    },
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    },
    
    showError(message) {
        this.showNotification(message, 'error');
    },
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `orabooks-notification orabooks-notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.remove()">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
};

// ===== MAIN FUNCTIONS =====

// Toggle Addon (Enable/Disable)
function toggleAddon(addonKey, action) {
    const button = event.target;
    orabooksAddonManager.showLoading(button);
    
    const nonce = document.querySelector('#orabooks-settings-form input[name="_wpnonce"]').value;
    
    if (!orabooksAddonManager.validateAction(action, addonKey)) {
        orabooksAddonManager.hideLoading(button);
        return;
    }
    
    fetch(orabooks_addon_manager.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'orabooks_toggle_addon',
            'addon_key': addonKey,
            'toggle_action': action,
            '_wpnonce': nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        orabooksAddonManager.hideLoading(button);
        
        if (data.success) {
            orabooksAddonManager.showSuccess(data.message || `Addon ${action}d successfully`);
            // Update UI state without reload
            setTimeout(() => location.reload(), 1500);
        } else {
            orabooksAddonManager.showError(data.data || 'Operation failed');
        }
    })
    .catch(error => {
        orabooksAddonManager.hideLoading(button);
        console.error('Toggle Addon Error:', error);
        orabooksAddonManager.showError('Network error. Please try again.');
    });
}

// Configure Addon
function configureAddon(addonKey) {
    // Validate addon key
    if (!addonKey || !addonKey.trim()) {
        orabooksAddonManager.showError('Invalid addon identifier');
        return;
    }
    
    // Open addon configuration in modal
    window.open('?page=orabooks-addons&configure=' + encodeURIComponent(addonKey), '_blank');
}

// Save Plan Features
function savePlanFeatures(levelId) {
    const button = event.target;
    orabooksAddonManager.showLoading(button);
    
    const form = document.getElementById('orabooks-settings-form');
    const formData = new FormData(form);
    
    // Get all checked features for this level
    const checkboxes = document.querySelectorAll(`input[name^="plan_features[${levelId}]["]:checked`);
    const features = {};
    let hasChanges = false;
    
    checkboxes.forEach(checkbox => {
        const name = checkbox.name;
        const match = name.match(/plan_features\[(\d+)\]\[(.+)\]/);
        if (match) {
            const featureKey = match[2];
            features[featureKey] = '1';
            hasChanges = true;
        }
    });
    
    // Validate at least one feature is selected
    if (!hasChanges) {
        orabooksAddonManager.hideLoading(button);
        orabooksAddonManager.showError('No changes detected. Please select features to save.');
        return;
    }
    
    // Add to form data
    formData.set('action', 'orabooks_save_plan_features');
    formData.set('level_id', levelId);
    formData.set('features', JSON.stringify(features));
    
    fetch(orabooks_addon_manager.ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        orabooksAddonManager.hideLoading(button);
        
        if (data.success) {
            orabooksAddonManager.showSuccess(data.message || 'Plan features saved successfully');
            // Highlight saved row
            const planItem = document.querySelector(`.plan-feature-item[data-level="${levelId}"]`);
            if (planItem) {
                planItem.classList.add('just-saved');
                setTimeout(() => planItem.classList.remove('just-saved'), 2000);
            }
        } else {
            orabooksAddonManager.showError(data.data || 'Failed to save plan features');
        }
    })
    .catch(error => {
        orabooksAddonManager.hideLoading(button);
        console.error('Save Plan Features Error:', error);
        orabooksAddonManager.showError('Network error. Please try again.');
    });
}

// View Plan Features Details
function viewPlanFeatures(levelId) {
    // Validate level ID
    if (!levelId || levelId <= 0) {
        orabooksAddonManager.showError('Invalid plan identifier');
        return;
    }
    
    // Create modal with loading state
    const modal = document.createElement('div');
    modal.className = 'plan-features-modal';
    modal.innerHTML = `
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Plan Features Details</h3>
                    <button class="modal-close" onclick="this.closest('.plan-features-modal').remove()">×</button>
                </div>
                <div class="modal-body">
                    <div class="loading-spinner"></div>
                    <p>Loading features...</p>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load features data
    fetch(orabooks_addon_manager.ajaxurl + '?action=orabooks_get_plan_features&level_id=' + levelId, {
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        const modalBody = modal.querySelector('.modal-body');
        
        if (data.success) {
            let html = '<div class="features-details">';
            
            if (data.features && data.features.length > 0) {
                data.features.forEach(feature => {
                    html += `
                        <div class="feature-detail">
                            <h4><span class="feature-key">${feature.feature_key}</span></h4>
                            <p><strong>Feature:</strong> ${feature.name || 'Unknown'}</p>
                            <p><strong>Description:</strong> ${feature.description || 'No description'}</p>
                            <p><strong>Access Type:</strong> ${feature.access_type || 'full'}</p>
                            <p><strong>Created:</strong> ${new Date(feature.created_at).toLocaleDateString()}</p>
                            <p><strong>Status:</strong> <span class="status-active">Active</span></p>
                        </div>
                    `;
                });
            } else {
                html = '<div class="no-features"><p>No features assigned to this plan.</p></div>';
            }
            
            html += '</div>';
            modalBody.innerHTML = html;
        } else {
            modalBody.innerHTML = '<div class="error-message"><p>Error loading features: ' + (data.data || 'Unknown error') + '</p></div>';
        }
    })
    .catch(error => {
        console.error('View Plan Features Error:', error);
        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = '<div class="error-message"><p>Network error loading features. Please try again.</p></div>';
    });
}

// ===== VALIDATION =====

function validateAction(action, addonKey) {
    // Validate action parameter
    if (!action || !['enable', 'disable'].includes(action)) {
        console.error('Invalid action:', action);
        return false;
    }
    
    // Validate addon key
    if (!addonKey || typeof addonKey !== 'string' || addonKey.trim().length === 0) {
        console.error('Invalid addon key:', addonKey);
        return false;
    }
    
    return true;
}

// ===== INITIALIZATION =====

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    orabooksAddonManager.init();
});
