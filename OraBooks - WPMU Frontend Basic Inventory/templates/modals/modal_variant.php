<div class="modal fade " id="variant-modal" tabindex='-1'>
                <form class='' id='variant-form'>
                <div class="modal-dialog modal-sm">
                  <div class="modal-content">
                    <div class="modal-header header-custom">
                      <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <label aria-hidden="true">&times;</label></button>
                      <h4 class="modal-title text-center">Add Variant</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                          <div class="col-md-12">
                            <div class="box-body">
                              <div class="form-group">
                                <label for="variant">Variant*</label>
                                <label id="variant_msg" class="text-danger text-right pull-right"></label>
                                <input type="text" class="form-control " id="variant" name="variant" placeholder="" >
                              </div>
                            </div>
                          </div>
                          <div class="col-md-12">
                            <div class="box-body">
                              <div class="form-group">
                                <label for="description">Description</label>
                                <label id="description_msg" class="text-danger text-right pull-right"></label>
                                <textarea type="text" class="form-control" id="description" name="description" placeholder="" ></textarea>
                              </div>
                            </div>
                          </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                      <input type="hidden" name="store_id" value="">
                      <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Close</button>
                      <button type="button" class="btn btn-primary add_variant">Save</button>
                    </div>
                  </div>
                  <!-- /.modal-content -->
                </div>
                <!-- /.modal-dialog -->
                </form>
              </div>
              <!-- /.modal -->

<script>
  jQuery(document).ready(function($){
    $('.add_variant').on('click', function(e){
        e.preventDefault();
      
        var variant = $('#variant').val();
        var description = $('#description').val();

        if(variant.trim() === ''){
            $('#variant_msg').text('Variant Name is required').show();
            return;
        }
        
        var data = {
            action: 'frontend_save_variant',
            variant_name: variant,
            description: description,
            security: typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.nonce : '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
        };

        var ajax_url = typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>';

        $.post(ajax_url, data, function(response){
            if(response.success){
                var modalEl = document.getElementById('variant-modal');
                var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.hide();

                Swal.fire({
                    title: 'Success!',
                    text: 'Variant inserted successfully.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });

                $('#variant-form')[0].reset();
                $('#variant_msg').hide();

                // If specialized for some select, append here.
            } else {
                Swal.fire('Error', response.data.message || 'Failed to save variant', 'error');
            }
        }, 'json');
    });
  });
</script>