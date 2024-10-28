<table class="form-table">
    <tr><th colspan="2">
        <hr/>
        <h3 style="margin-bottom: 0">Pantheon Settings</h3>
    </th></tr>
    <tr valign="top">
        <th scope="row"><label for="umcf-apikey">Cache Clear Workflow</label></th>
        <td>
            <?php if( $formSettings['pantheon']['ccworkflow'] ): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-left-width: 4px; border-left-color: #00a32a; padding: 11px 15px;">
                <p>The workflow has been installed.</p>
            </div>
            <?php endif; ?>
            <p class="description">To clear cloudflare cache from the pantheon dashboard add the following workflow to your pantheon.yml file in the document root of your site.</p>
            <code style="display: block; white-space: preserve; padding: 1em; border-radius: 3px; line-height: 1.5;"><?php include __DIR__ . DIRECTORY_SEPARATOR .'_pantheon.yml'; ?></code>
            <p>You will also need to set your cloudflare domain e.g. [SITE].umich.edu as the primary domain within pantheon. This can be done via the pantheon dashboard or using terminus: <code style="display: block; border-radius: 3px; line-height: 1.5;">terminus domain:primary:add &lt;site&gt;.&lt;env&gt; [SITE].umich.edu</code></p>
        </td>
    </tr>
</table>
