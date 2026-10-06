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

/**
 * Generate CSP nonce.
 *
 * @param array $params
 * @param Smarty $smarty
 * @return string
 * @throws \Random\RandomException
 */
function smarty_function_sugar_nonce($params, &$smarty)
{
    return Nonce::create();
}
