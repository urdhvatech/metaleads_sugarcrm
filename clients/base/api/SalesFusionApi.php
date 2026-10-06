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

class SalesFusionApi extends SugarApi
{
    public function registerApiRest()
    {
        return [
            'salesfusion' => [
                'reqType' => 'GET',
                'path' => ['connector', 'salesfusion'],
                'pathVars' => ['', ''],
                'method' => 'getOrgName',
                'shortHelp' => 'Retrieve SalesFusion Connector Org Name',
                'longHelp' => 'include/api/help/connector_salesfusion.html',
            ],
            'prospect_lists_prospects' => [
                'reqType' => 'GET',
                'path' => ['prospect_lists_prospects'],
                'pathVars' => ['', ''],
                'method' => 'getProspectListsProspects',
                'shortHelp' => 'Gets prospect_list_prospects (from a given date)',
                'longHelp' => 'include/api/help/prospect_lists_prospects.html',
                'exceptions' => [
                    'SugarApiExceptionInvalidParameter',
                ],
            ],
        ];
    }

    /**
     * Retrieve connectors registered org name. Cache this client side
     *
     * @param $api
     * @param $args
     * @return array
     * @throws SugarApiExceptionRequestMethodFailure
     */
    public function getOrgName($api, $args)
    {
        if (!hasMarketLicense()) {
            throw new SugarApiExceptionNotAuthorized('EXCEPTION_NOT_AUTHORIZED');
        }

        $sourceConnector = SourceFactory::getSource('ext_rest_salesfusion');
        if (!isset($sourceConnector->orgName)) {
            throw new SugarApiExceptionRequestMethodFailure('No connector settings found, check your connector configs');
        }

        if ($iframeUrl = @$sourceConnector->iframeUrl) {
            $iframeUrl = str_replace("{orgId}", $sourceConnector->orgName, $iframeUrl);
            $iframeUrl = str_replace("{crmName}", 'SugarCRM' . $GLOBALS['sugar_version'], $iframeUrl);
            $iframeUrl = str_replace("{userId}", $GLOBALS['current_user']->id, $iframeUrl);
            $iframeUrl = str_replace("amp;", '', $iframeUrl);
        }

        return [
            'orgName' => $sourceConnector->orgName,
            'iframeUrl' => $iframeUrl,
        ];
    }

    /**
     * Get ProspectListsProspects, with optional filters and criteria
     *
     * @param Object $api the standard api object
     * @param Array $args arguments passed into this API call from REST
     *
     * all args (GET params) are optional
     *
     * i.e. {url}rest/v10/prospect_lists_prospects?
     *     filter[0][date_modified][$gte]=2014/12/03 11:49:33
     *     filter[0][prospect_list_id]={GUID}
     *     filter[0][related_type][$in]=Contacts,Leads,Prospects
     *     order[0][date_modified]=ASC|DESC
     *     offset=30
     *     limit=25
     *     deleted=true
     *
     * @return array output
     */
    public function getProspectListsProspects(Object $api, array $args): array
    {
        if (!hasMarketLicense()) {
            throw new SugarApiExceptionNotAuthorized('EXCEPTION_NOT_AUTHORIZED');
        }

        $q = new SugarQuery(DBManagerFactory::getInstance('listviews'));

        $q->from(BeanFactory::newBean('EmailAddresses'), ['alias' => 'ea']);
        $q->joinTable('email_addr_bean_rel', ['alias' => 'eabr', 'joinType' => 'INNER', 'linkingTable' => true])
            ->on()->equalsField('ea.id', 'eabr.email_address_id')->equals('eabr.primary_address', 1)->equals('eabr.deleted', 0);
        $q->joinTable('prospect_lists_prospects', ['alias' => 'p', 'joinType' => 'INNER', 'linkingTable' => true])
            ->on()->equalsField('eabr.bean_id', 'p.related_id')->equals('ea.deleted', 0);

        if (!(isset($args['deleted']) && in_array(strtolower($args['deleted']), ['true', '1'], true))) {
            $q->where()->equals('p.deleted', 0);
        }
        if (isset($args['filter'][0]['date_modified']['$gte'])) {
            $date = $args['filter'][0]['date_modified']['$gte'];
            if (!preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $date)) {
                throw new SugarApiExceptionInvalidParameter('Invalid date_modified format');
            }
            $dateSQL = $q->getDBManager()->quote($date);
            $q->where()->addRaw("p.date_modified >= '$dateSQL'");
        }
        if (isset($args['filter'][0]['prospect_list_id'])) {
            $q->where()->equals('p.prospect_list_id', $args['filter'][0]['prospect_list_id']);
        }
        if (isset($args['filter'][0]['related_type']['$in'])) {
            $q->where()->in('p.related_type', explode(',', $args['filter'][0]['related_type']['$in']));
        }
        if (isset($args['limit'])) {
            $q->limit($args['limit']);
        }
        if (isset($args['offset'])) {
            $q->offset($args['offset']);
        }

        if (isset($args['order'][0]['date_modified'])) {
            $q->orderBy('p.date_modified', strtoupper($args['order'][0]['date_modified']));
        }

        $q->select(
            'p.id',
            'p.prospect_list_id',
            'p.related_id',
            'p.related_type',
            'p.date_modified',
            'p.deleted',
            'email_address'
        );
        $results = $q->execute();

        // delete a field, if added by orderBy method
        foreach ($results as &$row) {
            unset($row['p__date_modified']);
        }

        return [
            'results' => array_column($results, null, 'id'),
        ];
    }

}
