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
            <label class="form-label">Brand Code *</label>
            <input type="text" class="form-control" id="brand_code" name="brand_code" placeholder="Generating..." readonly>
          </div>
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

    function generateBrandCode() {
        $.post(obn_ajax.ajax_url, {
            action: 'frontend_generate_brand_code',
            security: obn_ajax.nonce
        }, function(response) {
            if (response.success) {
                $('#brand_code').val(response.data.code);
            }
        });
    }

    // Generate code when modal is opened
    $('#brand_modal').on('show.bs.modal', function() {
        generateBrandCode();
    });

    $('#save_brand').on('click', function(e){
        e.preventDefault();
      
        var brand_name = $('#brand_name').val();
        var brand_code = $('#brand_code').val();
        var description = $('#brand_description').val();

        if(brand_name.trim() === ''){
            $('#brand_name_msg').text('Brand Name is required').show();
            return;
        }

        var data = {
            action: 'frontend_save_brand', // WordPress AJAX hook
            brand_name: brand_name,
            brand_code: brand_code,
            description: description,
            security: obn_ajax.nonce
        };

        var ajax_url = obn_ajax.ajax_url;

        $.post(ajax_url, data, function(response){
            if(response.success){
                // Close the modal
                var modalEl = document.getElementById('brand_modal');
                var bootstrapObj = window.bootstrap || bootstrap;
                if (bootstrapObj && bootstrapObj.Modal) {
                    var modal = bootstrapObj.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                $(modalEl).removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
                $('.modal-backdrop').remove();

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