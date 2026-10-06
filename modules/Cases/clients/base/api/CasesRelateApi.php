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

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace -- Legacy API class without namespace
// phpcs:disable Generic.Files.LineLength.TooLong -- Allow long lines for SQL queries and descriptions

/**
 * Optimized API for Cases archived_emails relationship using UNION queries
 * Integrates with standard SugarCRM infrastructure while maintaining performance optimization
 */
class CasesRelateApi extends RelateApi
{
    public function registerApiRest()
    {
        return [
            'filterRelatedRecords' => [
                'reqType' => 'GET',
                'path' => ['Cases', '?', 'link', 'archived_emails', 'filter'],
                'pathVars' => ['module', 'record', '', 'link_name', ''],
                'jsonParams' => ['filter'],
                'method' => 'filterRelated',
                'shortHelp' => 'Lists related archived emails for a case using optimized UNION query',
                'longHelp' => 'include/api/help/module_record_link_link_name_filter_get_help.html',
            ],
            'filterRelatedRecordsCount' => [
                'reqType' => 'GET',
                'path' => ['Cases', '?', 'link', 'archived_emails', 'filter', 'count'],
                'pathVars' => ['module', 'record', '', 'link_name', '', ''],
                'jsonParams' => ['filter'],
                'method' => 'filterRelatedCount',
                'shortHelp' => 'Counts filtered archived emails for a case using optimized UNION query',
                'longHelp' => 'include/api/help/module_record_link_link_name_filter_get_help.html',
            ],
            'filterRelatedRecordsLeanCount' => [
                'reqType' => 'GET',
                'minVersion' => '11.4',
                'path' => ['Cases', '?', 'link', 'archived_emails', 'filter', 'leancount'],
                'pathVars' => ['module', 'record', '', 'link_name', '', ''],
                'jsonParams' => ['filter'],
                'method' => 'filterRelatedLeanCount',
                'shortHelp' => 'Gets the "lean" count of filtered archived emails for a case using optimized UNION query',
                'longHelp' => 'include/api/help/module_record_link_link_name_filter_get_help.html',
            ],
            'listRelatedRecords' => [
                'reqType' => 'GET',
                'path' => ['Cases', '?', 'link', 'archived_emails'],
                'pathVars' => ['module', 'record', '', 'link_name'],
                'jsonParams' => ['filter'],
                'method' => 'filterRelated',
                'shortHelp' => 'Lists archived emails for a case using optimized UNION query',
                'longHelp' => 'include/api/help/module_record_link_link_name_filter_get_help.html',
            ],
            'listRelatedRecordsCount' => [
                'reqType' => 'GET',
                'path' => ['Cases', '?', 'link', 'archived_emails', 'count'],
                'pathVars' => ['module', 'record', '', 'link_name', ''],
                'jsonParams' => ['filter'],
                'method' => 'filterRelatedCount',
                'shortHelp' => 'Counts archived emails for a case using optimized UNION query',
                'longHelp' => 'include/api/help/module_record_link_link_name_filter_get_help.html',
            ],
            'listRelatedRecordsLeanCount' => [
                'reqType' => 'GET',
                'minVersion' => '11.4',
                'path' => ['Cases', '?', 'link', 'archived_emails', 'leancount'],
                'pathVars' => ['module', 'record', '', 'link_name', ''],
                'jsonParams' => ['filter'],
                'method' => 'filterRelatedLeanCount',
                'shortHelp' => 'Gets the "lean" count of archived emails for a case using optimized UNION query',
                'longHelp' => 'include/api/help/module_record_link_link_name_filter_get_help.html',
            ],
        ];
    }

    /**
     * Filter related archived emails using optimized UNION query approach
     * Uses standard SugarCRM infrastructure with performance optimization
     */
    public function filterRelated(ServiceBase $api, array $args)
    {
        $api->action = 'list';

        // Use our custom setup that returns the UNION query
        [$args, $unionQuery, $options, $linkSeed] = $this->filterRelatedSetup($api, $args);

        // Execute using standard runQuery
        return $this->runQuery($api, $args, $unionQuery, $options, $linkSeed);
    }

