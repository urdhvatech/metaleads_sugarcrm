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

/*********************************************************************************
 * Description:  TODO: To be written.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/

use Sugarcrm\Sugarcrm\Audit\Formatter as AuditFormatter;
use Sugarcrm\Sugarcrm\DependencyInjection\Container;

require_once 'modules/Audit/field_assoc.php';

class Audit extends SugarBean
{
    public $module_dir = 'Audit';
    public $object_name = 'Audit';

    public $disable_vardefs = true;
    public $disable_custom_fields = true;

    public $genericAssocFieldsArray = [];
    public $moduleAssocFieldsArray = [];

    private $fieldDefs;

    // This is used to retrieve related fields from form posts.
    public $additional_column_fields = [];

    public function __construct()
    {
        parent::__construct();
        $this->team_id = 1; // make the item globally accessible

        // load up the assoc fields array from globals
        $this->getAssocFieldsArray();
        $this->getFieldDefs();
    }

    public $new_schema = true;

    public function get_summary_text()
    {
        return $this->name;
    }

    public function fill_in_additional_list_fields()
    {
    }

    public function fill_in_additional_detail_fields()
    {
    }

    public function fill_in_additional_parent_fields()
    {
    }

    public function get_list_view_data($filter_fields = [])
    {
    }

    public function get_audit_link()
    {
    }

    /**
     * wrapper for evil global var
     * @protected
     */
    protected function getAssocFieldsArray()
    {
        global $genericAssocFieldsArray, $moduleAssocFieldsArray;
        $this->genericAssocFieldsArray = (!empty($genericAssocFieldsArray) &&
            is_array($genericAssocFieldsArray)) ? $genericAssocFieldsArray : [];

        $this->moduleAssocFieldsArray = (!empty($moduleAssocFieldsArray) &&
            is_array($moduleAssocFieldsArray)) ? $moduleAssocFieldsArray : [];
    }

    /**
     * wrapper to get fielddefs for class - punch out for unit testing
     * @protected
     */
    protected function getFieldDefs()
    {
        $dictionary = [];
        if (empty($this->fieldDefs)) {
            require 'metadata/audit_templateMetaData.php';
            $this->fieldDefs = $dictionary['audit']['fields'];
            // defined in SugarBean
            $this->field_defs = $this->fieldDefs;
        }
        return $this->fieldDefs;
    }

    /**
     * This method gets the Audit log and formats it specifically for the API.
     * @param SugarBean $bean
     * @return array
     */
    public function getAuditLog(SugarBean $bean): array
    {
        global $current_user;
        if (!$bean->is_AuditEnabled() ||
            $current_user->portal_only ||
            $bean->id === null) {
            return [];
        }

        $query = $this->getAuditQuery($bean);

        $stmt = $query->execute();

        if (empty($stmt)) {
            return [];
        }

        return $this->fetchAuditLogRows($bean, $stmt);
    }

    /**
     * This method gets the Audit log and formats it specifically for the API.
     * @param SugarBean $bean
     * @param array $options
     * @return array
     */
    public function getAuditLogChunk(SugarBean $bean, array $options): array
    {
        global $current_user;
        if (!$bean->is_AuditEnabled() || $current_user->portal_only) {
            return [];
        }
        $query = $this->getAuditQuery($bean);
        $this->applyOrderBy($query, $options['order_by'] ?? '', $options['order_direction'] ?? '');
        // nagative limit means no limit
        if ($options['limit'] >= 0) {
            // Add an extra record to the limit so we can detect if there are more records to be found
            $query->setMaxResults($options['limit'] + 1);
            $query->setFirstResult($options['offset']);
        }

        $stmt = $query->execute();

        if (empty($stmt)) {
            return [];
        }

        return $this->fetchAuditLogRows($bean, $stmt);
    }

    /**
     * Wrapper around static method self::getAssociatedFieldName($fieldName, $fieldValue)
     * @param string $fieldName
     * @param string $fieldValue
     * @return string|null
     */
    protected function getNameForId($fieldName, $fieldValue)
    {
        return self::getAssociatedFieldName($fieldName, $fieldValue);
    }

    /**
     * Handles relate field.
     *
     * @param SugarBean $bean
     * @param array $row A row of database-queried audit table results.
     * @return boolean
     */
    protected function handleRelateField($bean, &$row)
    {
        $fields = $bean->getAuditEnabledFieldDefinitions(true);

        if (isset($fields[$row['field_name']]) && $fields[$row['field_name']]['type'] === 'relate') {
            $field = $fields[$row['field_name']];
            $row['field_name'] = $field['name'];

            if (!empty($row['before_value_string'])) {
                $beforeBean = BeanFactory::getBean($field['module'], $row['before_value_string']);
                if (!empty($beforeBean)) {
                    $row['before_value_string'] = $beforeBean->get_summary_text();
                }
            }

            if (!empty($row['after_value_string'])) {
                $afterBean = BeanFactory::getBean($field['module'], $row['after_value_string']);
                if (!empty($afterBean)) {
                    $row['after_value_string'] = $afterBean->get_summary_text();
                }
            }

            $row = $this->formatRowForApi($row);
            return true;
        }
        return false;
    }

    /**
     * Handles the special-cased `team_set_id` field when fetching rows for the
     * Audit Log API. It is needed in order to prevent processing this field as
     * type `relate`.
     *
     * @param array $row A row of database-queried audit table results.
     * @return array The API-formatted $row.
     */
    protected function handleTeamSetField($row = [])
    {
        if (empty($row)) {
            return $row;
        }

        require_once 'modules/Teams/TeamSetManager.php';
        $row['before_value_string'] = TeamSetManager::getTeamsFromSet($row['before_value_string']);
        $row['after_value_string'] = TeamSetManager::getTeamsFromSet($row['after_value_string']);

        $row = $this->formatRowForApi($row);
        return $row;
    }

    /**
     * Formats a db-fetched row for the Audit Log API with `before` and `after`
     * values.
     *
     * @param array $row A row of database-queried audit table results.
     * @return array The API-formatted $row.
     */
    protected function formatRowForApi($row = [])
    {
        if (empty($row)) {
            return $row;
        }

        if (empty($row['before_value_string']) && empty($row['after_value_string'])) {
            $row['before'] = $row['before_value_text'];
            $row['after'] = $row['after_value_text'];
        } else {
            $row['before'] = $row['before_value_string'];
            $row['after'] = $row['after_value_string'];
        }

        unset($row['before_value_string']);
        unset($row['before_value_text']);
        unset($row['after_value_string']);
        unset($row['after_value_text']);

        return $row;
    }

    /**
     * Formats datetime value according to type, or returns it as is in case it's empty
     *
     * @param mixed $value
     * @param string $type
     *
     * @return mixed
     */
    protected function formatDateTime($value, $type)
    {
        global $timedate;

        if ($value) {
            $obj = $timedate->fromDbType($value, $type);
            $value = $timedate->asIso($obj);
        }

        return $value;
    }

    public static function get_audit_list()
    {
        $dictionary = [];
        global $focus, $genericAssocFieldsArray, $moduleAssocFieldsArray, $current_user, $timedate, $app_strings;
        $audit_list = [];
        if (!empty($_REQUEST['record'])) {
            $result = $focus->retrieve($_REQUEST['record']);

            if ($result == null || !$focus->ACLAccess('', $focus->isOwner($current_user->id))) {
                sugar_die($app_strings['ERROR_NO_RECORD']);
            }
        }

        if ($focus->is_AuditEnabled()) {
            $order = ' order by ' . $focus->get_audit_table_name() . '.date_created desc';//order by contacts_audit.date_created desc
            $query = 'SELECT ' . $focus->get_audit_table_name() . '.*, users.user_name FROM ' . $focus->get_audit_table_name() . ', users WHERE ' . $focus->get_audit_table_name() . '.created_by = users.id AND ' . $focus->get_audit_table_name() . ".parent_id = '$focus->id'" . $order;

            $result = $focus->db->query($query);
            // We have some data.
            require 'metadata/audit_templateMetaData.php';
            $fieldDefs = $dictionary['audit']['fields'];
            while (($row = $focus->db->fetchByAssoc($result)) != null) {
                if (!ACLField::hasAccess($row['field_name'], $focus->module_dir, $GLOBALS['current_user']->id, $focus->isOwner($GLOBALS['current_user']->id))) {
                    continue;
                }

                //If the team_set_id field has a log entry, we retrieve the list of teams to display
                $viewName = array_search($row['field_name'], Team::$nameTeamsetMapping);
                if ($viewName) {
                    $row['field_name'] = $viewName;
                    require_once 'modules/Teams/TeamSetManager.php';
                    $row['before_value_string'] = TeamSetManager::getCommaDelimitedTeams($row['before_value_string']);
                    $row['after_value_string'] = TeamSetManager::getCommaDelimitedTeams($row['after_value_string']);
                }
                $temp_list = [];

                foreach ($fieldDefs as $field) {
                    if (array_key_exists($field['name'], $row)) {
                        if (($field['name'] == 'before_value_string' || $field['name'] == 'after_value_string') &&
                            (array_key_exists($row['field_name'], $genericAssocFieldsArray) || (!empty($moduleAssocFieldsArray[$focus->object_name]) && array_key_exists($row['field_name'], $moduleAssocFieldsArray[$focus->object_name])))
                        ) {
                            $temp_list[$field['name']] = self::getAssociatedFieldName($row['field_name'], $row[$field['name']]);
                        } else {
                            $temp_list[$field['name']] = $row[$field['name']];
                        }

                        if ($field['name'] == 'date_created') {
                            $date_created = '';
                            if (!empty($temp_list[$field['name']])) {
                                $date_created = $timedate->to_display_date_time($temp_list[$field['name']]);
                                $date_created = !empty($date_created) ? $date_created : $temp_list[$field['name']];
                            }
                            $temp_list[$field['name']] = $date_created;
                        }
                        if (($field['name'] == 'before_value_string' || $field['name'] == 'after_value_string') && ($row['data_type'] == 'enum' || $row['data_type'] == 'multienum')) {
                            global $app_list_strings;
                            $enum_keys = unencodeMultienum($temp_list[$field['name']]);
                            $enum_values = [];
                            foreach ($enum_keys as $enum_key) {
                                if (isset($focus->field_defs[$row['field_name']]['options'])) {
                                    $domain = $focus->field_defs[$row['field_name']]['options'];
                                    if (isset($app_list_strings[$domain][$enum_key])) {
                                        $enum_values[] = $app_list_strings[$domain][$enum_key];
                                    }
                                }
                            }
                            if (!empty($enum_values)) {
                                $temp_list[$field['name']] = implode(', ', $enum_values);
                            }
                            if ($temp_list['data_type'] === 'date') {
                                $temp_list[$field['name']] = $timedate->to_display_date($temp_list[$field['name']], false);
                            }
                        } elseif (($field['name'] == 'before_value_string' || $field['name'] == 'after_value_string') && ($row['data_type'] == 'datetimecombo')) {
                            if (!empty($temp_list[$field['name']]) && $temp_list[$field['name']] != 'NULL') {
                                $temp_list[$field['name']] = $timedate->to_display_date_time($temp_list[$field['name']]);
                            } else {
                                $temp_list[$field['name']] = '';
                            }
                        } elseif ($field['name'] == 'field_name') {
                            global $mod_strings;
                            if (isset($focus->field_defs[$row['field_name']]['vname'])) {
                                $label = $focus->field_defs[$row['field_name']]['vname'];
                                $temp_list[$field['name']] = translate($label, $focus->module_dir);
                            }
                        }
                    }
                }

                $temp_list['created_by'] = $row['user_name'];
                $audit_list[] = $temp_list;
            }
        }

        return $audit_list;
    }

    /**
     * Return a more readable name for an id
     * @param {String} $fieldName
     * @param {String} $fieldValue
     * @return string|null
     */
    public static function getAssociatedFieldName($fieldName, $fieldValue)
    {
        global $focus, $genericAssocFieldsArray, $moduleAssocFieldsArray;

        if (!empty($moduleAssocFieldsArray[$focus->object_name])
            && array_key_exists($fieldName, $moduleAssocFieldsArray[$focus->object_name])
        ) {
            $assocFieldsArray = $moduleAssocFieldsArray[$focus->object_name];
        } elseif (array_key_exists($fieldName, $genericAssocFieldsArray)) {
            $assocFieldsArray = $genericAssocFieldsArray;
        } else {
            return $fieldValue;
        }
        $field_arr = $assocFieldsArray[$fieldName];
        $sql = <<<SQL
SELECT %s FROM {$field_arr['table_name']}
WHERE {$field_arr['select_field_join']} = ?
SQL;

        $db = DBManagerFactory::getInstance();
        $row = $db->getConnection()
            ->executeQuery(
                sprintf(
                    $sql,
                    is_array($field_arr['select_field_name']) ?
                        implode(',', $field_arr['select_field_name']) : $field_arr['select_field_name']
                ),
                [$fieldValue]
            )->fetchAssociative();

        if ($row === false) {
            return null;
        }

        if (is_array($field_arr['select_field_name'])) {
            $returnVal = '';
            foreach ($field_arr['select_field_name'] as $col) {
                $returnVal .= $row[$col] . ' ';
            }

            return $returnVal;
        } else {
            return $row[$field_arr['select_field_name']];
        }
    }


    /**
     * Get audit log records matching a full-text search term across key fields.
     * The search is applied server-side, so it works across all pages.
     *
     * @param SugarBean $bean
     * @param array $options Pagination options (limit, offset)
     * @param string $searchTerm Text to search for
     * @return array
     */
    public function getAuditLogChunkWithSearch(SugarBean $bean, array $options, string $searchTerm): array
    {
        global $current_user;
        if (!$bean->is_AuditEnabled() || $current_user->portal_only) {
            return [];
        }
        $query = $this->getAuditQuery($bean);
        $sanitizedSearchTerm = trim(str_replace('%', '', $searchTerm));
        $matchingFieldNames = [];
        $emailFieldNames = [];
        if ($sanitizedSearchTerm !== '') {
            $matchingFieldNames = $this->resolveFieldNamesFromLabel($bean, $sanitizedSearchTerm);
            $emailFieldNames = $this->resolveEmailFieldNames($bean);
        }
        $this->applySearchTerm($query, $sanitizedSearchTerm, $matchingFieldNames, $emailFieldNames);
        $this->applyOrderBy($query, $options['order_by'] ?? '', $options['order_direction'] ?? '');

        if ($options['limit'] >= 0) {
            $query->setMaxResults($options['limit'] + 1);
            $query->setFirstResult($options['offset']);
        }

        $stmt = $query->execute();
        if (empty($stmt)) {
            return [];
        }
        return $this->fetchAuditLogRows($bean, $stmt);
    }

    /**
     * Get the count of audit log records matching a full-text search term.
     *
     * @param SugarBean $bean
     * @param string $searchTerm Text to search for
     * @return int
     */
    public function getAuditLogCountWithSearch(SugarBean $bean, string $searchTerm): int
    {
        global $current_user;
        if (!$bean->is_AuditEnabled() || $current_user->portal_only) {
            return 0;
        }

        $auditTable = $bean->get_audit_table_name();
        $qb = \DBManagerFactory::getInstance()->getConnection()->createQueryBuilder();

        $query = $qb->select('COUNT(atab.id) AS total')
            ->from($auditTable, 'atab')
            ->leftJoin('atab', 'users', 'usr', 'usr.id = atab.created_by');

        if ($bean->id !== null) {
            $query->where($qb->expr()->eq('atab.parent_id', $qb->createPositionalParameter($bean->id)));
        }

        $sanitizedSearchTerm = trim(str_replace('%', '', $searchTerm));
        $matchingFieldNames = [];
        $emailFieldNames = [];
        if ($sanitizedSearchTerm !== '') {
            $matchingFieldNames = $this->resolveFieldNamesFromLabel($bean, $sanitizedSearchTerm);
            $emailFieldNames = $this->resolveEmailFieldNames($bean);
        }
        $this->applySearchTerm($query, $sanitizedSearchTerm, $matchingFieldNames, $emailFieldNames);

        $result = $query->execute()->fetchAssociative();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Whitelist of sortable front-end field names mapped to DB column expressions.
     * Only these fields may be passed as order_by via the API.
     */
    private const SORTABLE_COLUMNS = [
        'field_name'           => 'atab.field_name',
        'created_by_username'  => 'usr.user_name',
        'date_created'         => 'atab.date_created',
    ];

    /**
     * Apply server-side ordering to the query, replacing the default date_created sort.
     * Only columns listed in SORTABLE_COLUMNS are accepted; all others are ignored.
     *
     * @param \Doctrine\DBAL\Query\QueryBuilder $query
     * @param string $field  Front-end field name (e.g. "date_created")
     * @param string $direction  "asc" or "desc"
     */
    private function applyOrderBy(\Doctrine\DBAL\Query\QueryBuilder $query, string $field, string $direction): void
    {
        $column = self::SORTABLE_COLUMNS[$field] ?? '';
        if ($column === '') {
            return;
        }
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $query->orderBy($column, $dir)
              ->addOrderBy('atab.id', $dir);
    }

    /**
     * Apply a full-text search term as an OR condition across auditable fields.
     *
     * Matching strategy:
     * - before_value_string / after_value_string: substring LIKE '%term%'
     * - field_name: exact IN match against $matchingFieldNames, which is the
     *   pre-resolved list of raw DB column names whose translated UI label
     *   contains the search term (see resolveFieldNamesFromLabel).
     * - email fields: an EXISTS subquery against email_addresses resolves UUIDs
     *   stored in before/after_value_string to actual email addresses, so that
     *   searching "phone" matches "phone.qa1@example.tv" (see resolveEmailFieldNames).
     *
     * Searching raw field_name with LIKE is intentionally avoided: internal
     * names like "last_interaction_parent_id" look nothing like the displayed
     * label "Record ID" and produce false positives.
     *
     * The entire search term is treated as a single phrase — it is NOT split
     * on whitespace.
     *
     * @param \Doctrine\DBAL\Query\QueryBuilder $query
     * @param string $searchTerm
     * @param array<string> $matchingFieldNames Raw DB field names whose label matches the term
     * @param array<string> $emailFieldNames Field names of type 'email' (values stored as email_addresses UUIDs)
     */
    private function applySearchTerm(
        \Doctrine\DBAL\Query\QueryBuilder $query,
        string $searchTerm,
        array $matchingFieldNames = [],
        array $emailFieldNames = [],
    ): void {
        if ($searchTerm === '') {
            return;
        }

        // Strip literal % so we don't accidentally widen the LIKE pattern
        // (no ESCAPE clause is used: SQL _ still acts as wildcard, which is
        //  cross-DB safe and acceptable here)
        $safe = str_replace('%', '', $searchTerm);
        if ($safe === '') {
            return;
        }
        $like = '%' . $safe . '%';
        $expr = $query->expr();

        $orConditions = [
            $expr->like('atab.before_value_string', $query->createPositionalParameter($like)),
            $expr->like('atab.after_value_string', $query->createPositionalParameter($like)),
        ];

        if (!empty($matchingFieldNames)) {
            $placeholders = array_map(
                fn(string $fn) => $query->createPositionalParameter($fn),
                $matchingFieldNames,
            );
            $orConditions[] = 'atab.field_name IN (' . implode(', ', $placeholders) . ')';
        }

        if (!empty($emailFieldNames)) {
            // Email audit rows store an email_addresses.id UUID, not the
            // address text. Resolve via EXISTS so "phone" matches
            // "phone.qa1@example.tv" without false positives from other fields.
            //
            // LIKE is case-sensitive on DB2, Oracle and MSSQL, so wrap both
            // sides in LOWER() to guarantee case-insensitive matching across
            // all supported databases.
            $fieldPlaceholders = array_map(
                fn(string $fn) => $query->createPositionalParameter($fn),
                $emailFieldNames,
            );
            $likePlaceholder = $query->createPositionalParameter($like);
            $orConditions[] = sprintf(
                'EXISTS ('
                . 'SELECT 1 FROM email_addresses ea '
                . 'WHERE atab.field_name IN (%s) '
                . 'AND (ea.id = atab.before_value_string OR ea.id = atab.after_value_string) '
                . 'AND LOWER(ea.email_address) LIKE LOWER(%s)'
                . ')',
                implode(', ', $fieldPlaceholders),
                $likePlaceholder,
            );
        }

        $query->andWhere($expr->or(...$orConditions));
    }

    /**
     * Build the list of raw DB field names (atab.field_name values) whose
     * translated UI label contains the given search term (case-insensitive).
     *
     * The UI displays translated labels, not raw column names, so to support
     * searching by label we must resolve the mapping at query time.
     *
     * Team-set fields are a special case: the DB stores "team_set_id" but the
     * UI label comes from the "team_name" virtual field.  We resolve this via
     * Team::$nameTeamsetMapping so that searching "Team" correctly matches the
     * team_set_id rows.
     *
     * @param SugarBean $bean
     * @param string $searchTerm Already-sanitised (no %) search phrase
     * @return array<string> Unique list of DB field names
     */
    private function resolveFieldNamesFromLabel(SugarBean $bean, string $searchTerm): array
    {
        $result = [];
        $lowerTerm = sugarStrToLower($searchTerm);

        foreach ($bean->field_defs as $fieldName => $fieldDef) {
            if (empty($fieldDef['vname'])) {
                continue;
            }
            $label = translate($fieldDef['vname'], $bean->module_dir);
            if (!is_string($label) || $label === '') {
                continue;
            }
            if (!str_contains(sugarStrToLower($label), $lowerTerm)) {
                continue;
            }
            // Team virtual fields (e.g. "team_name") are stored under a
            // different column name (e.g. "team_set_id") in the audit table.
            $dbFieldName = Team::$nameTeamsetMapping[$fieldName] ?? $fieldName;
            if (!in_array($dbFieldName, $result, true)) {
                $result[] = $dbFieldName;
            }
        }

        return $result;
    }

    /**
     * Build the list of field names that have type 'email' in the bean's
     * field_defs. These fields store email_addresses UUIDs in the audit table
     * instead of the address text, so they require a separate email_addresses
     * EXISTS subquery for search to work correctly.
     *
     * @param SugarBean $bean
     * @return array<string>
     */
    private function resolveEmailFieldNames(SugarBean $bean): array
    {
        $result = [];
        foreach ($bean->field_defs as $fieldName => $fieldDef) {
            if (($fieldDef['type'] ?? '') === 'email') {
                $result[] = $fieldName;
            }
        }
        return $result;
    }

    /**
     * @param SugarBean $bean
     * @return \Doctrine\DBAL\Query\QueryBuilder|\Sugarcrm\Sugarcrm\Dbal\Query\QueryBuilder
     * @throws Exception
     */
    private function getAuditQuery(SugarBean $bean)
    {
        $auditTable = $bean->get_audit_table_name();
        $qb = \DBManagerFactory::getInstance()->getConnection()->createQueryBuilder();
        $expr = $qb->expr();

        $query = $qb->select(
            ['atab.*, ae.source', 'ae.type AS event_type', 'usr.user_name AS created_by_username', 'ae.impersonated_by']
        )->from($auditTable, 'atab')
            ->leftJoin('atab', 'audit_events', 'ae', 'ae.id = atab.event_id')
            ->leftJoin('atab', 'users', 'usr', 'usr.id = atab.created_by')
            ->orderBy('atab.date_created', 'DESC')
            ->addOrderBy('atab.id', 'DESC');

        if ($bean->id !== null) {
            $query->where($expr->eq('atab.parent_id', $qb->createPositionalParameter($bean->id)));
        }
        return $query;
    }

    /**
     * @param SugarBean $bean
     * @param Doctrine\DBAL\Result $result
     * @return array
     */
    private function fetchAuditLogRows(SugarBean $bean, \Doctrine\DBAL\Result $result): array
    {
        $fieldDefs = $this->fieldDefs;
        $aclCheckContext = ['bean' => $bean];
        $rows = [];
        while ($row = $result->fetchAssociative()) {
            if (!SugarACL::checkField($bean->module_dir, $row['field_name'], 'access', $aclCheckContext)) {
                continue;
            }

            //convert date
            $dateCreated = $GLOBALS['timedate']->fromDbType($bean->db->fromConvert($row['date_created'], 'datetime'), 'datetime');
            $row['date_created'] = $GLOBALS['timedate']->asIso($dateCreated);

            $row['source'] = json_decode($row['source'], true);

            $viewName = array_search($row['field_name'], Team::$nameTeamsetMapping);
            if ($viewName) {
                $row['field_name'] = $viewName;
                $rows[] = $this->handleTeamSetField($row);
                continue;
            }

            if ($this->handleRelateField($bean, $row)) {
                $rows[] = $row;
                continue;
            }

            // look for opportunities to relate ids to name values.
            if (!empty($this->genericAssocFieldsArray[$row['field_name']]) ||
                !empty($this->moduleAssocFieldsArray[$bean->object_name][$row['field_name']])
            ) {
                foreach ($fieldDefs as $field) {
                    if (in_array($field['name'], ['before_value_string', 'after_value_string'])) {
                        $row[$field['name']] =
                            $this->getNameForId($row['field_name'], $row[$field['name']]);
                    }
                }
            }

            $rows[] = $this->formatRowForApi($row);
        }

        Container::getInstance()->get(AuditFormatter::class)->formatRows($rows);

        return $rows;
    }

    /**
     * Get the count of audit log records for a bean.
     *
     * @param SugarBean $bean The bean to get audit count for
     * @return int The count of audit records
     */
    public function getAuditLogCount(SugarBean $bean): int
    {
        global $current_user;
        if (!$bean->is_AuditEnabled() || $current_user->portal_only) {
            return 0;
        }

        $auditTable = $bean->get_audit_table_name();
        $qb = \DBManagerFactory::getInstance()->getConnection()->createQueryBuilder();

        $query = $qb->select('COUNT(atab.id) as total')
            ->from($auditTable, 'atab');

        if ($bean->id !== null) {
            $query->where($qb->expr()->eq('atab.parent_id', $qb->createNamedParameter($bean->id)));
        }

        $result = $query->execute()->fetch();
        return (int) ($result['total'] ?? 0);
    }
}
