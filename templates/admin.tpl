<div class="wrap">
    <h2>U-M: CloudFlare Cache Settings</h2>
    <form method="post" action="<?=($isNetwork ? 'settings.php' : 'options-general.php');?>?page=<?=$_GET['page'];?>">
        <?php wp_nonce_field( 'umich-cloudflare', 'umich_cloudflare_nonce' ); ?>

        <table class="form-table">
            <?php if( $umCFFormSettings['apikey'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-apikey">Cloudflare API Key</label></th>
                <td>
                    <input type="text" id="umcf-apikey" name="umich_cloudflare_settings[apikey]" value="<?=esc_attr( $umCFSettings['apikey'] );?>" placeholder="Enter API Key" class="regular-text" required="required" autocomplete="off" />
                </td>
            </tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['zone'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-zone">Cloudflare Zone</label></th>
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

            <?php if( ($umCFFormSettings['apikey'] || $umCFFormSettings['zone']) && ($umCFFormSettings['ttl'] || $umCFFormSettings['ttl_browser']) ): ?>
            <tr><th colspan="2">
                <hr/>
                <h3 style="margin-bottom: 0">Content Defaults</h3>
                <p>For any non-media content (pages, posts, etc) managed within wordpress.</p>
            </th></tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['ttl'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-ttl">Default <abbr title="Time to live">TTL</abbr></label></th>
                <td>
                    <input type="number" id="umcf-ttl" name="umich_cloudflare_settings[ttl]" value="<?=esc_attr( $umCFSettings['ttl'] );?>" placeholder="Enter Time in Seconds" class="regular-text" aria-describedby="umcf-ttl-description" />
                    <br/>
                    <p class="description" id="umcf-ttl-description">Max amount of time (in seconds) to hold page, post, etc in the <abbr title="Content Delivery Network">CDN</abbr> cache. Default <em><?=$umCFSettings['default_ttl'];?></em> seconds.<?php do_action( 'umich_cloudflare_admin_default_ttl_notes', $umCFSettings );?></p>
                </td>
            </tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['ttl_browser'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-ttl_browser">Default Browser <abbr title="Time to live">TTL</abbr></label></th>
                <td>
                    <input type="number" id="umcf-ttl_browser" name="umich_cloudflare_settings[ttl_browser]" value="<?=esc_attr( $umCFSettings['ttl_browser'] );?>" placeholder="Enter Time in Seconds" class="regular-text" aria-describedby="umcf-ttl_browser-description" />
                    <br/>
                    <p class="description" id="umcf-ttl_browser-description">Max amount of time (in seconds) to hold page, post, etc in the browsers cache. Default <em><?=$umCFSettings['default_ttl_browser'];?></em> seconds.<br/>Purging the cache will not affect content cached in a users browser.</p>
                </td>
            </tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['ttl_static'] || $umCFFormSettings['ttl_static_browser'] ): ?>
            <tr><th colspan="2">
                <hr/>
                <h3 style="margin-bottom: 0">Static Files</h3>
                <p>For files such as CSS, JS, Images, Documents, Media Uploads, etc.</p>
            </th></tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['ttl_static'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-ttl_static">Static <abbr title="Time to live">TTL</abbr></label></th>
                <td>
                    <input type="number" id="umcf-ttl_static" name="umich_cloudflare_settings[ttl_static]" value="<?=esc_attr( $umCFSettings['ttl_static'] );?>" placeholder="Enter Time in Seconds" class="regular-text" aria-describedby="umcf-ttl_static-description" />
                    <br/>
                    <p class="description" id="umcf-ttl_static-description">Max amount of time (in seconds) to hold static asset in cache. Default <em><?=$umCFSettings['default_ttl_static'];?></em> seconds.</p>
                </td>
            </tr>
            <?php endif; ?>

            <?php if( $umCFFormSettings['ttl_static_browser'] ): ?>
            <tr valign="top">
                <th scope="row"><label for="umcf-ttl_static_browser">Static Browser <abbr title="Time to live">TTL</abbr></label></th>
                <td>
                    <input type="number" id="umcf-ttl_static_browser" name="umich_cloudflare_settings[ttl_static_browser]" value="<?=esc_attr( $umCFSettings['ttl_static_browser'] );?>" placeholder="Enter Time in Seconds" class="regular-text" aria-describedby="umcf-ttl_static_browser-description" />
                    <br/>
                    <p class="description" id="umcf-ttl_static_browser-description">Max amount of time (in seconds) to hold static asset in the browsers cache. Default <em><?=$umCFSettings['default_ttl_static_browser'];?></em> seconds.<br/>Changing this to <em>0</em> will force the browser to request the file on every pageload.<br/>Purging the cache will not affect content cached in a users browser.</p>
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
