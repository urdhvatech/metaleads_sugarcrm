{*
/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */
*}

<div id="pdf-preview-container"></div>
<script nonce="{sugar_nonce}">
    (function () {
        var html = {$body_html|@json_encode};
        if (html) {

            // First decode HTML entities
            var tempDiv = document.createElement("div");
            tempDiv.innerHTML = html;
            html = tempDiv.textContent || tempDiv.innerText || "";

            var iframe = document.createElement('iframe');
            iframe.style.border = '0';
            iframe.style.width = '100%';
            iframe.style.height = '400px';
            iframe.sandbox = 'allow-same-origin';
            iframe.srcdoc = html;

            document.getElementById('pdf-preview-container').appendChild(iframe);
        } else {
            console.log("No HTML content found");
        }
    })();
</script>
