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


use Sugarcrm\Sugarcrm\CSP\FileViolationReportStorage;

class LoggerApi extends SugarApi
{
    public function registerApiRest()
    {
        return [
            'logPost' => [
                'reqType' => 'POST',
                'path' => ['logger'],
                'pathVars' => [],
                'method' => 'logMessage',
                'shortHelp' => 'Writes a message out to the log prefaced by a channel name',
                'longHelp' => 'include/api/help/logger_help.html',
            ],
            'cspReport' => [
                'reqType' => 'POST',
                'path' => ['csp_report'],
                'pathVars' => [''],
                'method' => 'logReport',
                'shortHelp' => 'Writes a CSP violation report',
                'noLoginRequired' => true,
            ],
        ];
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return true[]
     */
    public function logReport(ServiceBase $api, array $args): array
    {
        if (empty($args['csp-report'])) {
            return ['status' => false];
        }
        // The CSP report is saved as a JSON object, but the file extension is .log to rely on
        // webserver configuration to prevent access to the file directly.
        $fileStorage = new FileViolationReportStorage('csp_violations.log');
        return ['status' => $fileStorage->save($args['csp-report'])];
    }

    /**
     * Logs a message on the server, based on supplied arguments.
     *
     * @param ServiceBase $api The service object.
     * @param array $args The request arguments.
     * @return array Status.
     */
    public function logMessage(ServiceBase $api, array $args)
    {
        if (empty($args['message'])) {
            return ['status' => false];
        }

        $log = LoggerManager::getLogger();

        $level = empty($args['level']) ? 'debug' : $args['level'];
        $message = $args['message'];
        $channel = empty($args['channel']) ? 'LoggerApi' : $args['channel'];

        $log->$level("{$channel} - {$message}");

        return ['status' => true];
    }
}
