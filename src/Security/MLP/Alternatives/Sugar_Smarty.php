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
declare(strict_types=1);
namespace Sugarcrm\Sugarcrm\Security\MLP\Alternatives;

use SugarAutoLoader;

final class Sugar_Smarty
{
    private \Sugar_Smarty $smarty;

    public function __construct()
    {
        $this->smarty = new \Sugar_Smarty();
        $securityPolicy = new \Smarty_Security($this->smarty);
        // forbid all static calls
        $securityPolicy->static_classes = [null];
        // explicitly allow list of PHP functions (equals to defaults):
        $securityPolicy->php_functions = ['isset', 'empty', 'count', 'sizeof', 'in_array', 'is_array', 'time',];
        $securityPolicy->allow_super_globals = false;
        $securityPolicy->allow_constants = false;
        // disable all stream wrappers
        $securityPolicy->streams = null;
        // 'math' internally uses eval()
        $securityPolicy->disabled_tags = ['eval', 'fetch', 'include_php', 'math', 'php',];
        /**
         * Disable 'template_object' to prevent tricks like:  {$name=$smarty.template_object->disableSecurity()} {include "file://etc/passwd"}
         * Disable 'current_dir' to prevent filesystem info leakage
         */
        $securityPolicy->disabled_special_smarty_vars = ['template_object', 'current_dir',];
        if (defined('SUGAR_SHADOW_PATH')) {
            $securityPolicy->secure_dir[] = SUGAR_SHADOW_PATH;
        }
        $this->smarty->enableSecurity($securityPolicy);
    }

    /**
     * fetches a rendered Smarty template
     *
     * @param string $template the resource handle of the template file or template object
     * @param mixed $cache_id cache id to be used with this template
     * @param mixed $compile_id compile id to be used with this template
     * @param object $parent next higher level of Smarty variables
     *
     * @return string rendered template output
     * @throws \SmartyException
     * @throws \Exception
     */
    public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null)
    {
        $this->validateTemplateName($template);
        return $this->smarty->fetch($template, $cache_id, $compile_id, $parent);
    }

    /**
     * displays a Smarty template
     *
     * @param string $template the resource handle of the template file or template object
     * @param mixed $cache_id cache id to be used with this template
     * @param mixed $compile_id compile id to be used with this template
     * @param object $parent next higher level of Smarty variables
     *
     * @throws \Exception
     * @throws \SmartyException
     */
    public function display($template = null, $cache_id = null, $compile_id = null, $parent = null)
    {
        $this->validateTemplateName($template);
        $this->smarty->display($template, $cache_id, $compile_id, $parent);
    }

    /**
     * Fetch template or custom double
     * @param string $resource_name
     * @param string $cache_id
     * @param string $compile_id
     * @param boolean $display
     * @throws \SmartyException
     * @see Smarty::fetch()
     */
    public function fetchCustom($resource_name, $cache_id = null, $compile_id = null, $display = false)
    {
        $this->validateTemplateName($resource_name);
        return $this->smarty->fetch(SugarAutoLoader::existingCustomOne($resource_name), $cache_id, $compile_id, $display);
    }

    /**
     * Display template or custom double
     * @param string $resource_name
     * @param string $cache_id
     * @param string $compile_id
     * @see Smarty::display()
     */
    public function displayCustom($resource_name, $cache_id = null, $compile_id = null)
    {
        $this->validateTemplateName($resource_name);
        $this->smarty->display(SugarAutoLoader::existingCustomOne($resource_name), $cache_id, $compile_id);
    }


    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
    {
        if (!in_array($name, [
            'assign',
            'assign_by_ref',
            'append_by_ref',
            'clear_all_assign',
            'clear_assign',
            'clear_config',
            'clear_all_cache',
            'clear_cache',
            'clear_compiled_tpl',
            'config_load',
            'create_data',
            'get_config_vars',
            'get_registered_object',
            'get_registered_objects',
            'get_registered_plugins',
            'get_tags',
            'is_cached',
            'register_block',
            'register_object',
            'trigger_error',
            'get_template_vars',
            'getTemplateVars',
            'get_config_vars',
            'getConfigVars',
            'addTemplateDir',
            'setTemplateDir',
        ])) {
            throw new \Exception("Method {$name} is not allowed");
        }
        return call_user_func_array([$this->smarty, $name], $arguments);
    }

    /**
     * @param string|null $template
     * @return void
     * @throws \Exception
     */
    protected function validateTemplateName(?string $template): void
    {
        if (is_string($template) && preg_match('/\{.*\}/', $template)) {
            throw new \Exception('Invalid template');
        }
    }
}
