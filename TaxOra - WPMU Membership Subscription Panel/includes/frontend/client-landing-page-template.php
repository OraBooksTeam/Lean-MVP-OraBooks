<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="orabooks-client-landing-wrapper" style="padding: 0; margin: 0;">
    <?php
    if (have_posts()) :
        while (have_posts()) : the_post();
            the_content();
        endwhile;
    else :
        // Shortcode removed - no fallback content
    endif;
    ?>
</div>
<?php
get_footer();
