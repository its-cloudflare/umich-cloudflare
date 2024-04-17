<?php

namespace Umich\GithubUpdater {
    if( !class_exists( '\Umich\GithubUpdater\Init' ) ) {
        class Init
        {
            static private $_version = 0;

            public function __construct( $options = array() )
            {
                $class = '\Umich\GithubUpdater\v'. str_replace( '.', 'd', self::$_version ) .'\Actions';

                new $class( $options );
            }

            static public function load( $version )
            {
                if( version_compare( $version, self::$_version ) > 0 ) {
                    self::$_version = $version;
                }
            }
        }
    }
}


namespace Umich\GithubUpdater\v1d0d0 {
    class Actions
    {
        CONST VERSION = '1.0.0';

        private $_githubBase = [
            'main' => 'https://github.com/',
            'api'  => 'https://api.github.com/repos/',
            'raw'  => 'https://raw.githubusercontent.com/',
        ];

        private $_requiredOptions = [
            'repo',
            'slug'
        ];

        private $_options = [
            'repo'        => '',
            'slug'        => '',
            'config'      => 'wordpress.json',
            'changelog'   => 'CHANGELOG',
            'description' => 'README.md',
        ];

        private $_data = [];

        public function __construct( $options )
        {
            // dynamic defaults
            $this->_options['slug'] = plugin_basename( __FILE__ );

            // remove keys not used
            $options = array_intersect_key( $options, $this->_options );

            // override defaults
            $this->_options = array_merge(
                $this->_options, $options
            );

            // check for required options
            $invalidOptions = [];
            foreach( $this->_requiredOptions as $key ) {
                if( empty( $this->_options[ $key ] ) ) {
                    $invalidOptions[] = $key;
                }
            }

            if( $invalidOptions && function_exists( '\_doing_it_wrong' ) ) {
                \_doing_it_wrong(
                    '\Umich\GithubUpdater\Init',
                    'Missing required options: '. implode( ', ', $invalidOptions ) .'.',
                    self::VERSION
                );
            }

            /** WORDPRESS HOOKS **/
            // Update Check
            add_filter( 'update_plugins_github.com', function( $update, $pluginData, $pluginFile ){
                if( $pluginFile == $this->_options['slug'] ) {
                    // get latest release
                    $release = $this->_callAPI( 'releases/latest', 'gh_release_latest' );

                    if( $release ) {
                        $update = [
                            'slug'    => $this->_options['slug'],
                            'version' => $release->tag_name,
                            'url'     => $this->_githubBase['main'] . $this->_options['repo'] .'/releases/latest',
                            'package' => $release->zipball_url
                        ];
                    }
                }

                return $update;
            }, 10, 3 );

            // Plugin Details
            add_filter( 'plugins_api', function( $return, $action, $args ){
                if( !isset( $args->slug ) || ($args->slug != $this->_options['slug']) ) {
                    return false;
                }

                $release    = $this->_callAPI( 'releases/latest', 'gh_release_latest' );
                $repoInfo   = $this->_callAPI( '', 'gh_info' );
                $pluginData = $this->_getPluginData();

                if( $release && $repoInfo && $pluginData ) {
                    $wpConfig = $this->_callAPI( "contents/{$this->_options['config']}?ref={$release->tag_name}");

                    if( $wpConfig ) {
                        foreach( [ 'description', 'changelog' ] as $key ) {
                            if( isset( $wpConfig->{$key} ) ) {
                                $this->_options[ $key ] = $wpConfig->{$key};
                            }
                        }
                    }

                    $return = (object) [
                        'slug'           => $args->slug,
                        'name'           => $pluginData['Name'],
                        'version'        => $release->tag_name,
                        'requires'       => '',
                        'tested'         => '',
                        'requires_php'   => '',
                        'last_updated'   => date( 'Y-m-d h:ia e', strtotime( $release->published_at ) ),
                        'author'         => $pluginData['Author'],
                        'homepage'       => $pluginData['PluginURI'],
                        'sections'       => [ // as html
                            'description' => $this->_getMarkdown(
                                $this->_options['description'] .'?ref='. $release->tag_name,
                                $repoInfo->description
                            ),
                            'changelog'   => $this->_getMarkdown(
                                $this->_options['changelog'] .'?ref='. $release->tag_name,
                                $release->body
                            ),
                        ],
                        'download_link'  => $release->zipball_url, // zip file
                        'banners'        => [
                            'low'  => '', // image link (750x250)
                            'high' => '', // image link large (1500x500)
                        ]
                    ];

                    if( $wpConfig ) {
                        foreach( array( 'requires', 'tested', 'requires_php', 'banners:low', 'banners:high' ) as $key ) {
                            if( isset( $wpConfig->{$key} ) ) {
                                if( strpos( $key, ':' ) !== false ) {
                                    $kParts = explode( ':', $key, 2 );
                                    $return->{$kParts[0]}[ $kParts[1] ] = $wpConfig->{$key};
                                }
                                else {
                                    $return->{$key} = $wpConfig->{$key};
                                }
                            }
                        }
                    }

                    foreach( $return->banners as $key => $img ) {
                        if( strpos( $img, '/' ) === 0 ) {
                            $return->banners[ $key ] = plugins_url( $img, dirname( __FILE__ ) );
                        }
                    }
                }

                return $return;
            }, 10, 3 );
        }

