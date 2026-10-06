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

/**
 * API Class to handle file and image (attachment) interactions with a field in
 * a record.
 */
class UsersFileApi extends FileApi
{
    public function registerApiRest()
    {
        return [
            'removeFile' => [
                'reqType' => 'DELETE',
                'path' => ['Users', '?', 'file', '?'],
                'pathVars' => ['module', 'record', '', 'field'],
                'method' => 'removeFile',
                'rawPostContents' => true,
                'shortHelp' => 'Removes a file from a field.',
                'longHelp' => 'include/api/help/module_record_file_field_delete_help.html',
            ],
        ];
    }

    /**
     * Removes an attachment from a record field
     *
     * @param ServiceBase $api The service base
     * @param array $args The request args
     * @return array Listing of fields for a record
     * @throws SugarApiExceptionError|SugarApiExceptionNoMethod|SugarApiExceptionRequestMethodFailure
     */
    public function removeFile(ServiceBase $api, array $args)
    {
        global $current_user;
        $bean = $this->loadBean($api, $args);
        $field = $args['field'];
        /**
         * Restrict the deletion of attachments to the user themselves or administrators
         */
        if ($this->hasAdminOrDeveloperAccess($api, 'Users') || $bean->id === $current_user->id) {
            parent::removeFile($api, $args);
        } else {
            throw new SugarApiExceptionNotAuthorized('Not allowed to DELETE ' . $field . ' field in Users module.');
        }
    }
}
