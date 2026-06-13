<?php
/**
 * Simplified Feature Access Manager Template
 * 
 * Consolidates all access management into a single, clean interface.
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
orabooks_handle_multisite_tables();

require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-tier-features.php';


$levels = $wpdb->get_results("
    SELECT l.*, g.name AS group_name,
           (SELECT COUNT(*) FROM {$wpdb->orabooks_feature_assignments} fa WHERE fa.level_id = l.id) AS assignment_count
    FROM {$wpdb->orabooks_levels} l
    LEFT JOIN {$wpdb->orabooks_groups} g ON l.group_id = g.id
    ORDER BY g.name, l.price ASC
");

$mode_labels = [
    'business' => '🏢 Business',
    'law'      => '⚖️ Law',
    'faith'    => '🙏 Faith',
    'all'      => '🌍 Universal'
];
?>

<div class="wrap orabooks-admin">
    <!-- Premium Header -->
    <div style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); border-radius: 1rem; padding: 2.5rem; margin-bottom: 2rem; color: white; box-shadow: 0 10px 15px -3px rgba(30, 64, 175, 0.2);">
        <h1 style="font-size: 2.5rem; font-weight: 800; margin: 0; color: white;">Feature Access Manager</h1>
        <p style="font-size: 1.125rem; opacity: 0.9; margin-top: 0.5rem;">Unified control for membership tiers and feature permissions.</p>
    </div>

    <!-- Main Content Grid -->
    <div style="display: grid; grid-template-columns: 1fr 300px; gap: 2rem;">
        
        <!-- Left Column: Plans & Levels -->
        <div>
            <div style="background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; color: #1f2937; border-bottom: 2px solid #f3f4f6; padding-bottom: 0.75rem;">Active Membership Levels</h2>
                
                <?php if (!empty($levels)) : ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($levels as $level) : ?>
                            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; transition: all 0.2s hover; border-left: 4px solid #3b82f6;">
                                <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0 0 0.5rem 0; color: #111827;"><?php echo esc_html($level->name); ?></h3>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                                    <span style="background: #dbeafe; color: #1e40af; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 9999px; text-transform: uppercase;">
                                        <?php echo esc_html($level->mode); ?>
                                    </span>
                                    <span style="color: #6b7280; font-size: 0.875rem;">
                                        <?php echo esc_html($level->group_name); ?>
                                    </span>
                                </div>
                                <div style="margin-bottom: 1.5rem;">
                                    <div style="font-size: 0.875rem; color: #4b5563; margin-bottom: 0.25rem;">Assigned Features:</div>
                                    <div style="font-size: 1.5rem; font-weight: 800; color: #111827;"><?php echo intval($level->assignment_count); ?></div>
                                </div>
                                <button type="button" 
                                        class="button button-primary" 
                                        style="width: 100%; height: 40px; border-radius: 0.5rem; font-weight: 600;"
                                        onclick='orabooksOpenFeatureAssignment(<?php echo (int) $level->id; ?>, <?php echo wp_json_encode($level->name); ?>)'>
                                    Manage Permissions
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div style="text-align: center; padding: 3rem; background: #f9fafb; border-radius: 0.75rem; border: 2px dashed #d1d5db;">
                        <p style="color: #6b7280; font-size: 1.125rem;">No membership levels found.</p>
                        <a href="<?php echo admin_url('admin.php?page=orabooks-membership-levels'); ?>" class="button button-primary">Create Your First Plan</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Quick Stats -->
        <div>
            <div style="background: white; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: #1f2937;">Quick Stats</h3>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #f3f4f6;">
                        <span style="color: #6b7280;">Total Plans</span>
                        <span style="font-weight: 700; color: #111827;"><?php echo count($levels); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #6b7280;">Global Rules</span>
                        <span style="font-weight: 700; color: #111827;">
                            <?php 
                            $rules_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}orabooks_limited_access_rules WHERE is_active = 1");
                            echo intval($rules_count);
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 2rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem;">
                <h4 style="font-size: 0.875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">System Info</h4>
                <p style="font-size: 0.875rem; color: #475569; margin-bottom: 0.5rem;">Access Manager: <span style="color: #059669; font-weight: 600;">Active</span></p>
                <p style="font-size: 0.875rem; color: #475569;">Unified Logic: <span style="color: #059669; font-weight: 600;">Enabled</span></p>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php
$orabooks_admin_nonce = wp_create_nonce('orabooks-admin-nonce');
$ajax_url = admin_url('admin-ajax.php');
?>

<!-- Per-level feature assignment -->
<div id="orabooks-feature-modal" class="orabooks-modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px; max-height: 85vh; overflow-y: auto;">
        <div class="modal-header">
            <h3 id="orabooks-feature-modal-title">Assign Features</h3>
            <span class="close" onclick="orabooksCloseFeatureModal()">&times;</span>
        </div>
        <div class="modal-body" id="orabooks-feature-modal-body">
            <p>Loading features...</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="button" onclick="orabooksCloseFeatureModal()">Cancel</button>
            <button type="button" class="button button-primary" id="orabooks-save-feature-assignments" onclick="orabooksSaveFeatureAssignments(event)">Save Features</button>
        </div>
    </div>
</div>

<style>
.orabooks-modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.orabooks-modal .modal-content {
    background: #fff;
    margin: 4% auto;
    padding: 0;
    border-radius: 0.75rem;
    width: 92%;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
}
.orabooks-modal .modal-header,
.orabooks-modal .modal-footer {
    padding: 1.25rem 1.5rem;
    border-color: #e5e7eb;
}
.orabooks-modal .modal-header {
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.orabooks-modal .modal-footer {
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}
.orabooks-modal .modal-body { padding: 1.5rem; }
.orabooks-modal .close { cursor: pointer; font-size: 1.5rem; color: #6b7280; }
.feature-restriction-item {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
}
.feature-restriction-item .access-control {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.5rem;
}
</style>

<script>
jQuery(document).ready(function($) {
    const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
    const nonce = <?php echo wp_json_encode($orabooks_admin_nonce); ?>;

    window.orabooksOpenFeatureAssignment = function(levelId, levelName) {
        const modal = $('#orabooks-feature-modal');
        modal.show();
        $('#orabooks-feature-modal-title').text('Assign Features — ' + levelName);
        $('#orabooks-feature-modal-body').html('<div style="padding: 2rem; text-align: center;">Loading features...</div>');
        modal.attr('data-level-id', levelId);

        $.post(ajaxUrl, {
            action: 'orabooks_load_feature_assignment_form',
            level_id: levelId,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                $('#orabooks-feature-modal-body').html(response.data.form);
            } else {
                $('#orabooks-feature-modal-body').html('<div style="color: #ef4444; padding: 1rem; background: #fee2e2; border-radius: 0.5rem;">Error: ' + (response.data || 'Unknown') + '</div>');
            }
        }).fail(function() {
            $('#orabooks-feature-modal-body').html('<div style="color: #ef4444; padding: 1rem; background: #fee2e2; border-radius: 0.5rem;">Failed to load assignment form.</div>');
        });
    };

    window.orabooksCloseFeatureModal = function() {
        $('#orabooks-feature-modal').hide();
    };

    window.orabooksSaveFeatureAssignments = function(event) {
        if (event) { event.preventDefault(); event.stopPropagation(); }
        const modal = $('#orabooks-feature-modal');
        const levelId = modal.attr('data-level-id');
        if (!levelId) { alert('Level ID missing'); return false; }

        const features = {};
        $('#orabooks-feature-modal-body .feature-checkbox').each(function() {
            const checkbox = $(this);
            const featureKey = checkbox.val();
            const accessSelect = $('#access_type_' + featureKey);
            features[featureKey] = {
                enabled: checkbox.is(':checked') ? 'yes' : 'no',
                name: checkbox.attr('data-feature-name') || '',
                access_type: accessSelect.length ? accessSelect.val() : 'full'
            };
        });

        const btn = $('#orabooks-save-feature-assignments');
        const orig = btn.text();
        btn.prop('disabled', true).text('Saving...');

        $.post(ajaxUrl, {
            action: 'orabooks_save_feature_assignments',
            level_id: levelId,
            features: JSON.stringify(features),
            nonce: nonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message || 'Feature assignments saved.');
                orabooksCloseFeatureModal();
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Save failed'));
            }
        }).fail(function() {
            alert('Save failed.');
        }).always(function() {
            btn.prop('disabled', false).text(orig);
        });
        return false;
    };

    $(window).on('click', function(event) {
        const modal = $('#orabooks-feature-modal');
        if ($(event.target).is(modal)) {
            modal.hide();
        }
    });
});
</script>
