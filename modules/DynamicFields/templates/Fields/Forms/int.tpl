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
<script nonce="{sugar_nonce}">
    formsWithFieldLogic = null;
</script>

{include file="modules/DynamicFields/templates/Fields/Forms/coreTop.tpl"}
<tr>
    <td class='mbLBL'>{sugar_translate module="DynamicFields" label="COLUMN_TITLE_DEFAULT_VALUE"}:</td>
    <td>
        {if $hideLevel < 5}
            <input type="text" name="default" id="int_default" value="{$vardef.default|sugar_escape:'html':'UTF-8'}">
        {if isset($field_range_value)}
            <script nonce="{sugar_nonce}">addToValidateRange('popup_form', 'default', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_DEFAULT_VALUE"}', {$field_range_value.min|sugar_escape:'javascript'}, {$field_range_value.max|sugar_escape:'javascript'});</script>
        {else}
            <script nonce="{sugar_nonce}">addToValidate('popup_form', 'default', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_DEFAULT_VALUE"}');</script>
        {/if}
        {else}
            <input type="hidden" name="default" id="int_default" value="{$vardef.default|sugar_escape:'html':'UTF-8'}">
            {$vardef.default|sugar_escape:'html':'UTF-8'}
        {/if}
    </td>
</tr>
<tr>
    <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MIN_VALUE"}:</td>
    <td>
        {if $hideLevel < 5}
            <input type='text' name='min' id='int_min' value='{$vardef.validation.min|sugar_escape:'html':'UTF-8'}'>
        {if isset($field_range_value)}
            <script nonce="{sugar_nonce}">addToValidateRange('popup_form', 'min', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MIN_VALUE"}', {$field_range_value.min|sugar_escape:'javascript'}, {$field_range_value.max|sugar_escape:'javascript'});</script>
        {else}
            <script nonce="{sugar_nonce}">addToValidate('popup_form', 'min', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MIN_VALUE"}');</script>
        {/if}
        {else}
            <input type="hidden" name="min" id="int_min" value="{$vardef.validation.min|sugar_escape:'html':'UTF-8'}">
            {$vardef.range.min|sugar_escape:'html':'UTF-8'}
        {/if}
    </td>
</tr>
<tr>
    <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MAX_VALUE"}:</td>
    <td>
        {if $hideLevel < 5}
            <input type="text" name="max" id="int_max" value="{$vardef.validation.max|sugar_escape:'html':'UTF-8'}">
        {if isset($field_range_value)}
            <script nonce="{sugar_nonce}">addToValidateRange('popup_form', 'max', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MAX_VALUE"}', {$field_range_value.min|sugar_escape:'javascript'}, {$field_range_value.max|sugar_escape:'javascript'});</script>
        {else}
            <script nonce="{sugar_nonce}">addToValidate('popup_form', 'max', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MAX_VALUE"}');</script>
        {/if}
        {else}
            <input type="hidden" name="max" id="int_max" value="{$vardef.validation.max|sugar_escape:'html':'UTF-8'}">
            {$vardef.range.max|sugar_escape:'html':'UTF-8'}
        {/if}
    </td>
</tr>
<tr>
    <td class='mbLBL'>{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MAX_SIZE"}:</td>
    <td>
        {if $hideLevel < 5}
        <input type="text" name="len" id="int_len" value="{$vardef.len|sugar_escape:'html':'UTF-8'|default:11}"></td>
    <script nonce="{sugar_nonce}">addToValidate('popup_form', 'len', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_MAX_SIZE"}');</script>
    {else}
    <input type="hidden" name="len" id="int_len" value="{$vardef.len|sugar_escape:'html':'UTF-8'}">{$vardef.len|sugar_escape:'html':'UTF-8'}
    {/if}
    </td>
</tr>
{if $range_search_option_enabled}
    <tr>
        <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_ENABLE_RANGE_SEARCH"}:</td>
        <td>
            <input type='checkbox' name='enable_range_search' value="1"
                   {if !empty($vardef.enable_range_search) }checked{/if} {if $hideLevel > 5}disabled{/if} />
            {if $hideLevel > 5}
                <input type="hidden" name="enable_range_search" value="{$vardef.enable_range_search|sugar_escape:'html':'UTF-8'}">
            {/if}
        </td>
    </tr>
{/if}
{if !empty($vardef.auto_increment) }
    <tr id="autoinc_start_wrap" {if empty($vardef.auto_increment) }style="display:none" {/if}>
        <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_AUTOINC_NEXT"}:</td>
        <td>
            <input type="hidden" name="auto_increment" id="auto_increment" value="true">
            <input type="text" name="autoinc_next" id="autoinc_next" value="{$vardef.autoinc_next|sugar_escape:'html':'UTF-8'|default:1}"
                   {if $MB}disabled=1{/if}>
            <script nonce="{sugar_nonce}">addToValidateMoreThan('popup_form', 'autoinc_next', 'int', false, '{sugar_translate module="DynamicFields" label="COLUMN_TITLE_AUTOINC_NEXT"}', {$vardef.autoinc_next|sugar_escape:'javascript'|default:1});</script>
            <input type='hidden' name='autoinc_val_changed' id='autoinc_val_changed' value='false'>
        </td>
    </tr>
{/if}
<tr>
    <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_DISABLE_NUMBER_FORMAT"}:</td>
    <td>
        <input type="checkbox" name="ext3" value="1"
               {if !empty($vardef.disable_num_format) }checked{/if} {if $hideLevel > 5}disabled{/if} />
        {if $hideLevel > 5}
            <input type="hidden" name="ext3" value="{$vardef.disable_num_format|sugar_escape:'html':'UTF-8'}">
        {/if}
    </td>
</tr>
<script nonce="{sugar_nonce}">
    formsWithFieldLogic = new addToValidateFieldLogic('popup_form_id', 'int_min', 'int_max', 'int_default', 'int_len', 'int', 'Invalid Logic.');
</script>
{include file="modules/DynamicFields/templates/Fields/Forms/coreBottom.tpl"}
