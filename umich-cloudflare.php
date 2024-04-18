<?php
/**
 * Plugin Name: U-M: Cloudflare Cache
 * Plugin URI: https://github.com/its-cloudflare/umich-cloudflare/
 * Description: Provides cloudflare cache purging functionality.
 * Version: 1.0.0
 * Author: U-M: OVPC Digital
 * Author URI: http://vpcomm.umich.edu
 * Update URI: https://github.com/its-cloudflare/umich-cloudflare/releases/latest
 */

define( 'UMCLOUDFLARE_PATH', dirname( __FILE__ ) . DIRECTORY_SEPARATOR );

include UMCLOUDFLARE_PATH .'includes'. DIRECTORY_SEPARATOR .'override.php';
include UMCLOUDFLARE_PATH .'includes'. DIRECTORY_SEPARATOR .'umich-github-updater.php';

// pantheon integrations
if( isset( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
    include UMCLOUDFLARE_PATH .'integrations'. DIRECTORY_SEPARATOR . 'pantheon' . DIRECTORY_SEPARATOR .'pantheon.php';
}

class UMCloudflare
{
    static private $_defaultTTL       = 7200;
    static private $_defaultStaticTTL = 31536000;

    static private $_settings = array(
        'apibase'    => 'https://api.cloudflare.com/client/v4/',
        'apikey'     => '',
        'zone'       => '',
        'ttl'        => '',
        'ttl_static' => '',
    );

    static private $_siteSettings = [
        'apikey'     => '',
        'zone'       => '',
        'ttl'        => '',
        'ttl_static' => '',
    ];

    static private $_networkSettings = [
        'apikey'     => '',
        'zone'       => '',
        'ttl'        => '',
        'ttl_static' => '',
    ];

    static public function init()
    {
        // Initialize Github Updater
        new \Umich\GithubUpdater\Init([
            'repo' => 'its-cloudflare/umich-cloudflare',
            'slug' => plugin_basename( __FILE__ ),
        ]);

        add_action( 'init', function(){
            // IF LOGGED IN COOKIE AND COOKIE STALE (not logged in), LOGOUT
            if( isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) && !is_user_logged_in() ) {
                add_action( 'wp_logout', function(){
                    wp_redirect( $_SERVER['REQUEST_URI'] );
                    exit;
                });

                setcookie( TEST_COOKIE, '', -3600, SITECOOKIEPATH, COOKIE_DOMAIN );
                wp_logout();

                wp_redirect( $_SERVER['REQUEST_URI'] );
                exit;
            }

            self::$_networkSettings = array_merge(
                self::$_networkSettings,
                array_filter( get_site_option( 'umich_cloudflare_settings', array() ), 'trim' )
            );
            self::$_siteSettings    = array_merge(
                self::$_siteSettings,
                array_filter( get_option( 'umich_cloudflare_settings', array() ), 'trim' )
            );

            // merge settings
            self::$_settings = array_merge(
                self::$_settings,                                // default settings
                array_filter( self::$_networkSettings, 'trim' ), // network settings
                array_filter( self::$_siteSettings,    'trim' )  // site settings
            );

            self::$_settings = apply_filters( 'umich_cloudflare_settings', self::$_settings );
        });

        /** GLOBAL CHANGES: FULL SITE PURGE **/
        // Theme Updates
        add_action( 'switch_theme', function(){
            // TRIGGER FULL SITE PURGE
            self::purgeAll( '/' );
        });
        add_filter( 'customize_save_after', function(){
            // TRIGGER FULL SITE PURGE
            self::purgeAll( '/' );
        });

        // Widget Updates
        add_filter( 'widget_update_callback', function( $instance ){
            // TRIGGER FULL SITE PURGE
            self::purgeAll( '/' );

            // @REQUIRED to return the instance
            return $instance;
        });

        /** SPECIFIC PAGE UPDATES: SINGLE PAGE PURGE **/
        // Post Updates
        add_action( 'save_post',          array( __CLASS__, 'onPostUpdate' ), 10, 2 );
        add_action( 'before_delete_post', array( __CLASS__, 'onPostUpdate' ), 10, 2 );
        add_action( 'attachment_updated', array( __CLASS__, 'onPostUpdate' ), 10, 2 );
        add_action( 'delete_attachment',  array( __CLASS__, 'onPostUpdate' ), 10, 2 );

        // Taxonomy Updates
        add_action( 'edited_term',     array( __CLASS__, 'onTermUpdate' ) );
        add_action( 'pre_delete_term', array( __CLASS__, 'onTermUpdate' ) );

        // New Comment OR Status Change
        add_action( 'comment_post',          array( __CLASS__, 'onCommentUpdate' ) );
        add_action( 'wp_set_comment_status', array( __CLASS__, 'onCommentUpdate' ) );

        /** CACHE-CONTROL HEADERS **/
        add_filter( 'wp_headers', function( $headers ){
            global $post;

            // skip if logged in
            if( !is_user_logged_in() ) {
                $requestTTL = self::getRequestTTL();

                // override if we have a ttl we wish to set
                if( is_numeric( $requestTTL ) ) {
                    $headers['Cache-Control'] = "public, max-age={$requestTTL}";
                    $headers['X-Cache-Control'] = "public, max-age={$requestTTL}";
                }
            }

            return $headers;
        }, 999 );

        /** ADMIN **/
        add_action( 'wp_before_admin_bar_render',        array( __CLASS__, 'adminBarRender' ) );
        add_action( 'wp_ajax_umcloudflare_clear',        array( __CLASS__, 'ajaxOnPurge' ) );
        add_action( 'wp_ajax_nopriv_umcloudflare_clear', array( __CLASS__, 'ajaxOnPurge' ) );

        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueueScripts' ) );
        add_action( 'wp_enqueue_scripts',    array( __CLASS__, 'enqueueScripts' ) );

        /** ADMIN SETTINGS **/
        add_action( 'admin_menu', array( __CLASS__, 'adminMenu' ) );

        if( is_multisite() ) {
            add_action( 'network_admin_menu', function(){
                self::adminMenu( true );
            });
        }

        // plugin links
        add_filter( 'plugin_row_meta', function( $links, $pluginFile ){
            if( $pluginFile == plugin_basename( __FILE__ ) ) {
                $links[] = '<a href="https://github.com/its-cloudflare/umich-cloudflare/issues" target="_blank" title="'. esc_attr__( 'Support', 'umich-cloudflare' ) .'">'. esc_html__( 'Support', 'umich-cloudflare' ) .'</a>';
            }

            return $links;
        }, 10, 2 );

        add_filter( 'plugin_action_links_'. plugin_basename( __FILE__ ), function( $links ){
            $links[] = '<a href="'. admin_url( 'options-general.php?page=umich_cloudflare' ) .'">Settings</a>';
            return $links;
        });

        add_filter( 'network_admin_plugin_action_links_'. plugin_basename( __FILE__ ), function( $links ){
            $links[] = '<a href="'. network_admin_url( 'settings.php?page=umich_cloudflare' ) .'">Settings</a>';
            return $links;
        });

        // get cloudflare zones for apikey
        add_action( 'wp_ajax_umcloudflare_zones', function(){
            $return = array(
                'status'  => 'fail',
                'message' => '',
                'zones'   => [],
                'nonce'   => ''
            );

            if( check_ajax_referer( 'umich-cloudflare-nonce', 'nonce', false ) && current_user_can( 'administrator' ) ) {
                self::$_settings['apikey'] = $_POST['apikey'];

                $res = self::_callAPI( 'zones' );

                json_decode( $res );
                if( json_last_error() === JSON_ERROR_NONE ) {
                    $res = json_decode( $res );

                    if( $res->success && is_array( @$res->result ) ) {
                        $return['status'] = 'success';

                        foreach( $res->result as $zone ) {
                            $return['zones'][] = [
                                'id'   => $zone->id,
                                'name' => $zone->name
                            ];
                        }
                    }
                    else {
                        if( $res->errors ) {
                            $return['message'] = [];
                            foreach( $res->errors as $error ) {
                                $return['message'][] = @$error->message ?: 'Unknown Error';
                            }
                            $return['message'] = implode( ', ', $return['message'] );
                        }
                        else {
                            $return['message'] = 'API Key Failed';
                        }
                    }
                }
                else {
                    $return['message'] = 'Invalid response from Cloudflare.';
                }
            }

            $return['nonce'] = wp_create_nonce( 'umich-cloudflare-nonce' );

            echo json_encode( $return );

            wp_die();
        });


        /** INTEGRATIONS **/

        // PLUGIN: Enable Media Replace
        add_action( 'emr/cache/flush', function( $pID ){
            self::onPostUpdate( $pID, get_post( $pID ) );
        });
    }

    /**
     * Checks if plugin has been configured
     */
    static public function isConfigured()
    {
        if( self::$_settings['apikey'] && self::$_settings['zone'] ) {
            return true;
        }

        return false;
    }

    /**
     * Get the TTL for this requests response
     */
    static public function getRequestTTL()
    {
        global $post;

        $requestTTL = (self::$_settings['ttl'] ?: self::$_defaultTTL);

        // use post specific settings if exists
        if( $post && $post instanceof WP_Post ) {
            if( $status = get_post_meta( $post->ID, 'umcloudflare_disable', true ) ) {
                $headers    = array_merge( $headers, wp_get_nocache_headers() );
                $requestTTL = null;
            }
            else if( is_numeric( $ttl = get_post_meta( $post->ID, 'umcloudflare_ttl', true ) ) ) {
                $requestTTL = $ttl;
            }
        }

        // override if we have a ttl we wish to set
        if( is_numeric( $requestTTL ) ) {
            $requestTTL = (int) $requestTTL;
        }

        return $requestTTL;
    }

    /****************************/
    /*** PURGE FUNCTIONALITY ****/
    /****************************/

    static public function onPostUpdate( $pID, $post )
    {
        // Stop the script when doing autosave
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        // handle attachment logic
        if( $post && @$post->post_type == 'attachment' ) {
            $url = wp_get_attachment_url( $pID );
            self::purgePage( $url );

            // purge any resized version
            $meta = wp_get_attachment_metadata( $pID );
            foreach( (@$meta['sizes'] ?: array()) as $size ) {
                self::purgePage(
                    str_replace(
                        basename( parse_url( $url, PHP_URL_PATH ) ),
                        $size['file'],
                        $url
                    )
                );
            }
        }

        // PURGE POST URL
        self::purgePage( get_the_permalink( $pID ) );

        if( $post ) {
            // Purge post type archive
            if( $pArchiveUrl = get_post_type_archive_link( $post->post_type ) ) {
                self::purgeAll( $pArchiveUrl );
            }

            // Purge taxonomy archives
            foreach( get_object_taxonomies( $post ) as $tax ) {
                foreach( (get_the_terms( $pID, $tax ) ?: array()) as $term ) {
                    self::purgeAll( get_term_link( $term->term_id ) );
                }
            }
        }
    }

    static public function onCommentUpdate( $cID )
    {
        if( $comment = get_comment( $cID ) ) {
            $post = get_post( $comment->comment_post_ID );

            self::onPostUpdate( $comment->comment_post_ID, $post );
        }
    }

    static public function onTermUpdate( $termID )
    {
        $termLink = get_term_link( $termID );

        if( $termLink && !is_wp_error( $termLink ) ) {
            self::purgeAll( $termLink );
        }
    }

    /**
     * Purge one or more url paths
     *
     * @param  string|array $paths paths to be purged
     * @return bool
     */
    static public function purgePage( $paths )
    {
        if( !self::isConfigured() ) {
            return false;
        }

        $paths = (array) $paths;

        foreach( $paths as &$url ) {
            $url = self::_cleanupURL( $url );
        }
        unset( $url );

        do_action( 'umich_cloudflare_purge_page', $paths );

        return self::_purge( [ 'files' => $paths ] );
    }

    /**
     * Purge everything beginning with one or more paths
     *
     * @param  string|array $paths paths to be purged
     * @return bool
     */
    static public function purgeAll( $paths )
    {
        if( !self::isConfigured() ) {
            return false;
        }

        $paths = (array) $paths;

        foreach( $paths as &$path ) {
            if( !parse_url( $path, PHP_URL_HOST ) ) {
                $path = 'http://'. parse_url( get_site_url(), PHP_URL_HOST ) . $path;
            }

            $path = self::_cleanupURL( $path );

            if( ($scheme = parse_url( $path, PHP_URL_SCHEME )) ) {
                $path = preg_replace( '#^'. preg_quote( $scheme, '#' ) .'://#', '', $path );
            }
        }
        unset( $path );

        do_action( 'umich_cloudflare_purge_all', $paths );

        return self::_purge( [ 'prefixes' => $paths ] );
    }

    /**
     * Make call to cloudflare purge api
     *
     * @param array $params cloudflare purge params
     * @return bool
     */
    static private function _purge( $params )
    {
        $res = self::_callAPI(
            '/zones/'. self::$_settings['zone'] .'/purge_cache',
            'POST',
            json_encode( $params )
        );

        json_decode( $res );
        if( $res && (json_last_error() === JSON_ERROR_NONE) ) {
            $res = json_decode( $res );

            if( $res->result ) {
                return true;
            }

            foreach( array( 'errors', 'messages' ) as $type ) {
                foreach( $res->$type as $message ) {
                    error_log( "[CLOUDFLARE] (Endpoint: {$endpoint}) (Type: {$type}) (Message: {$message->message})" );
                }
            }
        }

        return false;
    }

    /**
     * Normalize url and sanitize url for the blog issuing purge request
     *
     * @param  string $url URL to process
     * @return string normalized and sanitized url
     */
    static private function _cleanupURL( $url )
    {
        $baseParts = array_merge(array(
            'scheme' => '',
            'host'   => '',
            'path'   => ''
        ), parse_url( get_site_url() ) );

        $urlParts = array_merge(array(
            'scheme' => '',
            'host'   => '',
            'path'   => ''
        ), parse_url( $url ) );
        $urlParts['path'] = @$urlParts['path'] ?: '/';

        // make sure $url path starts with baseUrl path
        if( strpos( $urlParts['path'], $baseParts['path'] ) !== 0 ) {
            $urlParts['path'] = rtrim( $baseParts['path'], '/' ) .'/'. ltrim( $urlParts['path'], '/' );
        }
        // cleanup path so that it starts and ends with a /
        $urlParts['path'] = trim( $urlParts['path'], '/' );
        if( str_ends_with( $urlParts['path'], '*' ) || preg_match( '#\..{2,3}$#', $urlParts['path'] ) ) {
            $urlParts['path'] = '/'. preg_replace( '/\*$/', '', $urlParts['path'] );
        }
        else {
            $urlParts['path'] = $urlParts['path'] ? "/{$urlParts['path']}/" : '/';
        }

        // check for Wordpress MU Domain Mapping Plugin usage
        // force host to the live version
        if( function_exists( 'domain_mapping_siteurl' ) ) {
            $mapParts = parse_url( domain_mapping_siteurl( false ) );

            if( $mapParts['host'] ) {
                $baseParts['host'] = $mapParts['host'];
            }
        }

        $url = $baseParts['scheme'] .'://'. $baseParts['host'] . $urlParts['path'];

        return $url;
    }

    /**
     * CALL Cloudflare API
     *
     * @param  string $endpoint Cloudflare API endpoint to call
     * @param  string $method   HTTP Method
     * @param  string $data     Data payload to send to the API
     * @return string|bool      API response, false on failure
     */
    static private function _callAPI( $endpoint, $method = 'GET', $data = null )
    {
        $params = [
            'timeout' => 5, // in case of filter override
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer '. self::$_settings['apikey'],
                'Content-Type'   => 'application/json'
            ]
        ];

        if( $data ) {
            $params['body'] = $data;
        }

        $res = wp_remote_request(
            self::$_settings['apibase'] . $endpoint,
            $params
        );

        if( is_wp_error( $res ) ) {
            foreach( $res->get_error_messages() as $msg ) {
                error_log( "[CLOUDFLARE] {$msg}" );
            }

            return false;
        }

        return $res['body'];
    }


    /***************************/
    /*** ADMIN FUNCTIONALITY ***/
    /***************************/

    /**
     * Load: Admin bar js functionality
     **/
    static public function enqueueScripts()
    {
        if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
            wp_enqueue_script( 'umich-cloudflare', plugins_url( 'umich-cloudflare.js', __FILE__ ), array('jquery') );
            wp_add_inline_script( 'umich-cloudflare', "
                var umCFNonce = '". wp_create_nonce( 'umich-cloudflare-nonce' ) ."';\n
                var umCFPlugin = '". plugins_url( '', __FILE__ ) ."';\n
                var umCFAjaxUrl = '". admin_url( 'admin-ajax.php') ."';\n
                if( typeof ajaxurl === 'undefined' ) { var ajaxurl = umCFAjaxUrl; }\n
            ", 'before');
        }
    }

    /**
     * Add admin bar options for cache purge
     **/
    static public function adminBarRender()
    {
        global $wp_admin_bar;

        if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
            $wp_admin_bar->add_menu(array(
                'parent' => false,
                'id'     => 'umich-cloudflare-root',
                'title'  => 'Cloudflare Cache <img src="'. plugins_url( 'assets/working.svg', __FILE__ ) .'" style="vertical-align: middle; display: inline-block; visibility: hidden;"/>',
                'href'   => false
            ));
        }

        if( self::$_settings['apikey'] && self::$_settings['zone'] ) {
            if( current_user_can( 'administrator' ) ) {
                $wp_admin_bar->add_menu(array(
                    'parent' => 'umich-cloudflare-root',
                    'id'     => 'umich-cloudflare-purge-site',
                    'title'  => 'Purge All',
                    'href'   => '#',
                    'meta'   => array(
                        'onclick' => 'return umCloudflarePurge("all");'
                    )
                ));
            }

            if( !is_admin() && (current_user_can( 'administrator' ) || current_user_can( 'editor' )) ) {
                if( apply_filters( 'umich_cloudflare_menu_purge_page', true ) ) {
                    $wp_admin_bar->add_menu(array(
                        'parent' => 'umich-cloudflare-root',
                        'id'     => 'umich-cloudflare-purge-page',
                        'title'  => 'Purge Page',
                        'href'   => '#',
                        'meta'   => array(
                            'onclick' => 'return umCloudflarePurge("page");'
                        )
                    ));
                }

                if( get_option( 'permalink_structure' ) && apply_filters( 'umich_cloudflare_menu_purge_section', true ) ) {
                    $wp_admin_bar->add_menu(array(
                        'parent' => 'umich-cloudflare-root',
                        'id'     => 'umich-cloudflare-purge-section',
                        'title'  => 'Purge Section',
                        'href'   => '#',
                        'meta'   => array(
                            'onclick' => 'return umCloudflarePurge("section");'
                        )
                    ));
                }
            }
        }
        else {
            if( current_user_can( 'administrator' ) ) {
                $wp_admin_bar->add_menu(array(
                    'parent' => 'umich-cloudflare-root',
                    'id'     => 'umich-cloudflare-settings',
                    'title'  => 'Cloudflare Settings',
                    'href'   => site_url( '/wp-admin/options-general.php?page=umich_cloudflare'),
                ));
            }
        }
    }

    /**
     * Plugin setting admin
     */
    static public function adminMenu( $isNetwork = false )
    {
        $umCFFormSettings = [
            'apikey'     => true,
            'zone'       => true,
            'ttl'        => true,
            'ttl_static' => true,
            'multisite' => [
                'apioverride' => false
            ]
        ];

        if( $isNetwork ) {
            if( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
                $umCFFormSettings['multisite']['apioverride'] = true;
            }
        }
        else if( is_multisite() && (!defined( 'SUBDOMAIN_INSTALL' ) || !SUBDOMAIN_INSTALL || !$umCFSettings['multisite']['override']) ) {
            $umCFFormSettings['apikey']     = false;
            $umCFFormSettings['zone']       = false;
            $umCFFormSettings['ttl_static'] = false;
        }

        if( !file_exists( ABSPATH .'.htaccess' ) ) {
            $umCFFormSettings['ttl_static'] = false;
        }

        $umCFFormSettings = apply_filters( 'umich_cloudflare_admin_form_settings', $umCFFormSettings );

        // HANDLE FORM SAVE
        if( $_POST && isset( $_POST['umich_cloudflare_nonce'] ) && wp_verify_nonce( $_POST['umich_cloudflare_nonce'], 'umich-cloudflare' ) ) {
            $settings = array_merge([
                    'apikey'     => '',
                    'zone'       => '',
                    'ttl'        => '',
                    'ttl_static' => '',
                ],
                array_filter( $_POST['umich_cloudflare_settings'] ?: array(), 'trim' )
            );

            $settings['ttl']        = $settings['ttl']        ? (int) $settings['ttl']        : '';
            $settings['ttl_static'] = $settings['ttl_static'] ? (int) $settings['ttl_static'] : '';

            // validate apikey, zone, and ttl
            $hasErrors = false;

            // verify key
            if( $settings['apikey'] && (($thisErrors = self::_validateApiKey( $settings['apikey'] )) !== true) ) {
                $hasErrors = true;

                add_settings_error(
                    'umich_cloudflare_settings_apikey',
                    'error',
                    implode( ', ', $thisErrors ),
                    'error',
                );
            }

            // verify zone
            if( !$hasErrors && $settings['zone'] && (($thisErrors = self::_validateApiZone( $settings['zone'] )) !== true) ) {
                $hasErrors = true;

                add_settings_error(
                    'umich_cloudflare_settings_zone',
                    'error',
                    implode( ', ', $thisErrors ),
                    'error',
                );
            }

            // verify ttl
            if( $settings['ttl'] && !is_int( $settings['ttl'] ) ) {
                $hasErrors = true;

                add_settings_error(
                    'umich_cloudflare_settings_ttl',
                    'error',
                    'Invalid Default Page TTL value.',
                    'error',
                );
            }

            if( $settings['ttl_static'] && !is_int( $settings['ttl_static'] ) ) {
                $hasErrors = true;

                add_settings_error(
                    'umich_cloudflare_settings_ttl_static',
                    'error',
                    'Invalid Default Static TTL value.',
                    'error',
                );
            }

            do_action_ref_array( 'umich_cloudflare_admin_settings_save', array( &$settings, &$hasErrors ) );

            // No Errors we can save now
            if( !$hasErrors ) {
                // remove stuff we can't update
                foreach( $settings as $key => $val ) {
                    if( !$umCFFormSettings[ $key ] ) {
                        unset( $settings[ $key ] );
                    }
                }

                if( $isNetwork ) {
                    update_site_option( 'umich_cloudflare_settings', $settings );
                }
                else {
                    update_option( 'umich_cloudflare_settings', $settings );
                }

                // rebuild class $_settings
                if( $isNetwork ) {
                    self::$_networkSettings = array_merge(
                        self::$_networkSettings,
                        $settings
                    );
                }
                else {
                    self::$_siteSettings = array_merge(
                        self::$_siteSettings,
                        $settings
                    );
                }

                self::$_settings = array_merge(
                    self::$_settings,
                    $settings
                );

                if( $umCFFormSettings['ttl_static'] && (!is_multisite() || $isNetwork) ) {
                    // place htaccess based headers default
                    insert_with_markers(
                        ABSPATH .'.htaccess',
                        'UM Cloudflare',
                        str_replace(
                            '{TTL_STATIC}',
                            self::$_settings['ttl_static'] ?: self::$_defaultStaticTTL,
                            file_get_contents( UMCLOUDFLARE_PATH .'templates'. DIRECTORY_SEPARATOR .'_htaccess.tpl' )
                        )
                    );
                }
            }
        }

        add_submenu_page(
            $isNetwork ? 'settings.php' : 'options-general.php',
            'U-M: Cloudflare',
            'U-M: Cloudflare',
            'administrator',
            'umich_cloudflare',
            function() use ( $isNetwork, $umCFFormSettings ) {
                $umCFZones    = array();
                $umCFSettings = self::$_settings;

                // get network or site specific settings
                if( $isNetwork ) {
                    $umCFSettings = array_merge([
                            'apikey'     => '',
                            'zone'       => '',
                            'ttl'        => '',
                            'ttl_static' => '',
                        ],
                        self::$_networkSettings
                    );

                    $umCFSettings['default_ttl']        = self::$_defaultTTL;
                    $umCFSettings['default_ttl_static'] = self::$_defaultStaticTTL;
                }
                else {
                    // get just the sites settings
                    $umCFSettings = array_merge([
                            'apikey'     => '',
                            'zone'       => '',
                            'ttl'        => '',
                            'ttl_static' => '',
                        ],
                        self::$_siteSettings
                    );

                    $umCFSettings['default_ttl']        = is_multisite() && self::$_networkSettings['ttl']        ? self::$_networkSettings['ttl']        : self::$_defaultTTL;
                    $umCFSettings['default_ttl_static'] = is_multisite() && self::$_networkSettings['ttl_static'] ? self::$_networkSettings['ttl_static'] : self::$_defaultStaticTTL;
                }

                if( $umCFFormSettings['zone'] && $umCFSettings['apikey'] ) {
                    $res = self::_callAPI( 'zones' );

                    json_decode( $res );
                    if( json_last_error() === JSON_ERROR_NONE ) {
                        $res = json_decode( $res );

                        if( $res->success && is_array( @$res->result ) ) {
                            foreach( $res->result as $zone ) {
                                $umCFZones[] = [
                                    'id'   => $zone->id,
                                    'name' => $zone->name
                                ];
                            }
                        }
                    }
                }

                include UMCLOUDFLARE_PATH .'templates'. DIRECTORY_SEPARATOR .'admin.tpl';
            }
        );
    }

    /**
     * Handles Admin Bar menu functionality
     */
    static public function ajaxOnPurge()
    {
        $url = isset( $_POST['url'] ) ? $_POST['url'] : false;

        $return = array(
            'status' => 'fail',
            'url'    => $url,
            'nonce'  => ''
        );

        if( check_ajax_referer( 'umich-cloudflare-nonce', 'nonce', false ) ) {
            switch( @$_POST['type'] ) {
                case 'all':
                    // TRIGGER FULL SITE PURGE
                    $return['status'] = self::purgeAll( '/' );
                    break;

                case 'page':
                    if( $url ) {
                        // TRIGGER PAGE PURGE
                        $return['status'] = self::purgePage( $url );
                    }
                    break;

                case 'section':
                    if( $url && get_option( 'permalink_structure' ) ) {
                        // TRIGGER PAGE PURGE
                        $urlParts = parse_url( $url );

                        // get the first segement of the url
                        list( $path ) = explode( '/', trim( $urlParts['path'], '/' ) );
                        $path = $path ? "/{$path}/" : '/';

                        // update url so that its the path of the first segment instead of full url
                        $url = str_replace( $urlParts['path'], $path, $url );

                        $return['status'] = self::purgeAll( $url );
                    }
                    break;

                default:
                    $return['status'] = 'unknown';
                    break;
            }
        }

        $return['nonce'] = wp_create_nonce( 'umich-cloudflare-nonce' );

        echo json_encode( $return );

        wp_die();
    }

    /**
     * Validate Cloudflare API Key
     *
     * @param string      $key Cloudflare API Key
     * @return array|bool true on valid api key, errors on failure
     */
    static private function _validateApiKey( $key )
    {
        $errors = [];

        self::$_settings['apikey'] = $key;

        $res = self::_callAPI( 'user/tokens/verify' );

        json_decode( $res );
        if( json_last_error() === JSON_ERROR_NONE ) {
            $res = json_decode( $res );

            if( $res->success == false ) {
                foreach( $res->errors as $error ) {
                    $errors[] = @$error->message ?: 'Unknown Error';
                }
            }
        }
        else {
            $errors[] = 'Invalid response from Cloudflare (apikey).';
        }

        return $errors ?: true;
    }

    /**
     * Validate zone
     *
     * @param string      $key Cloudflare Zone
     * @return array|bool true on valid zone, errors on failure
     */
    static private function _validateApiZone( $zone )
    {
        $errors = [];

        $res = self::_callAPI( 'zones/'. $zone );

        json_decode( $res );
        if( json_last_error() === JSON_ERROR_NONE ) {
            $res = json_decode( $res );

            if( $res->success == false ) {
                foreach( $res->errors as $error ) {
                    $errors[] = @$error->message ?: 'Unknown Error';
                }
            }
        }
        else {
            $errors[] = 'Invalid response from Cloudflare (zone).';
        }

        return $errors ?: true;
    }
}
UMCloudflare::init();