    /**
     * Custom filterRelatedSetup that builds proper UNION query
     * Overrides parent to control query construction
     * @return array
     */
    public function filterRelatedSetup(ServiceBase $api, array $args): array
    {
        $api->action = 'list';

        // Load the parent bean.
        $record = BeanFactory::retrieveBean($args['module'], $args['record']);
        if (empty($record)) {
            throw new SugarApiExceptionNotFound(
                sprintf('Could not find parent record %s in module: %s', $args['record'], $args['module'])
            );
        }

        if (!$record->ACLAccess('view')) {
            throw new SugarApiExceptionNotAuthorized(
                sprintf('No access to view records for module: %s', $args['module'])
            );
        }

        // Load the relationship.
        $linkName = $args['link_name'];
        $linkSeed = $this->getLinkBean($record, $linkName);

        $options = $this->parseArguments($api, $args, $linkSeed);

        // If they don't have fields selected we need to include any link fields
        // for this relationship
        if (empty($args['fields']) && is_array($linkSeed->field_defs)) {
            $relatedLinkName = $record->$linkName->getRelatedModuleLinkName();
            $options['linkDataFields'] = [];
            foreach ($linkSeed->field_defs as $field => $def) {
                if (empty($def['rname_link']) || empty($def['link'])) {
                    continue;
                }
                if ($def['link'] != $relatedLinkName) {
                    continue;
                }
                // It's a match
                $options['linkDataFields'][] = $field;
                $options['select'][] = $field;
            }
        }

        // In case the view parameter is set, reflect those fields in the
        // fields argument as well so formatBean only takes those fields
        // into account instead of every bean property.
        if (!empty($args['view'])) {
            $args['fields'] = $options['select'];
        } elseif (!empty($args['fields'])) {
            $args['fields'] = $this->normalizeFields($args['fields'], $options['displayParams']);
        }

        // return 'is_external_link' for records created by external users
        if ($this->hasExternalRecords($record, $linkName)) {
            // We'll need to handle this in our UNION queries
            $options['include_external_link'] = true;
        }

        if (isset($options['relate_collections'])) {
            $options = $this->removeRelateCollectionsFromSelect($options);
        }

        // fixing duplicates in the query is not needed since even if it selects many-to-many related records,
        // they are still filtered by one primary record, so the subset is at most one-to-many
        $options['skipFixQuery'] = true;

        // Create base options for individual queries (without LIMIT/OFFSET/ORDER BY)
        $baseOptions = $options;
        unset($baseOptions['limit'], $baseOptions['offset'], $baseOptions['order_by']);

        // For UNION queries, we need to ensure ORDER BY fields are in the SELECT list
        // Add them explicitly to the select option
        if (!empty($options['order_by'])) {
            foreach ($options['order_by'] as $orderBy) {
                $orderByField = $orderBy[0];
                // Skip id and date_modified as they're already included
                if ($orderByField != 'id' && $orderByField != 'date_modified') {
                    // Add to select if not already there
                    if (!in_array($orderByField, $baseOptions['select'])) {
                        $baseOptions['select'][] = $orderByField;
                    }
                }
            }
        }

        // Build the two queries without LIMIT/OFFSET
        $directQuery = $this->buildDirectRelationshipQuery($record, $linkSeed, $baseOptions, $args);
        $relatedQuery = $this->buildRelatedContactQuery($record, $linkSeed, $baseOptions, $args);

        // Create proper UNION with LIMIT/OFFSET/ORDER BY applied only to the final result
        $unionQuery = $this->createProperUnionQuery($directQuery, $relatedQuery, $options);

        return [$args, $unionQuery, $options, $linkSeed, $record, $directQuery];
    }

