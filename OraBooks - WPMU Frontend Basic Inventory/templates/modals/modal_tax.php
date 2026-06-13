<div class="modal fade" id="tax_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Tax</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="tax_form">
        <div class="modal-body">
          <div class="mb-3">
            <label for="unit_name" class="form-label">Tax Name *</label>
            <input type="text" class="form-control" id="tax_name" name="tax_name" required>
            <span id="tax_name_msg" class="text-danger small"></span>
          </div>
          <div class="mb-3">
            <label for="unit_description" class="form-label">Tax Percentage*</label>
            <input type="text" class="form-control only_currency" id="tax_percent" name="tax_percent" placeholder="0.00" >
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="save_tax">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
              <!-- /.modal -->
<script>
  jQuery(document).ready(function($){

    $('#save_tax').on('click', function(e){
        e.preventDefault();
      
        var tax_name = $('#tax_name').val();
        var tax_percent = $('#tax_percent').val();

        if(tax_name.trim() === ''){
            $('#tax_name_msg').text('Tax Name is required').show();
            return;
        }
        
        var data = {
            action: 'frontend_save_tax', // WordPress AJAX hook
            tax_name: tax_name,
            tax_percent: tax_percent,
            security: typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.nonce : '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
        };

        var ajax_url = typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>';

        $.post(ajax_url, data, function(response){
            if(response.success){
                // Close the modal
                var modalEl = document.getElementById('tax_modal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                Swal.fire({
                    title: 'Success!',
                    text: 'Tax inserted successfully.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });

                // Clear form
                $('#tax_form')[0].reset();
                $('#tax_name_msg').hide();

                // Append new option to SELECT with name 'tax_id'
                $('select[name="tax_id"]').append(`<option value="${response.data.id}" selected>${tax_name} (${tax_percent}%)</option>`).trigger('change');
            } else {
                Swal.fire('Error', response.data.message || 'Failed to save tax', 'error');
            }
        }, 'json');
    });

  });

</script>