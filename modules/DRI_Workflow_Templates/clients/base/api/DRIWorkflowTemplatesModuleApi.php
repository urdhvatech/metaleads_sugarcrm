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

class DRIWorkflowTemplatesModuleApi extends ModuleApi
{
    public function registerApiRest()
    {
        return [
            'create' => [
                'reqType' => 'POST',
                'path' => ['DRI_Workflow_Templates'],
                'pathVars' => ['module'],
                'method' => 'createRecord',
                'shortHelp' => 'This method creates a new record of the specified type',
                'longHelp' => 'include/api/help/module_post_help.html',
            ],
            'update' => [
                'reqType' => 'PUT',
                'path' => ['DRI_Workflow_Templates', '?'],
                'pathVars' => ['module', 'record'],
                'method' => 'updateRecord',
                'shortHelp' => 'This method updates a record of the specified type',
                'longHelp' => 'include/api/help/module_record_put_help.html',
            ],
            'delete' => [
                'reqType' => 'DELETE',
                'path' => ['DRI_Workflow_Templates', '?'],
                'pathVars' => ['module', 'record'],
                'method' => 'deleteRecord',
                'shortHelp' => 'This method deletes a record of the specified type',
                'longHelp' => 'include/api/help/module_record_delete_help.html',
            ],
        ];
    }

    public function createRecord(ServiceBase $api, array $args)
    {
        $this->ensureDeveloperAccess($api, $args['module']);

        return parent::createRecord($api, $args);
    }

    public function updateRecord(ServiceBase $api, array $args)
    {
        $this->ensureDeveloperAccess($api, $args['module']);

        return parent::updateRecord($api, $args);
    }

    public function deleteRecord(ServiceBase $api, array $args)
    {
        $this->ensureDeveloperAccess($api, $args['module']);

        return parent::deleteRecord($api, $args);
    }
}
