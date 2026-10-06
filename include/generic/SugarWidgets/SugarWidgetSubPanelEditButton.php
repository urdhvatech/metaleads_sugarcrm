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
use Sugarcrm\Sugarcrm\Security\Escaper\Escape;

class SugarWidgetSubPanelEditButton extends SugarWidgetField
{
    /**
     * @var mixed|\SugarBean|null
     */
    public $bean;
    protected static $defs = [];
    protected static $edit_icon_html;

    public function displayHeaderCell($layout_def)
    {
        return '';
    }

    public function displayList($layout_def)
    {
        global $app_strings;
        global $subpanel_item_count;

        $uniqueId = Escape::htmlAttr($layout_def['subpanel_id'] . '_edit_' . $subpanel_item_count); //bug 51512
        $formname = Escape::js($this->getFormName($layout_def));
        $id = Escape::js($layout_def['fields']['ID']);
        $subpanelId = Escape::js($layout_def['subpanel_id']);
        $loading = Escape::js($app_strings['LBL_LOADING']);

        $onclick = "document.forms['{$formname}'].record.value='{$id}';";
        $onclick .= "document.forms['{$formname}'].action.value='SubpanelEdits';";
        $onclick .= "retValz = SUGAR.subpanelUtils.sendAndRetrieve('{$formname}', 'subpanel_{$subpanelId}', '{$loading}', '{$subpanelId}');";
        $onclick .= "document.forms['{$formname}'].record.value='';retValz;return false;";

        $nonce = Nonce::create();
        if ($layout_def['EditView'] && $this->isQuickCreateValid($layout_def['module'], $layout_def['subpanel_id'])) {
            $label = Escape::html($app_strings['LNK_EDIT']);
            return <<<HTML
            <a href="#" class="listViewTdToolsS1" id="{$uniqueId}" data-onclick-{$nonce}="{$onclick}">{$label}</a>
            HTML;
        } elseif ($layout_def['EditView']) {
            if (isModuleBWC($layout_def['module'])) {
                $label = $app_strings['LNK_EDIT'];
            } else {
                //TODO:SP-1618 can't nav to inline edit in sidecar
                $label = $app_strings['LNK_VIEW'];
            }

            $module = Escape::js($layout_def['module']);

            $field = Escape::js($layout_def['linked_field']);
            $label = Escape::html($label);


            if (empty($field)) {
                return <<<HTML
                <a href="#" data-onmouseover-{$nonce}="javascript:subp_nav('{$module}', '{$id}', 'e', this);"
                            data-onfocus-{$nonce}="javascript:subp_nav('{$module}', '{$id}', 'e', this);"
                            data-onclick-{$nonce}="javascript:subp_nav_sidecar('{$module}', '{$id}', 'e');"
                            class="listViewTdToolsS1" id="{$uniqueId}">{$label}</a>
                HTML;
            } else {
                return <<<HTML
                <a href="#" data-onmouseover-{$nonce}="javascript:subp_nav('{$module}', '{$id}', 'e', this, '{$field}');"
                            data-onfocus-{$nonce}="javascript:subp_nav('{$module}', '{$id}', 'e', this, '{$field}');"
                            data-onclick-{$nonce}="javascript:subp_nav_sidecar('{$module}', '{$id}', 'e');"
                            class="listViewTdToolsS1" id="{$uniqueId}">{$label}</a>
                HTML;
            }
        }

        return '';
    }

    protected function getSubpanelDefs($module_dir)
    {
        if (!isset(self::$defs[$module_dir])) {
            $defs = SugarAutoLoader::loadWithMetafiles($module_dir, 'subpaneldefs');
            if ($defs) {
                require $defs;
            }
            $defs = SugarAutoLoader::loadExtension('layoutdefs', $module_dir);
            if ($defs) {
                require $defs;
            }

            if (!isset($layout_defs)) {
                return null;
            }

            self::$defs[$module_dir] = $layout_defs;
        }

        return self::$defs[$module_dir];
    }

