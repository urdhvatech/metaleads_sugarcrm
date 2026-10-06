<?php
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
use Sugarcrm\Sugarcrm\CSP\Nonce;

function smarty_function_sugar_html_options($params, Smarty_Internal_Template $template)
{
    foreach ($params as $name => $value) {
        if (str_starts_with($name, 'data-on')) {
            $params[$name] = $name . '-' . Nonce::create();
            $params[$name . '-' . Nonce::create()] = $value;
            unset($params[$name]);
        }
        if ($name === 'selected' && is_null($value)) {
            unset($params[$name]);
        }
    }
    if (!function_exists('smarty_function_html_options')) {
        require 'vendor/smarty/smarty/libs/plugins/function.html_options.php';
    }
    return smarty_function_html_options($params, $template);
}
