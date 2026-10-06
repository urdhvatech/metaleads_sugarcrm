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
            <input type="text" name="default" id="default" value="{$vardef.default|sugar_escape:'html':'UTF-8'}"
                   maxlength="{$vardef.len|sugar_escape:'html':'UTF-8'|default:50}">
        {else}
            <input type="hidden" id="default" name="default" value="{$vardef.default|sugar_escape:'html':'UTF-8'}">{$vardef.default|sugar_escape:'html':'UTF-8'}
        {/if}
    </td>
</tr>
{* Note: This template intentionally does NOT include a length field *}
{* htmleditable_tinymce fields should use longtext storage without length constraints *}

{include file="modules/DynamicFields/templates/Fields/Forms/coreBottom.tpl"}