    /**
     * Create proper UNION query with LIMIT/OFFSET/ORDER BY applied only at the end
     */
    protected function createProperUnionQuery($directQuery, $relatedQuery, $options)
    {
        $unionQuery = parent::newSugarQuery(DBManagerFactory::getInstance('listviews'));

        // Add both queries to the union (they should not have their own LIMIT/OFFSET/ORDER BY)
        $unionQuery->union($directQuery, false);
        $unionQuery->union($relatedQuery, false);

        if (!empty($options['order_by'])) {
            $this->addOrderByForUnion($unionQuery, $options['order_by'], $options['nulls_last']);
        }

        // negative limit means no limit
        if (isset($options['limit']) && $options['limit'] >= 0) {
            // Add an extra record to the limit so we can detect if there are more records to be found
            $unionQuery->limit($options['limit'] + 1);
        }
        if (isset($options['offset'])) {
            $unionQuery->offset($options['offset']);
        }

        // getQueryObject already applied ordering and limit/offset from options
        // No need to manually apply them again

        return $unionQuery;
    }

    /**
     * Check if a field is sortable in UNION queries
     *
     * @param array $fieldDef Field definition from vardefs
     * @return bool True if field can be used in ORDER BY
     */
    protected function isSortableInUnion(array $fieldDef): bool
    {
        // Link and parent types are relationship metadata, not data columns
        $type = $fieldDef['type'] ?? null;
        if (!empty($type) && in_array($type, ['link', 'parent'])) {
            return false;
        }

        // Function-based fields are computed dynamically
        $source = $fieldDef['source'] ?? null;
        if (!empty($source) && $source === 'function') {
            return false;
        }

        // Pure non-db fields without database backing cannot be sorted
        if (!empty($source) && $source === 'non-db') {
            $hasDatabaseBacking = !empty($fieldDef['sort_on'])
                || !empty($fieldDef['db_concat_fields'])
                || !empty($fieldDef['rname_link'])
                || (!empty($fieldDef['rname']) && !empty($fieldDef['link']));

            return $hasDatabaseBacking;
        }

        return true;
    }

    /**
     * Add ORDER BY to UNION query with special handling for relate fields
     *
     * In UNION queries, relate fields need to be mapped to their actual column aliases
     * because they expand to multiple columns (first_name, last_name, etc.) but the
     * ORDER BY references a virtual field name that doesn't exist in the result set.
     *
     * @param SugarQuery $q The UNION query
     * @param array $orderByOption Array of [field, direction] pairs
     * @param bool $nullsLast Whether to put NULL values last
     */
    protected function addOrderByForUnion(SugarQuery $q, array $orderByOption, bool $nullsLast = false): void
    {
        // Get the from bean from the first query in the union
        $fromBean = null;
        if ($q->union !== null) {
            $unionQueries = $q->union->getQueries();
            if (!empty($unionQueries)) {
                $fromBean = $unionQueries[0]['query']->getFromBean();
            }
        }

        $hasValidOrderBy = false;
        $firstDirection = !empty($orderByOption[0][1]) ? $orderByOption[0][1] : 'DESC';

        foreach ($orderByOption as $orderBy) {
            $orderByField = $orderBy[0];
            $useNullsLast = $nullsLast;

            // ID and date_modified are used to give some order to the system
            if ($orderByField != 'date_modified' && $orderByField != 'id') {
                // For relate fields, we need to map to the actual column alias
                if ($fromBean && !empty($fromBean->field_defs[$orderByField])) {
                    $fieldDef = $fromBean->field_defs[$orderByField];

                    // Skip non-sortable field types
                    if (!$this->isSortableInUnion($fieldDef)) {
                        continue;
                    }

                    if (!empty($fieldDef['type']) && $fieldDef['type'] == 'relate') {
                        // Get the link and rname
                        $linkName = $fieldDef['link'] ?? null;
                        $rname = $fieldDef['rname'] ?? null;
                        $relatedModule = $fieldDef['module'] ?? null;

                        if ($linkName && $rname && $relatedModule) {
                            // Load the related bean to check for sort_on field
                            $relatedBean = BeanFactory::newBean($relatedModule);

                            if (!empty($relatedBean->field_defs[$rname]['sort_on'])) {
                                // Use the sort_on field (e.g., 'last_name' for full_name)
                                $sortField = $relatedBean->field_defs[$rname]['sort_on'];
                            } else {
                                // Fall back to the rname field itself
                                $sortField = $rname;
                            }

                            // Construct the alias that SugarQuery uses: rel_{original_field}_{sort_field}
                            $orderByField = 'rel_' . $orderBy[0] . '_' . $sortField;

                            // Disable nullsLast for synthetic aliases
                            $useNullsLast = false;
                        }
                    }
                }

                // Verify the field exists and is accessible
                self::verifyField($q, $orderBy[0]);
            }

            $q->orderBy($orderByField, $orderBy[1], $useNullsLast);
            $hasValidOrderBy = true;
        }

        // If all fields were non-sortable, fallback to sorting by id
        if (!$hasValidOrderBy) {
            $q->orderBy('id', $firstDirection, $nullsLast);
        }
    }

