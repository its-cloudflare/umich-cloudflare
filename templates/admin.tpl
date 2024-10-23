<div class="wrap">
    <h2>U-M: CloudFlare Cache Settings</h2>
    <form method="post" action="<?=($isNetwork ? 'settings.php' : 'options-general.php');?>?page=<?=$_GET['page'];?>">
        <?php wp_nonce_field( 'umich-cloudflare', 'umich_cloudflare_nonce' ); ?>

        <table class="form-table">
            <?php if( $umCFFormSettings['apikey'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-apikey">API Key</label></th>
                <td>
                    <input type="text" id="umcf-apikey" name="umich_cloudflare_settings[apikey]" value="<?=esc_attr( $umCFSettings['apikey'] );?>" placeholder="Enter API Key" class="regular-text" required="required" />
                </td>
            </tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['zone'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-zone">Zone</label></th>
                <td>
                    <select id="umcf-zone" name="umich_cloudflare_settings[zone]" class="regular-text" required="required">
                        <option value="">Select a Zone</option>
                        <?php foreach( $umCFZones as $zone ): ?>
                        <option value="<?=esc_attr( $zone['id'] );?>"<?=($zone['id'] == $umCFSettings['zone'] ? ' selected="selected"' : null);?>><?=$zone['name'];?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['ttl'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-ttl">Default Page <abbr title="Time to live">TTL</abbr></label></th>
                <td>
                    <input type="number" id="umcf-ttl" name="umich_cloudflare_settings[ttl]" value="<?=esc_attr( $umCFSettings['ttl'] );?>" placeholder="Enter Time in Seconds" class="regular-text" aria-describedby="umcf-ttl-description" />
                    <br/>
                    <p class="description" id="umcf-ttl-description">Max amount of time (in seconds) to hold page in the <abbr title="Content Delivery Network">CDN</abbr> cache. Default <?=$umCFSettings['default_ttl'];?> seconds.<?php do_action( 'umich_cloudflare_admin_default_ttl_notes', $umCFSettings );?></p>
                </td>
            </tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['ttl_browser'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-ttl_browser">Default Browser <abbr title="Time to live">TTL</abbr></label></th>
                <td>
                    <input type="number" id="umcf-ttl_browser" name="umich_cloudflare_settings[ttl_browser]" value="<?=esc_attr( $umCFSettings['ttl_browser'] );?>" placeholder="Enter Time in Seconds" class="regular-text" aria-describedby="umcf-ttl_browser-description" />
                    <br/>
                    <p class="description" id="umcf-ttl_browser-description">Max amount of time (in seconds) to hold page in the browsers cache. Default <?=$umCFSettings['default_ttl_browser'];?> seconds.<br/>Purging the cache will not affect content cached in a users browser.</p>
                </td>
            </tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['ttl_static'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-ttl_static">Default Static <abbr title="Time to live">TTL</abbr></label></th>
                <td>
                    <input type="number" id="umcf-ttl_static" name="umich_cloudflare_settings[ttl_static]" value="<?=esc_attr( $umCFSettings['ttl_static'] );?>" placeholder="Enter Time in Seconds" class="regular-text" aria-describedby="umcf-ttl_static-description" />
                    <br/>
                    <p class="description" id="umcf-ttl_static-description">For files such as CSS, JS, Images, Documents, Media Uploads, etc.<br/>Max amount of time (in seconds) to hold static asset in cache. Default <?=$umCFSettings['default_ttl_static'];?> seconds.</p>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <?php do_action( 'umich_cloudflare_admin_settings_page', $umCFFormSettings, $isNetwork ); ?>

        <?php submit_button(); ?>
    </form>
</div>

<script type="text/javascript">
(function($){
    $(document).ready(function(){
        let zoneSelect  = $('select[name="umich_cloudflare_settings\[zone\]"]');

        $('input[name="umich_cloudflare_settings\[apikey\]"]').on('change', function(){
            // trim whitespace
            $(this).val( $.trim( $(this).val() ) );

            let apiKey = $(this).val();

            // remove zones
            zoneSelect.find('option[value!=""]').remove();

            // get zone list
            if( apiKey.length ) {
                let params = {
                    nonce : umCFNonce,
                    action: 'umcloudflare_zones',
                    apikey: apiKey
                };

                // add loading indicator
                zoneSelect.after(
                    '<img class="umcf-loading-status" src="'+ umCFPlugin +'/assets/working-dark.svg" title="Loading available zones from Couldflare" style="vertical-align: middle;" />'
                );

                $.post( ajaxurl.replace( /^https?:/, window.location.protocol ), params, function( response ){
                    if( response.nonce.length ) {
                        umCFNonce = response.nonce;
                    }

                    // add available zones
                    if( response.status == 'success' ) {
                        response.zones.forEach((zone) => {
                            zoneSelect.append('<option value="'+ zone.id +'">'+ zone.name +'</option>');
                        });

                        if( zoneSelect.find('option[value!=""]').length == 1 ) {
                            zoneSelect.val(
                                zoneSelect.find('option[value!=""]').attr('value')
                            );
                        }
                    }
                    else {
                        alert( '[ERROR] '+ response.message );
                    }

                    // remove loading indicator
                    zoneSelect.parent().find('img.umcf-loading-status').remove();
                }, 'json' );
            }
        });
    });
}(jQuery));
</script>
