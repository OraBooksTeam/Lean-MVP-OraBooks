<div class="modal fade" id="brand_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Brand</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="brand_form">
        <div class="modal-body">
          <div class="mb-3">
            <label for="unit_name" class="form-label">Brand Name *</label>
            <input type="text" class="form-control" id="brand_name" name="brand_name" required>
            <span id="brand_name_msg" class="text-danger small"></span>
          </div>
          <div class="mb-3">
            <label for="unit_description" class="form-label">Description</label>
            <textarea class="form-control" id="brand_description" name="description"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="save_brand">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
              <!-- /.modal -->
<script>
  jQuery(document).ready(function($){

    $('#save_brand').on('click', function(e){
        e.preventDefault();
      
        var brand_name = $('#brand_name').val();
        var description = $('#brand_description').val();

        if(brand_name.trim() === ''){
            $('#brand_name_msg').text('Brand Name is required').show();
            return;
        }

        var data = {
            action: 'frontend_save_brand', // WordPress AJAX hook
            brand_name: brand_name,
            description: description,
            security: typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.nonce : '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
        };

        var ajax_url = typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>';

        $.post(ajax_url, data, function(response){
            if(response.success){
                // Close the modal
                var modalEl = document.getElementById('brand_modal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                Swal.fire({
                    title: 'Success!',
                    text: 'Brand inserted successfully.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });

                // Clear form
                $('#brand_form')[0].reset();
                $('#brand_name_msg').hide();

                // Append new option to SELECT with ID 'brand_id'
                $('select[name="brand_id"]').append(`<option value="${response.data.id}" selected>${brand_name}</option>`).trigger('change');
            } else {
                Swal.fire('Error', response.data.message || 'Failed to save brand', 'error');
            }
        }, 'json');
    });

  });

</script>