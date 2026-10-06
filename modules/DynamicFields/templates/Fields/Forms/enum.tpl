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
    <td class="mbLBL">{sugar_translate module="DynamicFields" label="LBL_DROP_DOWN_LIST"}:</td>
    <td>
        {if $hideLevel < 5 && empty($vardef.function) && (!isset($vardef.studio.field.options) || isTruthy($vardef.studio.field.options))}
            {sugar_html_options name="options" id="options" selected=$selected_dropdown values=$dropdowns output=$dropdowns data-onchange="ModuleBuilder.dropdownChanged(this.value);"}
            {if !$uneditable}
                <br>
                <input type="button" value="{sugar_translate module="DynamicFields" label="LBL_BTN_EDIT"}" class="button"
                       data-onclick-{sugar_nonce}={literal}"openDropdown(this.form.options.value)"{/literal}>
                <input type="button" value="{sugar_translate module="DynamicFields" label="LBL_BTN_ADD"}" class="button"
                       data-onclick-{sugar_nonce}={literal}"openDropdown()"{/literal}>

                <script type="text/javascript" nonce="{sugar_nonce}">
                    function openDropdown(name) {
                        let page = 'Administration/dropdown-editor' + (name ? '/' + name : '');
                        if (parent.SUGAR.App.router) {
                            parent.SUGAR.App.router.navigate(page, {
                                'trigger': true
                            });
                        } else {
                            window.parent.location.href = location.origin + '/#' + page;
                        }
                    }
                </script>
            {/if}
        {else}
            <input type="hidden" name="options" value="{$selected_dropdown|sugar_escape:'html':'UTF-8'}">{$selected_dropdown|sugar_escape:'html':'UTF-8'}
        {/if}
    </td>
</tr>
{if !isset($vardef.studio.default) || isTruthy($vardef.studio.default)}
    <tr>
        <td class="mbLBL">{sugar_translate module="DynamicFields" label="COLUMN_TITLE_DEFAULT_VALUE"}:</td>
        <td>
            {if $hideLevel < 5 && empty($vardef.function)}
                <select name="default[]" id="default[]"{if $multi} multiple{/if}>
                    {sugar_html_options selected=$selected_options options=$default_dropdowns}
                </select>
            {else}
                <input type="hidden" name="default[]" id="default[]" value="{$vardef.default|sugar_escape:'html':'UTF-8'}">{$vardef.default|sugar_escape:'html':'UTF-8'}
            {/if}
        </td>
    </tr>
{/if}
{if !$radio && (!isset($vardef.studio.field.options) || isTruthy($vardef.studio.field.options))}
    <tr id="depTypeRow" class="toggleDep">
        <td class="mbLBL">{sugar_translate module="DynamicFields" label="LBL_DEPENDENT"}:</td>
        <td>
            <select id="depTypeSelect" data-onchange-{sugar_nonce}="ModuleBuilder.toggleParent(this.value === 'parent'); ModuleBuilder.toggleDF(this.value === 'formula'); ">
                <option label="{sugar_translate module="ModuleBuilder" label="LBL_NONE"}" value="">
                    {sugar_translate module="ModuleBuilder" label="LBL_NONE"}
                </option>
                {if !empty($module_dd_fields)}
                    <option label="{sugar_translate module="ModuleBuilder" label="LBL_PARENT_DROPDOWN"}" value="parent">
                        {sugar_translate module="ModuleBuilder" label="LBL_PARENT_DROPDOWN"}
                    </option>
                {/if}
                <option label="{sugar_translate module="ModuleBuilder" label="LBL_FORMULA"}" value="formula">
                    {sugar_translate module="ModuleBuilder" label="LBL_FORMULA"}
                </option>
            </select>
            <script nonce="{sugar_nonce}">
                //For enums, don't use the formal dependent checkbox, use this dependency type selector
                $('#depCheckboxRow').hide();
                ModuleBuilder.toggleParent({if empty($vardef.visibility_grid)}false{else}true{/if});
                {if !empty($vardef.visibility_grid)}
                $('#depTypeSelect').val("parent");
                {elseif !empty($vardef.dependency)}
                $('#depTypeSelect').val("formula");
                {/if}
            </script>
            {** We can only have a formula or a vis_grid. Before we save we need to clear the one we aren't using **}
            <input type="hidden" id="customTypeValidate" data-onchange-{sugar_nonce}="return ModuleBuilder.validateDD()"/>
        </td>
    </tr>
    <tr id="visGridRow" {if empty($vardef.visibility_grid)}style="display:none"{/if} class="toggleDep">
        <td class="mbLBL">{sugar_translate module="DynamicFields" label="LBL_PARENT_DROPDOWN"}:</td>
        <td>
            {if !empty($vardef.visibility_grid.trigger)}
                {sugar_html_options name="parent_dd" id="parent_dd" selected=$vardef.visibility_grid.trigger options=$module_dd_fields}
            {else}
                {sugar_html_options name="parent_dd" id="parent_dd" selected="" options=$module_dd_fields}
            {/if}
            <input type="hidden" name="visibility_grid" id="visibility_grid" value="{$vardef.visibility_grid|@json|escape:'hexentity'}"/>
            {if $hideLevel < 5}
                <button data-onclick-{sugar_nonce}="ModuleBuilder.editVisibilityGrid('visibility_grid', YAHOO.util.Dom.get('parent_dd').value, YAHOO.util.Dom.get('options').value)">
                    {sugar_translate module="DynamicFields" label="LBL_EDIT_VIS"}
                </button>
            {/if}
        </td>
    </tr>
    <tr id="visFormulaRow" {if empty($vardef.dependency)}style="display:none"{/if}>
        <td class="mbLBL">{sugar_translate module="DynamicFields" label="LBL_VISIBLE_IF"}:</td>
        <td>
            <input id="dependency" type="hidden" name="dependency" value="{$vardef.dependency|sugar_escape:'html'}"
                   data-onchange-{sugar_nonce}="document.getElementById('dependency_display').value = this.value"/>
            <input id="dependency_display" type="text" name="dependency_display"
                   value="{$vardef.dependency|sugar_escape:'html'}" readonly="1"/>
            <input class="button" type=button name="editFormula" value="{sugar_translate label="LBL_BTN_EDIT_FORMULA"}"
                   data-onclick-{sugar_nonce}="ModuleBuilder.moduleLoadFormula(YAHOO.util.Dom.get('dependency').value, 'dependency', 'boolean')"/>
        </td>
    </tr>
{/if}
{include file="modules/DynamicFields/templates/Fields/Forms/coreBottom.tpl"}
