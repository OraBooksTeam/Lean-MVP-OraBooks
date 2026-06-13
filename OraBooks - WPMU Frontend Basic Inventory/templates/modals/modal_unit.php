<div class="modal fade" id="unit_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Unit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="unit_form">
        <div class="modal-body">
          <div class="mb-3">
            <label for="unit_name" class="form-label">Unit Name *</label>
            <input type="text" class="form-control" id="unit_name" name="unit_name" required>
            <span id="unit_name_msg" class="text-danger small"></span>
          </div>
          <div class="mb-3">
            <label for="unit_description" class="form-label">Description</label>
            <textarea class="form-control" id="unit_description" name="description"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="save_unit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  jQuery(document).ready(function($){

    $('#save_unit').on('click', function(e){
        e.preventDefault();
      
        var unit_name = $('#unit_name').val();
        var description = $('#unit_description').val();

        if(unit_name.trim() === ''){
            $('#unit_name_msg').text('Unit Name is required').show();
            return;
        }

        var data = {
            action: 'frontend_save_unit', // WordPress AJAX hook
            unit_name: unit_name,
            description: description,
            security: typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.nonce : '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
        };

        var ajax_url = typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>';

        $.post(ajax_url, data, function(response){
            if(response.success){
                // Close the modal
                var modalEl = document.getElementById('unit_modal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                Swal.fire({
                    title: 'Success!',
                    text: 'Unit inserted successfully.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });

                // Clear form
                $('#unit_form')[0].reset();
                $('#unit_name_msg').hide();

                // Append new option to SELECT with name 'unit_id'
                $('select[name="unit_id"]').append(`<option value="${response.data.id}" selected>${unit_name}</option>`).trigger('change');
            } else {
                Swal.fire('Error', response.data.message || 'Failed to save unit', 'error');
            }
        }, 'json');
    });

  });

</script>