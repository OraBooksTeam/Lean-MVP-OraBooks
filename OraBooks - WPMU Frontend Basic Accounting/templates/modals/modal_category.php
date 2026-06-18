<div class="modal fade" id="category_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="category_form">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Category Code *</label>
            <input type="text" class="form-control" id="category_code" name="category_code" placeholder="Generating..." readonly>
          </div>
          <div class="mb-3">
            <label for="unit_name" class="form-label">Category Name *</label>
            <input type="text" class="form-control" id="category_name" name="category_name" required>
            <span id="category_name_msg" class="text-danger small"></span>
          </div>
          <div class="mb-3">
            <label for="unit_description" class="form-label">Description</label>
            <textarea class="form-control" id="catdescription" name="description"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="save_category">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
              <!-- /.modal -->
<script>
  jQuery(document).ready(function($){

    function generateCategoryCode() {
        $.post(obn_ajax.ajax_url, {
            action: 'frontend_generate_category_code',
            security: obn_ajax.nonce
        }, function(response) {
            if (response.success) {
                $('#category_code').val(response.data.code);
            }
        });
    }

    // Generate code when modal is opened
    $('#category_modal').on('show.bs.modal', function() {
        generateCategoryCode();
    });

    $('#save_category').on('click', function(e){
        e.preventDefault();
      
        var category_name = $('#category_name').val();
        var category_code = $('#category_code').val();
        var catdescription = $('#catdescription').val();

        if(category_name.trim() === ''){
            $('#category_name_msg').text('Category Name is required').show();
            return;
        }
        
        var data = {
            action: 'frontend_save_category', // WordPress AJAX hook
            category_name: category_name,
            category_code: category_code,
            description: catdescription,
            security: obn_ajax.nonce
        };

        var ajax_url = obn_ajax.ajax_url;

        $.post(ajax_url, data, function(response){
            if(response.success){
                // Close the modal
                var modalEl = document.getElementById('category_modal');
                var bootstrapObj = window.bootstrap || bootstrap;
                if (bootstrapObj && bootstrapObj.Modal) {
                    var modal = bootstrapObj.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                $(modalEl).removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
                $('.modal-backdrop').remove();

                Swal.fire({
                    title: 'Success!',
                    text: 'Category inserted successfully.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });

                // Clear form
                $('#category_form')[0].reset();
                $('#category_name_msg').hide();

                // Append new option to SELECT with ID 'category_id'
                $('select[name="category_id"]').append(`<option value="${response.data.id}" selected>${category_name}</option>`).trigger('change');
                
                // If Select2 is used, it will be updated by trigger('change')
            } else {
                Swal.fire('Error', response.data.message || 'Failed to save category', 'error');
            }
        }, 'json');
    });

  });

</script>