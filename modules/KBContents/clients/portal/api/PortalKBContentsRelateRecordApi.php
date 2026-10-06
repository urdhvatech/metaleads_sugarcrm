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

class PortalKBContentsRelateRecordApi extends RelateRecordApi
{
    /**
     * Override createRelatedLinks().
     * @inheritdoc
     */
    public function registerApiRest()
    {
        return [
            'fetchRelatedRecord' => [
                'reqType' => 'GET',
                'path' => ['KBContents', '?', 'link', '?', '?'],
                'pathVars' => ['module', 'record', '', 'link_name', 'remote_id'],
                'method' => 'getRelatedRecord',
                'shortHelp' => 'Fetch a single record related to this module',
                'longHelp' => 'include/api/help/module_record_link_link_name_remote_id_get_help.html',
            ],
            'createRelatedRecord' => [
                'reqType' => 'POST',
                'path' => ['KBContents', '?', 'link', '?'],
                'pathVars' => ['module', 'record', '', 'link_name'],
                'method' => 'createRelatedRecord',
                'shortHelp' => 'Create a single record and relate it to this module',
                'longHelp' => 'include/api/help/module_record_link_link_name_post_help.html',
            ],
            'createRelatedLink' => [
                'reqType' => 'POST',
                'path' => ['KBContents', '?', 'link', '?', '?'],
                'pathVars' => ['module', 'record', '', 'link_name', 'remote_id'],
                'method' => 'createRelatedLink',
                'shortHelp' => 'Relates an existing record to this module',
                'longHelp' => 'include/api/help/module_record_link_link_name_remote_id_post_help.html',
            ],
            'createRelatedLinks' => [
                'reqType' => 'POST',
                'path' => ['KBContents', '?', 'link'],
                'pathVars' => ['module', 'record', ''],
                'method' => 'createRelatedLinks',
                'shortHelp' => 'Relates existing records to this module.',
                'longHelp' => 'include/api/help/module_record_link_post_help.html',
            ],
            'updateRelatedLink' => [
                'reqType' => 'PUT',
                'path' => ['KBContents', '?', 'link', '?', '?'],
                'pathVars' => ['module', 'record', '', 'link_name', 'remote_id'],
                'method' => 'updateRelatedLink',
                'shortHelp' => 'Updates relationship specific information ',
                'longHelp' => 'include/api/help/module_record_link_link_name_remote_id_put_help.html',
            ],
            'deleteRelatedLink' => [
                'reqType' => 'DELETE',
                'path' => ['KBContents', '?', 'link', '?', '?'],
                'pathVars' => ['module', 'record', '', 'link_name', 'remote_id'],
                'method' => 'deleteRelatedLink',
                'shortHelp' => 'Deletes a relationship between two records',
                'longHelp' => 'include/api/help/module_record_link_link_name_remote_id_delete_help.html',
            ],
            'createRelatedLinksFromRecordList' => [
                'reqType' => 'POST',
                'path' => ['KBContents', '?', 'link', '?', 'add_record_list', '?'],
                'pathVars' => ['module', 'record', '', 'link_name', '', 'remote_id'],
                'method' => 'createRelatedLinksFromRecordList',
                'shortHelp' => 'Relates existing records from a record list to this record.',
                'longHelp' => 'include/api/help/module_record_links_from_recordlist_post_help.html',
            ],
        ];
    }

    /**
     * Disable linking for `localizations` and `revisions`.
     * @inheritdoc
     */
    public function createRelatedLinks(ServiceBase $api, array $args, $securityTypeLocal = 'view', $securityTypeRemote = 'view')
    {
        if (in_array($args['link_name'], ['localizations', 'revisions'])) {
            throw new SugarApiExceptionInvalidParameter('Unable to link existing record as localisation or revision.');
        }

        return parent::createRelatedLinks($api, $args, $securityTypeLocal, $securityTypeRemote);
    }

    protected function checkRelatedSecurity(ServiceBase $api, array $args, SugarBean $primaryBean, $securityTypeLocal = 'view', $securityTypeRemote = 'view')
    {
        $linkName = $args['link_name'] ?? null;
        if ($linkName === 'notes') {
            $settings = Administration::getSettings('portal', true)->settings;
            $showKBNotes = !isset($settings['portal_showKBNotes']) ? 'enabled' : $settings['portal_showKBNotes'];
            if ($showKBNotes !== 'enabled') {
                throw new SugarApiExceptionNotAuthorized();
            }
        }
        return parent::checkRelatedSecurity($api, $args, $primaryBean, $securityTypeLocal, $securityTypeRemote);
    }
}
