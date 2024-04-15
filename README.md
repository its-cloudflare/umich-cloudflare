U-M Cloudflare Cache Purge Manager
===================================
Provides easy and automatic ability to purge cloudflare cache for your whole site, section, or a page.  Also adds metabox to override TTL and Disable cache for a specific page/post/cpt.

This requires that you have an existing CloudFlare API key.

### Filters
**umich_cloudflare_settings**

Override the plugins saved API settings.
```
add_filter( 'umich_cloudflare_settings', function( $settings ){
    // your code here to modify settings as necessary

    return $settings;
});
```

**umich_cloudflare_menu_purge_page**

Ability to disable the Admin Menu Page Purge menu item.
```
add_filter( 'umich_cloudflare_menu_purge_page', '__return_false' );
```

**umich_cloudflare_menu_purge_section**

Ability to disable the Admin Menu Section Purge menu item.
```
add_filter( 'umich_cloudflare_menu_purge_section', '__return_false' );
```

**umich_cloudflare_admin_form_settings**

Customize which settings should be available for management.  Useful in complex wordpress environments.
```
add_filter('umich_cloudflare_admin_form_settings', function( $settings ){
    $settings['apikye']                   = false;
    $settings['zone']                     = false;
    $settings['ttl']                      = false;
    $settings['ttl_static']               = false;
    $settings['multisite']['apioverride'] = true;
    return $settings;
});
```

#### Pantheon Filters
**umich_cloudflare_pantheon_surrogate_keys**

Adjust which surrogate keys are issued in the request.
```
add_filter( 'umich_cloudflare_pantheon_surrogate_keys', function( $keys ){
    $keys[] = 'my-custom-key';

    return $keys;
});
```

### Actions
**umich_cloudflare_purge_page**

Called before cloudflare purge page request.
```
$paths: paths to be purged
add_action( 'umich_cloudflare_purge_page', function( $paths ){
    // do something
});
```

**umich_cloudflare_purge_all**

Called before cloudflare purge all request
```
$paths: paths to be purged
add_action( 'umich_cloudflare_purge_all', function( $paths ){
    // do something
});
```

**umich_cloudflare_admin_settings_save**

Called after settings validation and before settings save.
```
$settings:  reference of the form settings
$hasErrors: reference of the forms boolean error flag
add_action( 'umich_cloudflare_admin_settings_save', function( $settings, $hasErrors ){
    // custom action code here
    if( !$hasErrors ) {
        if( $settings['apikey'] ) {
            // do something
        }
    }
});
```

### Custom Purge Integration
There are some public functions that can be called to perform custom cache purging integrations.

**UMCloudflare::purgePage( $path )**
Purge a specific url from the cache.
```
// RETURNS: true on success, false on failure
UMCloudflare::purgePage( '/path-to-my-page/' );
```

**UMCloudflare::purgeAll( $paths )**
Purge everything that starts with a path
```
// RETURNS: true on success, false on failure
UMCloudflare::purgeAll('/');
```
