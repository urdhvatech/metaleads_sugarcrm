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


class AuditApi extends FilterApi
{
    public function registerApiRest()
    {
        return [
            'export_audit' => [
                'reqType' => 'GET',
                'path' => ['<module>', 'audit', 'export'],
                'pathVars' => ['module', '', ''],
                'method' => 'exportAudit',
                'shortHelp' => 'Export Audit records for module',
                'minVersion' => '11.11',
                'longHelp' => 'include/api/help/audit_export_help.html',
            ],
            'view_change_log' => [
                'reqType' => 'GET',
                'path' => ['<module>', '?', 'audit'],
                'pathVars' => ['module', 'record', 'audit'],
                'method' => 'viewChangeLog',
                'shortHelp' => 'View audit log in record view',
                'minVersion' => '11.11',
                'longHelp' => 'include/api/help/audit_get_help.html',
            ],
            'audit_log_count' => [
                'reqType' => 'GET',
                'path' => ['<module>', '?', 'audit', 'count'],
                'pathVars' => ['module', 'record', 'audit', 'count'],
                'method' => 'getAuditLogCount',
                'shortHelp' => 'Get count of audit log records',
                'minVersion' => '11.11',
                'longHelp' => 'include/api/help/audit_count_help.html',
            ],
        ];
    }

    public function viewChangeLog(ServiceBase $api, array $args)
    {
        global $focus;

        $this->requireArgs($args, ['module', 'record']);

        $focus = BeanFactory::getBean($args['module'], $args['record']);

        if (!$focus->ACLAccess('view')) {
            throw new SugarApiExceptionNotAuthorized('no access to the bean');
        }

        $auditBean = BeanFactory::newBean('Audit');
        $searchTerm = trim((string) ($args['search'] ?? ''));

        if (!isset($args['max_num'])) {
            return [
                'next_offset' => -1,
                'records' => $auditBean->getAuditLog($focus),
            ];
        } else {
            $options = $this->parseArguments($api, $args, $auditBean);
            $options['order_by'] = trim((string) ($args['audit_order_by'] ?? ''));
            $options['order_direction'] = trim((string) ($args['audit_order_direction'] ?? ''));
            $records = $searchTerm !== ''
                ? $auditBean->getAuditLogChunkWithSearch($focus, $options, $searchTerm)
                : $auditBean->getAuditLogChunk($focus, $options);
            if ($options['limit'] > 0 && safeCount($records) > $options['limit']) {
                $next_offset = $options['limit'] + $options['offset'];
                array_pop($records);
            } else {
                $next_offset = -1;
            }
            return [
                'next_offset' => $next_offset,
                'records' => $records,
            ];
        }
    }

    /**
     * Get the count of audit log records for a bean.
     *
     * @param ServiceBase $api Service API
     * @param array $args API arguments
     * @return array Count response
     * @throws SugarApiExceptionNotAuthorized
     */
    public function getAuditLogCount(ServiceBase $api, array $args)
    {
        $this->requireArgs($args, ['module', 'record']);

        $focus = BeanFactory::getBean($args['module'], $args['record']);

        if (!$focus->ACLAccess('view')) {
            throw new SugarApiExceptionNotAuthorized('no access to the bean');
        }

        $auditBean = BeanFactory::newBean('Audit');
        $searchTerm = trim((string) ($args['search'] ?? ''));

        $count = $searchTerm !== ''
            ? $auditBean->getAuditLogCountWithSearch($focus, $searchTerm)
            : $auditBean->getAuditLogCount($focus);

        return [
            'record_count' => $count,
        ];
    }

    public function exportAudit(ServiceBase $api, array $args)
    {
        global $focus;
        $this->requireArgs($args, ['module']);
        $focus = BeanFactory::getBean($args['module']);
        if (!$focus->ACLAccess('view')) {
            throw new SugarApiExceptionNotAuthorized('no access to the bean');
        }
        $auditBean = BeanFactory::newBean('Audit');
        $this->defaultLimit = -1;
        $options = $this->parseArguments($api, $args, $auditBean);

        $records = $auditBean->getAuditLogChunk($focus, $options);
        if ($options['limit'] > 0 && safeCount($records) > $options['limit']) {
            $next_offset = $options['limit'] + $options['offset'];
            array_pop($records);
        } else {
            $next_offset = -1;
        }
        return [
            'next_offset' => $next_offset,
            'records' => $records,
        ];
    }
}