    /**
     * Build Query 1: Direct relationship query using standard SugarQuery
     */
    protected function buildDirectRelationshipQuery($record, $linkSeed, $options, $args)
    {
        // Use standard getQueryObject for proper field selection, ordering, etc.
        $query = self::getQueryObject($linkSeed, $options);

        // Add direct relationship join - simple INNER JOIN with emails_beans
        $this->addDirectRelationshipJoin($query, $record);

        $this->applyStandardFilters($query, $args);

        return $query;
    }

    /**
     * Add direct relationship join for Query 1
     * Simple INNER JOIN with emails_beans table
     */
    protected function addDirectRelationshipJoin($query, $record)
    {
        // directly assigned emails
        $subQuery = sprintf(
            "SELECT eb.email_id FROM emails_beans eb WHERE eb.bean_module = 'Cases' AND eb.bean_id = %s AND eb.deleted = 0",
            $record->db->quoted($record->id)
        );

        $jta = $query->getJoinTableAlias('archived_emails');
        $query->joinTable("($subQuery)", ['alias' => $jta, 'joinType' => 'INNER'])
            ->on()->equalsField('emails.id', $jta . '.email_id');
    }

    /**
     * Build Query 2: Related/Contact relationship query using standard SugarQuery
     */
    protected function buildRelatedContactQuery($record, $linkSeed, $options, $args)
    {
        // Use standard getQueryObject for proper field selection, ordering, etc.
        $query = self::getQueryObject($linkSeed, $options);

        // Add the complex EXISTS condition with UNION ALL subqueries
        $this->addRelatedContactsExists($query, $record);

        $this->applyStandardFilters($query, $args);

        return $query;
    }

    /**
     * Build a SugarQuery for emails whose subject contains the case number.
     *
     * @param SugarBean $record The Case record
     * @param array $args API arguments, including filters
     * @return SugarQuery
     */
    protected function buildSubjectEmailsQuery($record, $args)
    {
        $query = new SugarQuery();
        $query->from(BeanFactory::newBean('Emails'));
        $query->select(['id']);
        $query->where()->like('name', '%' . $this->getSubjectMacro($record) . '%');

        $this->applyStandardFilters($query, $args);

        return $query;
    }

    /**
     * Apply standard filters from API arguments to a SugarQuery.
     *
     * @param SugarQuery $query The query to apply filters to
     * @param array $args API arguments containing optional 'filter'
     * @return void
     */
    protected function applyStandardFilters(SugarQuery $query, array $args): void
    {
        if (!empty($args['filter']) && is_array($args['filter'])) {
            self::addFilters($args['filter'], $query->where(), $query);
        }
    }

