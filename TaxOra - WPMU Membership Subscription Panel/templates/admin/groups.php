<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table = $wpdb->orabooks_groups;
$groups = $wpdb->get_results( "SELECT * FROM $table ORDER BY name" );
?>

<div class="wrap orabooks-admin">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Membership Groups</h1>
            <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Organize your membership levels into categories</p>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>
    
    <div id="orabooks-groups-app">
        <input type="hidden" id="orabooks-admin-nonce" value="<?php echo wp_create_nonce('orabooks-admin-nonce'); ?>">
        
        <!-- Groups Table -->
        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
            <div style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">All Groups</h2>
                <p style="color: #6b7280; font-size: 0.875rem;">Manage your membership group categories</p>
            </div>
            
            <div style="overflow-x-auto;">
                <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                    <tr>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">ID</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Name</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Description</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Created</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $groups ) ) : ?>
                        <?php foreach ( $groups as $g ) : ?>
                            <tr data-id="<?php echo intval( $g->id ); ?>" style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.2s;">
                                <td style="padding: 1rem; font-weight: 600; color: #374151;"><?php echo intval( $g->id ); ?></td>
                                <td class="g-name" style="padding: 1rem; font-weight: 500; color: #1f2937;"><?php echo esc_html( $g->name ); ?></td>
                                <td class="g-desc" style="padding: 1rem; color: #6b7280;"><?php echo esc_html( $g->description ); ?></td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo date( 'M j, Y', strtotime( $g->created_at ) ); ?></td>
                                <td style="padding: 1rem;">
                                    <button class="orabooks-edit-group" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; margin-right: 0.5rem; cursor: pointer; transition: all 0.2s;">Edit</button> 
                                    <button class="orabooks-delete-group" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s;">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center; color: #6b7280;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                                    <svg style="width: 48px; height: 48px; color: #9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    <div>
                                        <p style="font-weight: 600; margin-bottom: 0.25rem;">No groups found</p>
                                        <p style="font-size: 0.875rem;">Create your first group below.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                </div>
        </div>
        
        <!-- Create New Group -->
        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 0.5rem; padding: 0.75rem;">
                    <svg style="width: 24px; height: 24px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.25rem;">Create New Group</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">Add a new membership group to organize your levels</p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;" for="orabooks-new-group-name">Group Name</label>
                    <input id="orabooks-new-group-name" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: all 0.2s;" type="text" placeholder="Enter group name">
                    <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">A descriptive name for this membership group (e.g., "Business Plans", "Personal Plans")</p>
                </div>
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;" for="orabooks-new-group-desc">Description</label>
                    <textarea id="orabooks-new-group-desc" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: all 0.2s; resize: vertical;" rows="3" placeholder="Enter group description"></textarea>
                    <p style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">Optional description for this group</p>
                </div>
            </div>
            
            <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                <button id="orabooks-create-group" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 0.75rem 2rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">Create Group</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = $('#orabooks-admin-nonce').val();

    $('#orabooks-create-group').on('click', function(){
        var btn = $(this);
        var name = $('#orabooks-new-group-name').val();
        var desc = $('#orabooks-new-group-desc').val();
        
        if (!name) {
            alert('Group name is required');
            return;
        }
        
        btn.prop('disabled', true).text('Creating...');
        
        $.post(ajaxurl, { 
            action: 'orabooks_save_group', 
            nonce: nonce, 
            name: name, 
            description: desc 
        }, function(response) {
            if ( response.success ) {
                location.reload();
            } else {
                alert(response.data || 'Error creating group');
                btn.prop('disabled', false).text('Create Group');
            }
        }).fail(function(xhr, status, error) {
            alert('AJAX error: ' + error);
            btn.prop('disabled', false).text('Create Group');
        });
    });
    
    $('.orabooks-delete-group').on('click', function(){
        var btn = $(this);
        var tr = btn.closest('tr');
        var id = tr.data('id');
        
        if (!id) {
            alert('Could not find group ID');
            return;
        }

        if ( ! confirm('Are you sure you want to delete this group? This action cannot be undone.') ) return;
        
        btn.prop('disabled', true).text('Deleting...');
        
        $.post(ajaxurl, { 
            action: 'orabooks_delete_group', 
            nonce: nonce, 
            id: id 
        }, function(response) {
            if ( response.success ) {
                location.reload();
            } else {
                alert(response.data || 'Error deleting group');
                btn.prop('disabled', false).text('Delete');
            }
        }).fail(function(xhr, status, error) {
            alert('AJAX error: ' + error);
            btn.prop('disabled', false).text('Delete');
        });
    });
    
    $('.orabooks-edit-group').on('click', function(){
        var btn = $(this);
        var tr = btn.closest('tr');
        var id = tr.data('id');
        var name = tr.find('.g-name').text();
        var desc = tr.find('.g-desc').text();
        
        if (!id) {
            alert('Could not find group ID');
            return;
        }

        var newName = prompt('Group Name', name);
        if ( newName === null ) return;
        if ( newName.trim() === '' ) {
            alert('Group name cannot be empty');
            return;
        }
        
        var newDesc = prompt('Group Description', desc);
        if ( newDesc === null ) newDesc = desc;
        
        btn.prop('disabled', true).text('Saving...');
        
        $.post(ajaxurl, { 
            action: 'orabooks_save_group', 
            nonce: nonce, 
            id: id, 
            name: newName, 
            description: newDesc 
        }, function(response) {
            if ( response.success ) {
                location.reload();
            } else {
                alert(response.data || 'Error updating group');
                btn.prop('disabled', false).text('Edit');
            }
        }).fail(function(xhr, status, error) {
            alert('AJAX error: ' + error);
            btn.prop('disabled', false).text('Edit');
        });
    });
});
</script>