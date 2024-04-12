<?php

class UMCloudflare_Override
{
    static public function init()
    {
        add_action( 'init', function(){
            if( UMCloudflare::isConfigured() ) {
                add_action( 'add_meta_boxes', array( __CLASS__, 'metaBoxes' ) );
                add_action( 'save_post',      array( __CLASS__, 'metaDetailsSave' ) );
            }
        }, 11 );
    }

    static public function metaBoxes()
    {
        if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
            add_meta_box(
                'umcloudflare-override',
                __( 'Cloudflare Cache Settings', 'umcloudflare' ),
                array( __CLASS__, 'metaCloudflareAdmin' ),
                null,
                'side'
            );
        }
    }

    static public function metaCloudflareAdmin()
    {
        wp_nonce_field( 'umcloudflare-settings_nonce', 'umcloudflare-settings_nonce' );

        echo '
        <style type="text/css">
        #umcloudflare-override .notes {
            display: block;
            padding-top: 3px;
            font-style: italic;
            font-size: .9em;
            line-height: 1.2;
        }
        #umcloudflare-override label {
            font-weight: bold;
        }
        #umcloudflare-override input {
            border-radius: 2px;
        }
        #umcloudflare-override input[type="checkbox"] {
            margin-left: 5px;
        }
        #umcloudflare-override input[type="text"],
        #umcloudflare-override input[type="number"] {
            width: 100%;
            margin-top: 5px;
        }
        </style>';

        self::_checkbox( 'disable', 'Disable Cache', 1, null, 'Check to NOT cache this page.' );
        self::_input( 'ttl', 'Cache Lifetime (TTL)', 'number', null, 'Max amount of time (in seconds) to hold page in cache.' );
    }


    static public function metaDetailsSave( $pID )
    {
        // Stop the script when doing autosave
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        if( !current_user_can( 'administrator' ) && !current_user_can( 'editor' ) ) {
            return;
        }

        // Verify the nonce. If insn't there, stop the script
        if( isset( $_POST['umcloudflare-settings_nonce'] ) && wp_verify_nonce( $_POST['umcloudflare-settings_nonce'], 'umcloudflare-settings_nonce' ) ) {

            $metaVars = array(
                'disable',
                'ttl'
            );

            foreach( $metaVars as $var ) {
                $var = 'umcloudflare_'. $var;

                if( isset( $_POST[ $var ] ) ) {
                    if( $var == 'umcloudflare_ttl' ) {
                        if( is_numeric( $_POST[ $var ] ) ) {
                            $_POST[ $var ] = (int) $_POST[ $var ];
                        }
                        else {
                            unset( $_POST[ $var ] );
                        }
                    }
                }

                if( isset( $_POST[ $var ] ) && $_POST[ $var ] ) {
                    update_post_meta(
                        $pID, $var, esc_attr( $_POST[ $var ] )
                    );
                }
                else {
                    delete_post_meta( $pID, $var );
                }
            }
        }
    }


    static private function _input( $key, $name, $type = 'text', $value = null, $notes = null )
    {
        $key = 'umcloudflare_'. $key;

        if( $_POST && isset( $_POST[ $key ] ) ) {
            $value = $_POST[ $key ];
        }
        else if( !$value && isset( $_GET['post'] ) ) {
            $value = get_post_meta( $_GET['post'], $key, true );
        }

        echo '
        <p>
            <label for="'. $key .'">'. __( $name, 'cloudflare' ) .'</label>
            <input type="number" id="'. $key .'" name="'. $key .'" value="'. $value .'" />
            '. ($notes ? '<spane class="notes">'. $notes .'</span>' : null) .'
        </p>
        ';
    }

    static private function _checkbox( $key, $name, $value = null, $checked = null, $notes = null )
    {
        $key = 'umcloudflare_'. $key;

        $currValue = null;
        if( $_POST && isset( $_POST[ $key ] ) ) {
            $currValue = $_POST[ $key ];
        }
        else if( isset( $_GET['post'] ) ) {
            $currValue = get_post_meta( $_GET['post'], $key, true );
        }

        if( !is_null( $currValue ) ) {
            if( $currValue == $value ) {
                $checked = true;
            }
        }

        echo '
        <p>
            <label for="'. $key .'">'. __( $name, 'umcloudflare' ) .'</label>
            <input type="checkbox" id="'. $key .'" name="'. $key .'" value="'. $value .'" '.( $checked ? ' checked="checked"' : null ).' />
            '. ($notes ? '<span class="notes">'. $notes .'</span>' : null) .'
        </p>
        ';
    }
}

UMCloudflare_Override::init();
