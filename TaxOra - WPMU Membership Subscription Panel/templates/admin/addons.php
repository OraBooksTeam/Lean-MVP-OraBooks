<?php
if (!defined('ABSPATH')) exit;

// Build Guide Compliance: Check permissions before displaying addons
if (class_exists('OraBooks_Permission_Matrix')) {
    $user_id = get_current_user_id();
    $role = OraBooks_Permission_Matrix::get_user_role($user_id);
    $mode = OraBooks_Permission_Matrix::get_current_mode($user_id);
    
    $permission = OraBooks_Permission_Matrix::check_permission(
        $user_id,
        $role,
        $mode,
        OraBooks_Permission_Matrix::ACTION_USER_MANAGEMENT
    );
    
    if (!$permission['allowed']) {
        wp_die('You do not have permission to manage addons.');
    }
}

// Get addons from registry
$addons = class_exists('OraBooks_Addon_Registry') ? OraBooks_Addon_Registry::get_addons() : array();
?>

<div class="wrap orabooks-admin">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Orabooks Addons</h1>
                    <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Manage addon plugins that extend the membership system</p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 0.5rem;">
                    <span style="font-size: 0.875rem; font-weight: 500;"><?php echo count($addons); ?> Addons</span>
                </div>
            </div>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>

    <?php if (empty($addons)): ?>
        <div style="background: white; border-radius: 0.75rem; padding: 2rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); text-align: center;">
            <div style="margin-bottom: 2rem;">
                <div style="background: #f3f4f6; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <svg style="width: 40px; height: 40px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">No Addons Found</h2>
                <p style="color: #6b7280; font-size: 0.875rem;">No Orabooks addons are currently installed or active.</p>
            </div>
            
            <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 0.5rem; padding: 1.5rem; text-align: left; max-width: 600px; margin: 0 auto;">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #1e3a8a; margin-bottom: 1rem;">How to Install Addons</h3>
                <ol style="color: #374151; font-size: 0.875rem; line-height: 1.6;">
                    <li style="margin-bottom: 0.5rem;">Install an Orabooks addon plugin from the WordPress repository or upload a ZIP file</li>
                    <li style="margin-bottom: 0.5rem;">Activate the addon plugin</li>
                    <li style="margin-bottom: 0.5rem;">The addon will automatically appear here for management</li>
                </ol>
                
                <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 0.375rem; padding: 0.75rem; margin-top: 1rem;">
                    <p style="color: #92400e; font-size: 0.75rem; margin: 0;">
                        <strong>Note:</strong> Addons must include the header <code>Orabooks Addon: true</code> to be detected.
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem;">
            <?php foreach ($addons as $addon_id => $addon): ?>
                <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); border-left: 4px solid <?php echo $addon['enabled'] ? '#10b981' : '#6b7280'; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div>
                            <h3 style="font-size: 1.125rem; font-weight: 700; color: #1f2937; margin-bottom: 0.25rem;"><?php echo esc_html($addon['name']); ?></h3>
                            <p style="color: #6b7280; font-size: 0.875rem;">Version <?php echo esc_html($addon['version']); ?></p>
                        </div>
                        <div style="background: <?php echo $addon['enabled'] ? '#d1fae5' : '#f3f4f6'; ?> color: <?php echo $addon['enabled'] ? '#065f46' : '#6b7280'; ?> padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                            <?php echo $addon['enabled'] ? 'Active' : 'Inactive'; ?>
                        </div>
                    </div>
                    
                    <p style="color: #374151; font-size: 0.875rem; margin-bottom: 1rem; line-height: 1.5;"><?php echo esc_html($addon['description']); ?></p>
                    
                    <?php if (!empty($addon['features'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <h4 style="font-size: 0.875rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">Features:</h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($addon['features'] as $feature): ?>
                                    <li style="color: #6b7280; font-size: 0.75rem; margin-bottom: 0.25rem; display: flex; align-items: center;">
                                        <svg style="width: 16px; height: 16px; color: #10b981; margin-right: 0.5rem;" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <?php echo esc_html($feature); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid #f3f4f6;">
                        <div style="font-size: 0.75rem; color: #6b7280;">
                            By <?php echo esc_html($addon['author'] ?? 'Unknown'); ?>
                        </div>
                        <div>
                            <?php if ($addon['enabled']): ?>
                                <button type="button" disabled style="background: #e5e7eb; color: #6b7280; padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; font-size: 0.875rem; cursor: not-allowed;">
                                    Active
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="toggleAddon('<?php echo esc_js($addon_id); ?>', true)" style="background: #3b82f6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; font-size: 0.875rem; cursor: pointer;">
                                    Activate
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-top: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem;">About Orabooks Addons</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">What are Addons?</h4>
                <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.5;">Addons are WordPress plugins that extend Orabooks Membership functionality with additional features and integrations.</p>
            </div>
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">Build Guide Compliant</h4>
                <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.5;">All addons are built to comply with the OraBooks Ultimate Build Guide specifications for security and audit readiness.</p>
            </div>
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">Mode-Aware Operations</h4>
                <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.5;">Addons respect Business/Law/Faith mode boundaries and integrate seamlessly with the permission matrix.</p>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAddon(addonId, enable) {
    // This would typically make an AJAX call to enable/disable the addon
    // For now, we'll just show a message
    alert('Addon management functionality would be implemented here.');
}
</script>
