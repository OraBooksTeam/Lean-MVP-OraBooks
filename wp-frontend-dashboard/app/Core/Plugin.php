<?php
namespace WPFD\Core;

use WPFD\REST\AuthController;
use WPFD\REST\PostController;
use WPFD\REST\PageController;
use WPFD\REST\MenuController;
use WPFD\REST\SettingsController;
use WPFD\REST\ThemeController;
use WPFD\REST\MediaController;
use WPFD\REST\UserController;
use WPFD\REST\CommentController;
use WPFD\REST\PluginController;
use WPFD\REST\SiteHealthController;
use WPFD\REST\AdminMenuController;
use WPFD\REST\DashboardController;
use WPFD\REST\TaxonomyController;
use WPFD\REST\ToolsController;
use WPFD\REST\DashboardFeaturesController;
use WPFD\REST\SecurityController;
use WPFD\Core\AdminMasker;
use WPFD\Core\TaxOraIntegration;

class Plugin {
    public static function init() {
        // Plugin runs on all sites (including main) so frontend login routing and redirects are consistent

        add_action('rest_api_init', function () {
            // (new AuthController)->register(); // Disabled to use default WP login/register
            (new PostController)->register();
            (new PageController)->register();
            (new MenuController)->register();
            (new SettingsController)->register();
            (new ThemeController)->register();
            (new MediaController)->register();
            (new UserController)->register();
            (new CommentController)->register();
            (new PluginController)->register();
            (new SiteHealthController)->register();
            (new AdminMenuController)->register();
            (new DashboardController)->register();
            (new TaxonomyController)->register();
            (new ToolsController)->register();
            (new DashboardFeaturesController)->register();
            (new SecurityController)->register();
        });

        (new Assets)->register();
        (new Router)->register();
        (new AccessManager)->register();
        (new MenuHandler)->register();
        (new AdminMasker)->register();
        
        // Initialize TaxOra integration if available
        TaxOraIntegration::get_instance();
    }
}
