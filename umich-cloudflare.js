let umCloudflareTimer     = false;
let umCloudflareStatusImg = false;
function umCloudflarePurge( type ) {
    let umCFData = {
        nonce : umCFNonce,
        action: 'umcloudflare_clear',
        type  : type,
        url   : window.location.href
    };

    if( umCloudflareTimer ) {
        clearTimeout( umCloudflareTimer );
    }

    if( umCloudflareStatusImg === false ) {
        umCloudflareStatusImg = jQuery('#wp-admin-bar-umich-cloudflare-root > *:first-child > img');
    }

    // show working status icon
    umCloudflareStatusImg.attr( 'src',
        umCloudflareStatusImg.attr('src')
            .replace(/(error|success)\.svg/, 'working.svg')
    ).css({
        visibility: 'visible',
        display   : 'inline-block',
        opacity   : ''
    });

    // Send purge request
    jQuery.post( ajaxurl.replace( /^https?:/, window.location.protocol ), umCFData, function( response ){
        if( response.nonce.length ) {
            umCFNonce = response.nonce;
        }

        let resStatus = 'error';

        // valid request
        if( response.status === true ) {
            resStatus = 'success';
        }

        // change status icon
        umCloudflareStatusImg.css({
            display   : 'none',
            visibility: 'hidden'
        }).attr( 'src',
            umCloudflareStatusImg.attr('src').replace('working.svg', resStatus +'.svg')
        ).css({
            display   : 'inline-block',
            visibility: 'visible'
        }).fadeIn();

        // set timer to clear status icon
        umCloudflareTimer = setTimeout(function(){
            umCloudflareStatusImg.fadeOut(function(){
                umCloudflareStatusImg.css({
                    visibility: 'hidden',
                    display   : 'inline-block',
                    opacity   : ''
                }).attr( 'src',
                    umCloudflareStatusImg.attr('src')
                        .replace(/(error|success)\.svg/, 'working.svg')
                );
            });
        }, 5000 );

    }, 'json' );

    return false;
}
