<?php

class UMCloudflare_Pantheon
{
    static public function init()
    {
        if( class_exists( 'Pantheon_Cache' ) ) {
            add_filter( 'option_'. Pantheon_Cache::SLUG,         array( __CLASS__, 'overrideDefaultTTL' ) );
            add_filter( 'default_option_'. Pantheon_Cache::SLUG, array( __CLASS__, 'overrideDefaultTTL' ) );

            // Handle Admin bar cache clear actions
            add_action( 'wp_ajax_umcloudflare_clear', function(){
                $url = isset( $_POST['url'] ) ? $_POST['url'] : false;

                $pages = [];

                if( check_ajax_referer( 'umich-cloudflare-nonce', 'nonce', false ) ) {
                    switch( @$_POST['type'] ) {
                        case 'all':
                            // handled by the action (umich_cloudflare_purge_all)
                            // return self::_purge( true );
                            return true;
                            break;

                        case 'page':
                            return self::_purge( $url );
                            break;

                        case 'section':
                            $keys = [];

                            $path = @explode( '/', trim( parse_url( $url, PHP_URL_PATH ), '/' ), 2 )[0];

                            if( $path ) {
                                $keys = array_merge( $keys,
                                    self::_multisiteKeys( ["section-{$path}"] )
                                );
                            }

                            return self::_purgeKeys( $keys );
                            break;
                    }
                }
            });

            // purge part or whole cache
            add_action( 'umich_cloudflare_purge_all', function( $paths ){
                foreach( $paths as $path ) {
                    if( strpos( $path, 'http' ) !== 0 ) {
                        $path = 'https://'. $path;
                    }

                    if( parse_url( $path, PHP_URL_PATH ) == '/' ) {
                        $path = true;
                    }

                    self::_purge( $path );
                }
            });

            // Post Updates
            add_action( 'save_post',          array( __CLASS__, 'onPostUpdate' ), 10, 2 );
            add_action( 'before_delete_post', array( __CLASS__, 'onPostUpdate' ), 10, 2 );

            // Taxonomy Updates
            add_action( 'edited_term',     array( __CLASS__, 'onTermUpdate' ) );
            add_action( 'pre_delete_term', array( __CLASS__, 'onTermUpdate' ) );

            /** HEADER: Surrogate-Keys **/
            add_filter( 'pantheon_wp_main_query_surrogate_keys', function( $keys ){
                global $post;

                if( $post ) {
                    // section based purge support
                    $path = @explode( '/', trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ), 2 )[0];

                    if( $path ) {
                        $keys = array_merge( $keys,
                            self::_multisiteKeys( ["section-{$path}"] )
                        );
                    }
                }

                return $keys;
            });
            add_action( 'wp', function(){
                global $post;

                if( self::_isPantheonAdvancedActive() ) return;

                $keys = [];

                // stop, do not pass go
                if( is_admin() ) {
                    return;
                }

                if( is_post_type_archive() ) {
                    $postTypes = (array) get_query_var( 'post_types' );
                    foreach( $postTypes as $postType ) {
                        $keys[] = "archive-{$postType}";
                    }
                }
                else if( is_category() || is_tag() || is_tax() ) {
                    if( ($termID = get_queried_object_id()) ) {
                        $keys[] = "term-{$termID}";
                    }
                }

                $keys = apply_filters( 'pantheon_wp_main_query_surrogate_keys',    $keys );
                $keys = apply_filters( 'umich_cloudflare_pantheon_surrogate_keys', $keys );
                $keys = array_unique( $keys );

                // add header
                if( $keys ) {
                    $keys = self::_multisiteKeys( $keys );
                    @header( 'Surrogate-Key: '. implode( ' ', $keys ) );
                }
            });

            /** ADMIN **/
            add_action( 'umich_cloudflare_admin_default_ttl_notes', function( $settings ){
                echo '<br><em>This will override the <a href="https://github.com/pantheon-systems/pantheon-mu-plugin">Pantheon MU Plugin</a> Default TTL setting.</em>';
            });

            add_filter( 'umich_cloudflare_admin_form_settings', function( $settings ){
                $settings['ttl_static'] = false;

                return $settings;
            });

            // custom pantheon settings
            add_action( 'umich_cloudflare_admin_settings_page', function( $formSettings, $isNetwork ) {
                $formSettings['pantheon'] = [
                    'ccworkflow' => false
                ];

                if( $formSettings['apikey'] ) {
                    // check pantheon.yml for workflow
                    if( function_exists( 'yaml_parse_file' ) ) {
                        if( file_exists( ABSPATH .'pantheon.yml' ) ) {
                            $pYML = yaml_parse_file( ABSPATH .'pantheon.yml' );

                            if( isset( $pYML['workflows']['clear_cache']['after'] ) ) {
                                foreach( $pYML['workflows']['clear_cache']['after'] as $wf ) {
                                    if( isset( $wf['script'] ) && strpos( $wf['script'], 'umich-cloudflare/scripts/wpcli-purgeall.php' ) ) {
                                        $formSettings['pantheon']['ccworkflow'] = true;
                                    }
                                }
                            }
                        }
                    }

                    include __DIR__ . DIRECTORY_SEPARATOR .'admin.tpl';
                }
            }, 10, 2 );

            // save pantheon settings
            add_action( 'umich_cloudflare_admin_settings_save', function( $settings, $hasErrors ){
                if( $hasErrors ) {
                    return;
                }
            }, 10, 2 );
        }
    }

    /**
     * Override Pantheon MU default_ttl with current request ttl
     */
    static public function overrideDefaultTTL( $settings )
    {
        if( !is_admin() ) {
            $requestTTL = UMCloudflare::getRequestTTL();

            if( is_numeric( $requestTTL ) ) {
                $settings['default_ttl'] = $requestTTL;
            }
        }

        return $settings;
    }

    static public function onPostUpdate( $pID, $post )
    {
        if( self::_isPantheonAdvancedActive() ) return;

        // Stop the script when doing autosave
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        $purgeKeys = [];

        if( $post ) {
            $purgeKeys[] = "archive-{$post->post_type}";

            foreach( get_object_taxonomies( $post ) as $tax ) {
                $purgeKeys[] = "term-{$tax->name}";

                foreach( (get_the_terms( $pID, $tax ) ?: array()) as $term ) {
                    $purgeKeys[] = "term-{$term->term_id}";
                }
            }
        }

        if( $purgeKeys ) {
            self::_purgeKeys( $purgeKeys );
        }
    }

    static public function onTermUpdate( $termID )
    {
        if( self::_isPantheonAdvancedActive() ) return;

        self::_purgeKeys( [ "term-{$termID}" ] );
    }

    /**
     * Call pantheon cache purge
     *
     * @param  string|bool $url URL to be purged, true for everything
     * @return bool|WP_Error
     */
    static private function _purge( $url )
    {
        if( $url === true ) {
            try {
                if( function_exists( 'pantheon_clear_edge_all' ) ){
                    pantheon_clear_edge_all();
                }
            } catch( Exception $e ) {
                return new WP_Error( 'umich_cloudflare_pantheon_purge_all', $e->getMessage() );
            }
        }
        else {
            try {
                if( function_exists( 'pantheon_clear_edge_paths' ) ){
                    if( ($path = parse_url( $url, PHP_URL_PATH )) ) {
                        pantheon_clear_edge_paths( [ $path ] );
                    }
                }
            } catch( Exception $e ) {
                return new WP_Error( 'umich_cloudflare_pantheon_purge_paths', $e->getMessage() );
            }
        }

        return true;
    }

    /** 
     * Call Pantheon key purge
     *
     * @param  string|array $keys Key(s) to be purged
     * @return bool|WP_Error
     */
    static private function _purgeKeys( $keys )
    {
        if( !$keys ) {
            return;
        }

        $keys = (array) $keys;

        $keys = self::_multisiteKeys( $keys );

        try {
            if( function_exists( 'pantheon_clear_edge_keys' ) ){
                pantheon_clear_edge_keys( $keys );
            }
        } catch( Exception $e ) {
            return new WP_Error( 'umich_cloudflare_pantheon_purge_keys', $e->getMessage() );
        }
    }

    /**
     * Convert keys to multisite specific versions
     *
     * @param string|array $keys Keys to be sent
     * @return array
     */
    static private function _multisiteKeys( $keys )
    {
        $keys = (array) $keys;

        if( $keys && is_multisite() ) {
            $keys = array_map( function( $key ){
                return 'blog-'. get_current_blog_id() .'-'. $key;
            }, $keys );
        }

        return $keys;
    }

    /**
     * Checks if Pantheon Advanced Page Cache plugin is enabled
     *
     * @return bool
     */
    static private function _isPantheonAdvancedActive()
    {
        return is_plugin_active( 'pantheon-advanced-page-cache/pantheon-advanced-page-cache.php' );
    }
}
UMCloudflare_Pantheon::init();