        private function _callAPI( $endpoint, $key = null, $method = 'GET', $data = null )
        {
            $key = false;

            if( $key && isset( $this->_data[ $key ] ) ) {
                return $this->_data[ $key ];
            }

            $params = [
                'timeout' => 5,
                'method'  => $method,
                'headers' => []
            ];

            if( $data ) {
                $params['body']    = is_string( $data ) ? $data : json_encode( $data );
                $params['headers'] = array_merge( $params['headers'], [
                    'Accept'       => 'application/vnd.github+json',
                    'Content-Type' => 'application/json',
                ]);
            }

            $data = false;

            if( $key ) {
                $data = get_site_transient( $this->_getTransient( $key ) );
            }

            if( !$data ) {
                $res = wp_remote_request(
                    rtrim( "{$this->_githubBase['api']}{$this->_options['repo']}/{$endpoint}", '/' ),
                    $params
                );

                if( is_wp_error( $res ) || (@$res['response']['code'] != 200) ) {
                    return false;
                }

                $data = json_decode( $res['body'] );

                if( $key ) {
                    set_site_transient(
                        $this->_getTransient( $key ),
                        $data,
                        60 * 60 * 6 // 6 hours
                    );

                    $this->_data[ $key ] = $data;
                }
            }

            return $data;
        }

        private function _getTransient( $key )
        {
            return substr( $this->_options['repo'], 0, 100 ) .'-'. $key;
        }

        private function _getPluginData()
        {
            // include_once ABSPATH.'/wp-admin/includes/plugin.php';
            return get_plugin_data( WP_PLUGIN_DIR .'/'. $this->_options['slug'] );
        }

        private function _getMarkdown( $file, $default = '' )
        {
            if( ($res = $this->_callAPI( 'contents/'. $file )) && isset( $res->content ) ) {
                $content = base64_decode( $res->content );

                $mRes = wp_remote_post(
                    'https://api.github.com/markdown', [
                        'body'    => json_encode([ 'text' => $content ]),
                        'timeout' => 5,
                        'headers' => [
                            'Accept'       => 'application/vnd.github+json',
                            'Content-Type' => 'application/json',
                        ]
                    ]
                );

                if( !is_wp_error( $mRes ) && @$mRes['response']['code'] == 200 ) {
                    return $mRes['body'];
                }
            }

            return $default;
        }
    }

    \Umich\GithubUpdater\Init::load( Actions::VERSION );
}