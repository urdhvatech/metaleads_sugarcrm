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

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class TeamFilterApi extends FilterApi
{
    public function registerApiRest()
    {
        return [
            'TeamSearch' => [
                'reqType' => 'GET',
                'path' => ['Teams'],
                'jsonParams' => ['filter'],
                'pathVars' => ['module_list'],
                'method' => 'filterList',
                'shortHelp' => 'Search Team records',
                'longHelp' => 'include/api/help/module_filter_get_help.html',
                'exceptions' => [
                    // Thrown in filterList and filterListSetup
                    'SugarApiExceptionInvalidParameter',
                    // Thrown in filterListSetup and parseArguments
                    'SugarApiExceptionNotAuthorized',
                    'SugarApiExceptionError',
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Filters Teams based on the Users module.
     *
     * @param ServiceBase $api The REST API object.
     * @param array $args REST API arguments.
     * @param string $acl Which type of ACL to check.
     * @return array The REST response as a PHP array.
     * @throws SugarApiExceptionError If retrieving a predefined filter failed.
     * @throws SugarApiExceptionInvalidParameter If any arguments are invalid.
     * @throws SugarApiExceptionNotAuthorized If we lack ACL access.
     */
    public function filterList(ServiceBase $api, array $args, $acl = 'list')
    {
        $args['module'] = $args['module_list'];
        $api->action = 'list';
        [$args, $q, $options, $seed] = $this->filterListSetup($api, $args);
        // Adds the additional requirements for Users
        $this->getWhereWithUserData($q);

        return $this->runQuery($api, $args, $q, $options, $seed);
    }

    /**
     * Sets the proper query params to filter Teams based on the Users module
     *
     * @param SugarQuery query to be expanded
     */
    protected function getWhereWithUserData($query = null)
    {
        if ($query instanceof SugarQuery) {
            $query
                // JOIN USER_DATA
                ->joinTable('users', ['alias' => 'user_data', 'joinType' => 'LEFT', 'linkingTable' => true])
                ->on()
                ->equalsField('user_data.id', 'associated_user_id');
            $query
                ->where()
                ->queryOr()
                ->equals('user_data.status', 'Active')
                ->isNull('associated_user_id');
        }
    }
}