    /**
     * Add EXISTS condition with UNION ALL subqueries for Query 2
     * This is the core optimization that finds related contacts and email addresses
     */
    protected function addRelatedContactsExists($query, $record)
    {
        $caseId = $record->db->quoted($record->id);

        $subjectMacroQuery = $this->getSubjectMacroQuery($record);
        $existsSubQuery = CaseEmailsLink::getExistsSubQuery($caseId);
        $sql = "$subjectMacroQuery AND EXISTS ($existsSubQuery)";

        $query->where()->addRaw($sql);
    }

    /**
     * Get case subject matching condition for Query
     */
    protected function getSubjectMacroQuery($record)
    {
        $subjectMacro = $this->getSubjectMacro($record);
        $subjectMacroSql = DBManagerFactory::getInstance('listviews')->sqlLikeString($subjectMacro, '%', false);
        $quotedSubjectMacro = $record->db->quoted('%' . $subjectMacroSql . '%');

        return "emails.name LIKE {$quotedSubjectMacro}";
    }

    /**
     * Get case subject macro
     * @param SugarBean $record Case
     * @return string
     */
    protected function getSubjectMacro($record)
    {
        if (empty($record->case_number)) {
            $GLOBALS['log']->info('Case number is empty, skipping subject macro match for archived emails.');
            return '1=1';
        }

        return str_replace('%1', $record->case_number, $record->getEmailSubjectMacro());
    }

    /**
     * Count related archived emails using optimized UNION query approach
     */
    public function filterRelatedCount(ServiceBase $api, array $args)
    {
        $api->action = 'list';

        [$args,,,, $record, $directEmailsQuery] = $this->filterRelatedSetup($api, $args);

        // direct emails
        $directEmailsQuery->select()->selectReset()->field('id');
        $directCompiled = $directEmailsQuery->compile();

        // subject emails
        $subjectEmailsQuery = $this->buildSubjectEmailsQuery($record, $args);
        $subjectCompiled = $subjectEmailsQuery->compile();

        $quotedCaseId = $record->db->quoted($record->id);
        $relatedEmailsSql = CaseEmailsLink::getExistsSubQuery($quotedCaseId, null);

        $unionSql = "
            {$directCompiled->getSQL()}
            UNION
                SELECT se.id
                FROM ({$subjectCompiled->getSQL()}) se
                INNER JOIN (
                    SELECT DISTINCT email_id
                    FROM ($relatedEmailsSql) re
                ) related_emails ON related_emails.email_id = se.id
            ";

        $sql = "SELECT COUNT(*) FROM ($unionSql) q";

        $params = [
            ...$directCompiled->getParameters(),
            ...$subjectCompiled->getParameters(),
        ];
        $result = DBManagerFactory::getConnection('listviews')->executeQuery($sql, $params);
        $count = (int) $result->fetchOne();

        return ['record_count' => $count];
    }

    /**
     * Lean count for related archived emails using optimized UNION query approach
     */
    public function filterRelatedLeanCount(ServiceBase $api, array $args)
    {
        if (isset($args['max_num'])) {
            $args['max_num'] = (int)$args['max_num'];
        }
        if (!isset($args['max_num']) || $args['max_num'] <= 0) {
            throw new SugarApiExceptionMissingParameter('max_num parameter is missing or invalid');
        }

        $api->action = 'list';
        $args['fields'] = 'id';
        $args['view'] = '';

        // Use our custom setup
        [$args, $unionQuery] = $this->filterRelatedSetup($api, $args);

        // Override limit for lean count check
        $unionQuery->limit($args['max_num'] + 1);
        $unionQuery->orderByReset();

        // Execute and count results
        $stmt = $unionQuery->compile()->execute();
        $rows = $stmt->fetchFirstColumn();
        $count = count($rows);

        return [
            'record_count' => $count > $args['max_num'] ? $args['max_num'] : $count,
            'has_more' => $count > $args['max_num'],
        ];
    }
}
