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

class CurrenciesModuleApi extends ModuleApi
{
    public function registerApiRest()
    {
        return [
            'create' => [
                'reqType' => 'POST',
                'path' => ['Currencies'],
                'pathVars' => ['module'],
                'method' => 'createRecord',
                'shortHelp' => 'This method creates a new record of the specified type',
                'longHelp' => 'include/api/help/module_post_help.html',
            ],
            'update' => [
                'reqType' => 'PUT',
                'path' => ['Currencies', '?'],
                'pathVars' => ['module', 'record'],
                'method' => 'updateRecord',
                'shortHelp' => 'This method updates a record of the specified type',
                'longHelp' => 'include/api/help/module_record_put_help.html',
            ],
            'delete' => [
                'reqType' => 'DELETE',
                'path' => ['Currencies', '?'],
                'pathVars' => ['module', 'record'],
                'method' => 'deleteRecord',
                'shortHelp' => 'This method deletes a record of the specified type',
                'longHelp' => 'include/api/help/module_record_delete_help.html',
            ],
        ];
    }

    public function createRecord(ServiceBase $api, array $args)
    {
        if ($api->user->isAdmin()) {
            return parent::createRecord($api, $args);
        }

        throw new SugarApiExceptionNotAuthorized();
    }

    public function updateRecord(ServiceBase $api, array $args)
    {
        if ($api->user->isAdmin()) {
            return parent::updateRecord($api, $args);
        }

        throw new SugarApiExceptionNotAuthorized();
    }

    public function deleteRecord(ServiceBase $api, array $args)
    {
        if ($api->user->isAdmin()) {
            return parent::deleteRecord($api, $args);
        }

        throw new SugarApiExceptionNotAuthorized();
    }
}