    public function isQuickCreateValid($module, $panel_id)
    {
        //try to retrieve the subpanel defs
        global $beanList;
        $isValid = false;
        $layout_defs = $this->getSubpanelDefs($module);

        //For Sidecar modules return false as we want caller to add an
        //onClick routed to the SubPanelTiles.js subp_nav_sidecar function
        if (!isModuleBWC($module)) {
            return false;
        }

        //lets check to see if the subpanel buttons are defined, and if they extend quick create
        //If no buttons are defined, then the default ones are used which do NOT use quick create
        if (!empty($panel_id) && !empty($layout_defs) && is_array($layout_defs)
            && !empty($layout_defs[$module]) && !empty($layout_defs[$module]['subpanel_setup'][$panel_id])
            && !empty($layout_defs[$module]['subpanel_setup'][$panel_id]['top_buttons'])
            && is_array($layout_defs[$module]['subpanel_setup'][$panel_id]['top_buttons'])
        ) {
            //we have the buttons from the definitions, lets see if they enabled for quickcreate
            foreach ($layout_defs[$module]['subpanel_setup'][$panel_id]['top_buttons'] as $buttonClasses) {
                $buttonClass = '';
                //get the button class
                if (isset($buttonClasses['widget_class'])) {
                    $buttonClass = $buttonClasses['widget_class'];
                }
                //include the button class and see if it extends quick create
                $className = 'SugarWidget' . $buttonClass;
                if (SugarAutoLoader::requireWithCustom('include/generic/SugarWidgets/' . $className . '.php')) {
                    if (class_exists($className)) {
                        $button = new $className();
                        //set valid flag to true if this class extends quickcreate button
                        if ($button instanceof SugarWidgetSubPanelTopButtonQuickCreate) {
                            $isValid = true;
                        }
                    }
                }
            }
        }


        //if only default buttons are used, or none of the buttons extended quick create, then there is no need to proceed
        if (!$isValid) {
            return false;
        }

        //So our create buttons are defined, now lets check for the proper quick create meta files
        if (SugarAutoLoader::existingCustomOne('modules/' . $module . '/metadata/quickcreatedefs.php')) {
            return true;
        }

        return false;
    }

    public function getFormName($layout_def)
    {
        global $currentModule;
        $formname = 'formform';
        $mod = $currentModule;

        //we need to retrieve the relationship name as the form name
        //if this is a collection, just return the module name, start by loading the subpanel definitions
        $layout_defs = $this->getSubpanelDefs($mod);

        //check to make sure the proper arrays were loaded
        if (!empty($layout_defs) && is_array($layout_defs) && !empty($layout_defs[$mod]) && !empty($layout_defs[$mod]['subpanel_setup'][$layout_def['subpanel_id']])) {
            //return module name if this is a collection
            $def_to_check = $layout_defs[$mod]['subpanel_setup'][$layout_def['subpanel_id']];
            if (isset($def_to_check['type']) && $def_to_check['type'] == 'collection') {
                $formname .= $layout_def['module'];
                return $formname;
            }
        }

        global $beanList;


        if (empty($this->bean)) {
            $this->bean = BeanFactory::newBean($layout_def['module']);
        }

        //load the bean relationships for the next check
        $link = $layout_def['linked_field'];
        if (empty($this->bean->$link)) {
            $this->bean->load_relationship($link);
        }

        //if this is not part of a subpanel collection, see if the link field name and relationship is defined on the subpanel bean
        if (isset($this->bean->$link)
            && !empty($this->bean->field_defs[$link])
            && !empty($this->bean->field_defs[$link]['relationship'])) {
            //return relationship name
            return $formname . $this->bean->field_defs[$link]['relationship'];
        } else {
            //if the relationship was not found on the subpanel bean, then see if the relationship is defined on the parent bean
            $parentBean = BeanFactory::newBean($mod);
            $subpanelMod = strtolower($layout_def['module']);
            if (!empty($parentBean->field_defs[$subpanelMod])
                && !empty($parentBean->field_defs[$subpanelMod]['relationship'])) {
                //return relationship name
                return $formname . $parentBean->field_defs[$subpanelMod]['relationship'];
            }
        }

        //as a last resort, if the relationship is not found, then default to the module name
        return $formname . $layout_def['module'];
    }
}
