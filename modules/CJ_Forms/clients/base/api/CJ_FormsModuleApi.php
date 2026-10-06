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

class CJ_FormsModuleApi extends ModuleApi
{
    /**
     * @return array[]
     */
    public function registerApiRest()
    {
        return [
            'create' => [
                'reqType' => 'POST',
                'path' => ['CJ_Forms'],
                'pathVars' => ['module'],
                'method' => 'createRecord',
                'shortHelp' => 'This method creates a new record of the specified type',
                'longHelp' => 'include/api/help/module_post_help.html',
            ],
            'update' => [
                'reqType' => 'PUT',
                'path' => ['CJ_Forms', '?'],
                'pathVars' => ['module', 'record'],
                'method' => 'updateRecord',
                'shortHelp' => 'This method updates a record of the specified type',
                'longHelp' => 'include/api/help/module_record_put_help.html',
            ],
        ];
    }

    /**
     * Create CJ_Forms record
     * @param ServiceBase $api
     * @param array $args
     * @return array
     * @throws SugarApiExceptionInvalidParameter
     * @throws SugarApiExceptionMissingParameter
     * @throws SugarApiExceptionNotAuthorized
     */
    public function createRecord(ServiceBase $api, array $args)
    {
        $args['populate_fields'] = $this->filterPopulateFields($args['populate_fields'] ?? '[]');

        return parent::createRecord($api, $args);
    }

    /**
     * Update CJ_Forms record
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function updateRecord(ServiceBase $api, array $args)
    {
        $args['populate_fields'] = $this->filterPopulateFields($args['populate_fields'] ?? '[]');

        return parent::updateRecord($api, $args);
    }

    /**
     * Filter populate_fields from request. Don't allow manipulations with
     * Users::user_hash for everybody and Users::is_admin for Regular Users
     * @param string $populateFields
     * @return string
     */
    private function filterPopulateFields(string $populateFields): string
    {
        global $current_user;

        $decodedFields = json_decode($populateFields, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid json was provided for Populated Fields.');
        }

        $conditions = ['Users' => ['user_hash']];

        if (!$current_user->isAdmin()) {
            $conditions['Users'][] = 'is_admin';
        }

        $filteredFields = array_filter($decodedFields, function ($field) use ($conditions) {
            $module = $field['module'] ?? null;

            if (!$module) {
                return true;
            }

            $fieldsToExclude = $conditions[$module] ?? [];

            return !safeInArray($field['actualFieldName'] ?? '', $fieldsToExclude);
        });

        //Re-index array before encoding to save structure
        return json_encode(array_values($filteredFields));
    }
}
