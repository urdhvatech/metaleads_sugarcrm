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
namespace Sugarcrm\Sugarcrm\Security\MLP\Alternatives;

if (!function_exists('Sugarcrm\Sugarcrm\Security\MLP\Alternatives\unserialize')) {
    /**
     * @param string $data
     * @param array $options
     * @return mixed
     */
    function unserialize(string $data, array $options = []): mixed
    {
        if (isset($options['allowed_classes'])) {// Passed options take precedence over config
            if (is_array($options['allowed_classes'])) {
                $allowedClasses = $options['allowed_classes'];
            } else {// No classes are allowed, preventing bypassing with 'allowed_classes' => true
                $allowedClasses = [];
            }
        } else {// No allowed classes explicitly specified, fallback to config
            $allowedClasses = \SugarConfig::getInstance()->get('moduleInstaller.unserialize.allowed_classes', []);
        }
        //Filter known classes that may be used in gadget chain for PHP deserialization
        $allowedClasses = array_filter($allowedClasses, function ($class) {
            return !in_array(strtolower($class), [
                'guzzlehttp\cookie\filecookiejar',
                'guzzlehttp\psr7\fnstream',
                'guzzlehttp\handlerstack',
                'doctrine\common\cache\psr6\cacheadapter',
                'laminas\http\response\stream',
                'laminas\cache\storage\adapter\filesystem',
                'laminas\cache\psr\cacheitempool\cacheitem',
                'monolog\handler\grouphandler',
                'monolog\handler\syslogudphandler',
                'monolog\handler\bufferhandler',
                'monolog\handler\nativemailerhandler',
                'monolog\handler\rollbarhandler',
                'monolog\handler\fingerscrossedhandler',
                'smarty_internal_template',
                'smarty_internal_templateparser',
                'smarty_internal_configfileparser',

            ], true);
        });
        $options['allowed_classes'] = $allowedClasses;
        return \unserialize($data, $options);
    }
}
