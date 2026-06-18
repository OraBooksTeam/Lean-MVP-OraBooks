<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_sidebar';

// Handle Form Submission
if (isset($_POST['save_feature'])) {
    check_admin_referer('save_feature_nonce');
    
    $data = array(
        'module'     => sanitize_text_field($_POST['module']),
        'parent'     => intval($_POST['parent']),
        'menu_title' => sanitize_text_field($_POST['menu_title']),
        'menu_slug'  => sanitize_text_field($_POST['menu_slug']),
        'icon'       => sanitize_text_field($_POST['icon']),
        'sort_order' => intval($_POST['sort_order']),
        'status'     => intval($_POST['status']),
        'created_by' => get_current_user_id()
    );
    
    if (!empty($_POST['feature_id'])) {
        $wpdb->update($table_name, $data, array('id' => intval($_POST['feature_id'])));
        echo "<script>Swal.fire('Success', 'Feature updated successfully', 'success');</script>";
    } else {
        $wpdb->insert($table_name, $data);
        echo "<script>Swal.fire('Success', 'Feature added successfully', 'success');</script>";
    }
}

// Handle Delete
if (isset($_GET['delete_feature'])) {
    $wpdb->delete($table_name, array('id' => intval($_GET['delete_feature'])));
    echo "<script>Swal.fire('Deleted', 'Feature removed successfully', 'success');</script>";
}

// Fetch Features for Accounting Module
$features = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE module = %s ORDER BY sort_order ASC", 'accounting'));
$parent_menus = $wpdb->get_results($wpdb->prepare("SELECT id, menu_title FROM $table_name WHERE parent = 0 AND status = 1 AND module = %s", 'accounting'));

?>

<div class="p-6 bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Manage Sidebar Features (Accounting Module)</h2>
        <button onclick="toggleFeatureForm()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Add New Feature
        </button>
    </div>

    <!-- Feature Form (Hidden by default) -->
    <div id="feature-form-container" class="mb-8 p-6 bg-slate-50 rounded-xl border border-slate-200 hidden">
        <h3 class="text-lg font-semibold mb-4 text-slate-700" id="form-title">Add New Feature</h3>
        <form method="post" id="feature-form" action="">
            <?php wp_nonce_field('save_feature_nonce'); ?>
            <input type="hidden" name="feature_id" id="feature_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Menu Title</label>
                    <input type="text" name="menu_title" id="menu_title" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Menu Slug (view)</label>
                    <input type="text" name="menu_slug" id="menu_slug" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Parent Menu</label>
                    <select name="parent" id="parent" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="0">None (Top Level)</option>
                        <?php foreach ($parent_menus as $pm): ?>
                            <option value="<?php echo $pm->id; ?>"><?php echo esc_html($pm->menu_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Module</label>
                    <input type="text" name="module" id="module" value="accounting" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Icon (FontAwesome)</label>
                    <input type="text" name="icon" id="icon" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="fa-solid fa-star">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="0" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" name="save_feature" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white p-2 rounded-md font-semibold transition-colors">
                        Save Feature
                    </button>
                    <button type="button" onclick="toggleFeatureForm()" class="w-full ml-2 bg-gray-500 hover:bg-gray-600 text-white p-2 rounded-md font-semibold transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Features Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-3 text-sm font-semibold text-gray-600">Icon</th>
                    <th class="p-3 text-sm font-semibold text-gray-600">Title</th>
                    <th class="p-3 text-sm font-semibold text-gray-600">Slug</th>
                    <th class="p-3 text-sm font-semibold text-gray-600">Parent</th>
                    <th class="p-3 text-sm font-semibold text-gray-600">Order</th>
                    <th class="p-3 text-sm font-semibold text-gray-600">Status</th>
                    <th class="p-3 text-sm font-semibold text-gray-600 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($features): foreach ($features as $f): ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                        <td class="p-3 text-center"><i class="<?php echo esc_attr($f->icon); ?> text-indigo-500"></i></td>
                        <td class="p-3 font-medium text-gray-800"><?php echo esc_html($f->menu_title); ?></td>
                        <td class="p-3 text-gray-600 text-sm"><?php echo esc_html($f->menu_slug); ?></td>
                        <td class="p-3 text-gray-500 text-sm">
                            <?php 
                            if ($f->parent == 0) echo '<span class="text-indigo-600 font-semibold">Root</span>';
                            else {
                                foreach ($parent_menus as $pm) if ($pm->id == $f->parent) echo $pm->menu_title;
                            }
                            ?>
                        </td>
                        <td class="p-3 text-gray-600"><?php echo $f->sort_order; ?></td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs <?php echo $f->status ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo $f->status ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="p-3 text-right space-x-2">
                            <button onclick='editFeature(<?php echo json_encode($f); ?>)' class="text-blue-600 hover:text-blue-800"><i class="fa-solid fa-pen-to-square"></i></button>
                            <a href="<?php echo add_query_arg('delete_feature', $f->id); ?>" onclick="return confirm('Are you sure?')" class="text-red-600 hover:text-red-800"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" class="p-8 text-center text-gray-400 italic">No features added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleFeatureForm() {
    const container = document.getElementById('feature-form-container');
    container.classList.toggle('hidden');
    if (!container.classList.contains('hidden')) {
        document.getElementById('feature-form').reset();
        document.getElementById('feature_id').value = '';
        document.getElementById('form-title').innerText = 'Add New Feature';
        document.getElementById('module').value = 'accounting';
    }
}

function editFeature(feature) {
    document.getElementById('feature-form-container').classList.remove('hidden');
    document.getElementById('form-title').innerText = 'Edit Feature';
    
    document.getElementById('feature_id').value = feature.id;
    document.getElementById('menu_title').value = feature.menu_title;
    document.getElementById('menu_slug').value = feature.menu_slug;
    document.getElementById('parent').value = feature.parent;
    document.getElementById('module').value = feature.module;
    document.getElementById('icon').value = feature.icon;
    document.getElementById('sort_order').value = feature.sort_order;
    document.getElementById('status').value = feature.status;
}
</script>
