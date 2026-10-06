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
/**
 * Escapes a string preventing double escaping
 * @param $string
 * @param $esc_type
 * @param $char_set
 * @param $double_encode
 * @return string
 */
function smarty_modifier_sugar_escape($string, $esc_type = 'html', $char_set = null, $double_encode = true)
{
    do {
        $oldData = $string;
        $string = htmlspecialchars_decode($string, ENT_QUOTES);
    } while ($string !== $oldData);
    require_once 'vendor/smarty/smarty/libs/plugins/modifier.escape.php';
    return smarty_modifier_escape($string, $esc_type, $char_set, $double_encode);
}
