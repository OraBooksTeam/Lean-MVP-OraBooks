jQuery(document).ready(function ($) {
    var mediaUploader;

    // Logo upload functionality
    $('#upload-logo-btn').on('click', function (e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: multisiteGlobalMenu.title,
            button: {
                text: multisiteGlobalMenu.buttonText
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();

            $.ajax({
                url: multisiteGlobalMenu.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_global_logo',
                    logo_url: attachment.url,
                    nonce: multisiteGlobalMenu.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('.logo-preview').html('<img src="' + attachment.url + '" alt="Global Logo" style="max-width: 200px; height: auto;" />');
                        $('#upload-logo-btn').text(multisiteGlobalMenu.changeLogoText || 'Change Logo');

                        if (!$('#remove-logo-btn').length) {
                            $('.logo-actions').append('<button type="button" id="remove-logo-btn" class="button button-link-delete">' + multisiteGlobalMenu.removeLogoText + '</button>');
                            $('#remove-logo-btn').on('click', removeLogoHandler);
                        }

                        alert(response.data.message);
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        mediaUploader.open();
    });

    // Logo removal functionality
    function removeLogoHandler(e) {
        e.preventDefault();

        if (!confirm(multisiteGlobalMenu.confirmLogoDelete)) {
            return;
        }

        $.ajax({
            url: multisiteGlobalMenu.ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_global_logo',
                nonce: multisiteGlobalMenu.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('.logo-preview').html('<div class="no-logo">' + multisiteGlobalMenu.noLogoText + '</div>');
                    $('#upload-logo-btn').text(multisiteGlobalMenu.uploadLogoText);
                    $('#remove-logo-btn').remove();
                    alert(response.data.message);
                }
            }
        });
    }

    // Bind remove logo handler
    $(document).on('click', '#remove-logo-btn', removeLogoHandler);

    // Form validation for menu items (only apply to menu item form)
    $('form').on('submit', function (e) {
        // Skip validation for delete forms
        if ($(this).find('input[name="delete_global_menu_item"]').length > 0) {
            return true;
        }

        // Only validate if this is the menu item form (has menu_title field)
        if ($(this).find('#menu_title').length > 0) {
            var title = $('#menu_title').val();
            var url = $('#menu_url').val();

            if (!title || !title.trim()) {
                alert('Please enter a menu title');
                e.preventDefault();
                return false;
            }

            if (!url || !url.trim()) {
                alert('Please enter a URL');
                e.preventDefault();
                return false;
            }

            // Basic URL validation - Allow http://, https://, or relative paths starting with /
            if (!url.match(/^https?:\/\//) && !url.match(/^\//)) {
                alert('Please enter a valid URL starting with http://, https://, or a relative path starting with /');
                e.preventDefault();
                return false;
            }
        }

        // Allow other forms to submit normally
        return true;
    });
});