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


{include file="modules/DynamicFields/templates/Fields/Forms/coreTop.tpl"}
<tr>
    <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_DEFAULT_VALUE"}:</td>
    <td>
        {if $hideLevel < 5}
            <input type="text" id="default" name="default" value="{$vardef.default|sugar_escape:'html':'UTF-8'}">
            <script nonce="{sugar_nonce}">
                addToValidate('popup_form', 'default', 'float', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_DEFAULT_VALUE"}');
                formWithPrecision = new addToValidatePrecision('popup_form_id', 'default', 'precision');
            </script>
        {else}
            <input type="hidden" name="default" id="default" value="{$vardef.default|sugar_escape:'html':'UTF-8'}">
            {$vardef.default|sugar_escape:'html':'UTF-8'}
        {/if}
    </td>
</tr>
<tr>
    <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MAX_SIZE"}:</td>
    <td>
        {if $hideLevel < 5}
        <input type="text" name="len" value="{$vardef.len|sugar_escape:'html':'UTF-8'|default:18}"
               data-onchange-{sugar_nonce}="changeMaxLength(document.getElementById('default'),this.value);">
    </td>
    <script nonce="{sugar_nonce}">
        addToValidate('popup_form', 'len', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MAX_SIZE"}');

        //warn user if default value is greater than max length
        function changeMaxLength(field, length) {ldelim}
            field.maxLength = parseInt(length);

            //clear previous max length validation
            removeFromValidate('popup_form', field.name);
            //add new max length validation based on specified value
            addToValidateMaxLength('popup_form', field.name, 'float', false, field.maxLength, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_DEFAULT_VALUE"}');
            {rdelim}
    </script>
    {else}
    <input type="hidden" name="len" value="{$vardef.len|sugar_escape:'html':'UTF-8'}">{$vardef.len|sugar_escape:'html':'UTF-8'}
    {/if}
    </td>
</tr>
{if $range_search_option_enabled}
    <tr>
        <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_ENABLE_RANGE_SEARCH"}:</td>
        <td>
            <input type="checkbox" name="enable_range_search" value="1" {if !empty($vardef.enable_range_search) }checked{/if} {if $hideLevel > 5}disabled{/if} />
            {if $hideLevel > 5}
                <input type="hidden" name="enable_range_search" value="{$vardef.enable_range_search|sugar_escape:'html':'UTF-8'}">
            {/if}
        </td>
    </tr>
{/if}
<tr>
    <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_PRECISION"}:</td>
    <td>
        {if $hideLevel < 5}
            <input type="text" id="precision" name="precision" value="{$vardef.precision|sugar_escape:'html':'UTF-8'|default:0}">
            <script nonce="{sugar_nonce}">addToValidate('popup_form', 'ext1', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_PRECISION"}');</script>
        {else}
            <input type="hidden" name="precision" value="{$vardef.precision|sugar_escape:'html':'UTF-8'}">
            {$vardef.precision|sugar_escape:'html':'UTF-8'}
        {/if}
    </td>
</tr>

{include file="modules/DynamicFields/templates/Fields/Forms/coreBottom.tpl"}
