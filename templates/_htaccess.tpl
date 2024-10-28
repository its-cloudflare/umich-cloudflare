<IfModule mod_headers.c>
    <FilesMatch "\.(css|js|xml)$">
        Header set Cache-Control "max-age={TTL_STATIC_BROWSER}, s-maxage={TTL_STATIC}"
    </FilesMatch>

    <FilesMatch "\.(wav|wmv||ogg|ogv|webm|mp3|mp4|m4v|mov|avi|flv|aac)$">
        Header set Cache-Control "max-age={TTL_STATIC_BROWSER}, s-maxage={TTL_STATIC}"
    </FilesMatch>

    <FilesMatch "\.(pdf|txt|docx?|xlsx?|pptx?|rtf)$">
        Header set Cache-Control "max-age={TTL_STATIC_BROWSER}, s-maxage={TTL_STATIC}"
    </FilesMatch>

    <FilesMatch "\.(zip|tgz|tbz|gz|tar|rar|bz2)$">
        Header set Cache-Control "max-age={TTL_STATIC_BROWSER}, s-maxage={TTL_STATIC}"
    </FilesMatch>

    <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|svgz|ico|bmp)$">
        Header set Cache-Control "max-age={TTL_STATIC_BROWSER}, s-maxage={TTL_STATIC}"
    </FilesMatch>

    <FilesMatch "\.(ttf|otf|woff|woff2|eot)$">
        Header set Cache-Control "max-age={TTL_STATIC_BROWSER}, s-maxage={TTL_STATIC}"
    </FilesMatch>
</IfModule>
