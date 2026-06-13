jQuery(document).ready(function ($) {
    if (typeof multisiteGlobalMenuFrontend === 'undefined' || !multisiteGlobalMenuFrontend.globalLogo) {
        return;
    }

    var globalLogo = multisiteGlobalMenuFrontend.globalLogo;

    // Common selectors for logos in various themes
    var logoSelectors = [
        '.custom-logo',
        '.site-logo img',
        '.logo img',
        '.navbar-brand img',
        '#logo img',
        '.header-logo img',
        '.brand img',
        '.branding img'
    ];

    // Try to replace src attribute for image tags
    var logoFound = false;
    $.each(logoSelectors, function (index, selector) {
        var $element = $(selector);
        if ($element.length) {
            if ($element.is('img')) {
                // Set the src attribute to the global logo
                $element.attr('src', globalLogo);
                // Clear srcset to prevent responsive images from overriding
                $element.attr('srcset', '');
                // Ensure the image is fully visible
                $element.css({
                    'opacity': '1',
                    'visibility': 'visible',
                    'display': 'block'
                });
                logoFound = true;
                console.log('Global Menu: Logo replaced successfully with ' + globalLogo);
            }
        }
    });

    // If no image tag found, or as a backup, try to inject via CSS if the theme uses background images for logo
    // This is handled by the PHP add_custom_css function, but we can reinforce it here if needed.
    // However, the PHP function targets .custom-logo. Let's add a few more common classes if PHP didn't catch it.

    if (!logoFound) {
        // Try to find a link that looks like a logo wrapper
        var $logoLink = $('.site-logo a, .logo a, .navbar-brand, .brand');
        if ($logoLink.length && !$logoLink.find('img').length) {
            // If it's a text logo or background image logo, we might want to prepend an image
            // But this is risky as it might break layout. 
            // Let's stick to replacing existing images for now to be safe.
            console.log('Global Menu: No logo image found to replace.');
        }
    }

    // --- Menu Injection Logic ---
    if (multisiteGlobalMenuFrontend.menuItems) {
        var menuItems = multisiteGlobalMenuFrontend.menuItems;

        // Check if we have items to add
        if (Object.keys(menuItems).length > 0) {

            // Common menu selectors
            var menuSelectors = [
                '#primary-menu',
                '.primary-menu',
                '#main-menu',
                '.main-menu',
                '.main-navigation ul',
                '#site-navigation ul',
                '.nav-menu',
                '.navbar-nav',
                'ul.menu'
            ];

            var menuFound = false;

            // First check if items are already there (added by PHP)
            // We'll check for a unique class or ID if we had one, but for now let's just check if the menu exists
            // If the PHP filter worked, the items should be there.
            // But since we don't have a unique ID on the items in the JS object easily matching the DOM without parsing,
            // let's assume if we find the menu container, we might need to append if they aren't there.

            // Actually, let's look for a specific class we add in PHP: 'global-menu-item'? 
            // The PHP code adds classes from the settings.

            // Let's try to find the menu container
            $.each(menuSelectors, function (index, selector) {
                if (menuFound) return;

                var $menu = $(selector).first();
                if ($menu.length) {
                    // Check if it's a UL
                    if ($menu.prop('tagName') !== 'UL') {
                        $menu = $menu.find('ul').first();
                    }

                    if ($menu.length) {
                        menuFound = true;
                        console.log('Global Menu: Found menu container: ' + selector);

                        // Check if our items are already there?
                        // We can check if the first item's URL matches one of our global items
                        var firstGlobalItem = Object.values(menuItems)[0];
                        var alreadyAdded = false;

                        $menu.find('li a').each(function () {
                            if ($(this).attr('href') === firstGlobalItem.url) {
                                alreadyAdded = true;
                                return false;
                            }
                        });

                        if (!alreadyAdded) {
                            console.log('Global Menu: Injecting items via JS');

                            $.each(menuItems, function (key, item) {
                                var target = item.target ? item.target : '_self';
                                var classes = item.classes ? item.classes : '';
                                var liClass = 'menu-item menu-item-type-custom menu-item-object-custom ' + classes;

                                var html = '<li class="' + liClass + '"><a href="' + item.url + '" target="' + target + '">' + item.title + '</a></li>';
                                $menu.append(html);
                            });
                        } else {
                            console.log('Global Menu: Items already present (likely via PHP)');
                        }
                    }
                }
            });
        }
    }
});
