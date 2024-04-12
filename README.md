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

### Custom Purge Integration
```
$path = '/path-to-my-page/';

// RETURNS: true on success, false on failure
UMCloudflare::purgePage( $path );
```
