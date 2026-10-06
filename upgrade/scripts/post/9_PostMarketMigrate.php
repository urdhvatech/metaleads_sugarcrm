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

class SugarUpgradePostMarketMigrate extends UpgradeScript
{
    public $order = 9000;

    public $type = self::UPGRADE_CUSTOM;

    /**
     * List of field to migrate for each module
     */
    public $fieldsToMove = [
        'campaigns' => [
            'peoplewhoopened',
            'peoplewhoclicked',
            'bounced',
            'notreported',
            'delivered',
            'social',
            'sent',
            'postdate',
            'forwards',
            'unopened',
            'unsubscribed',
            'totalopens',
            'totalclicks',
        ],
        'contacts' => [
            'sf_lastactivity_default',
        ],
        'leads' => [
            'sf_lastactivity_default',
        ],
        'prospects' => [
            'deliverystatus',
            'deliverymessage',
            'forms',
            'friendforward',
            'opened',
            'clicked',
            'unsub',
        ],
    ];

    /**
     * List of field to delete for each module
     */
    public $fieldsToDelete = [
        'emails' => [
            'salesfusionemail',
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        if (!$this->shouldRun()) {
            return;
        }
        $this->log('Start Post Market Migration');
        $this->updateReportsRecords();
        $this->updateBPMRecords();
        $this->deleteUnusedFields();
        $this->moveDataFromCstmTables();
        $this->deleteDashboardDashlets();
        $this->restoreFocusDrawerDashboards();
        $this->restoreMarketSettings();
        $this->log('Finish Post Market Migration');
    }

    /**
     * Check if the upgrade script should be executed
     * @return bool
     */
    protected function shouldRun(): bool
    {
        $query = new SugarQuery();
        $query->from(\BeanFactory::newBean('UpgradeHistory'), ['add_deleted' => false]);
        $query->where()->starts('id_name', 'ext_rest_salesfusion');
        $result = $query->execute();
        return ((is_countable($result) ? count($result) : 0 > 0) ||
                file_exists('cache/upgrades/settings.bak')) &&
            version_compare($this->from_version, '25.1.0', '<');
    }

    /**
     * Update fields name in BPM tables
     *
     * @return void
     */
    protected function updateBPMRecords()
    {
        $this->updateActivityDefinitionFields();

        $dataToUpdate = [
            ['pmse_Emails_Templates', ['subject', 'body_html'], 'base_module'],
            ['pmse_BpmDynaForm', ['dyn_view_defs'], 'dyn_module'],
            ['pmse_BpmProcessDefinition', ['pro_locked_variables', 'pro_terminate_variables'], 'pro_module'],
            ['pmse_BpmEventDefinition', ['evn_criteria'], 'evn_module'],
            ['pmse_BpmRelatedDependency', ['evn_criteria'], 'evn_module'],
            ['pmse_BpmRelatedDependency', ['pro_locked_variables', 'pro_terminate_variables'], 'pro_module'],
            ['pmse_Business_Rules', ['rst_source_definition'], 'rst_module'],
        ];

        foreach ($dataToUpdate as $params) {
            $this->renameCustomFieldsInDBColumns(...$params);
        }
    }

    /**
     * Rename custom fields in next columns
     * pmse_bpm_activity_definition.act_fields
     * pmse_bpm_activity_definition.act_params
     * pmse_bpm_activity_definition.act_required_fields
     * pmse_bpm_activity_definition.act_readonly_fields
     * @return void
     * @throws SugarQueryException
     */
    protected function updateActivityDefinitionFields(): void
    {
        $columnsToUpdate = ['act_fields', 'act_params'];
        $bean = BeanFactory::newBean('pmse_BpmActivityDefinition');
        $query = new SugarQuery();

        $query->select(['ad.id', 'ad.act_fields', 'ad.act_params', 'ad.act_field_module']);
        $query->select->fieldRaw('pd.pro_module', 'pro_module');
        $query->from($bean, ['alias' => 'ad']);
        $query->joinTable('pmse_bpm_process_definition', ['alias' => 'pd'])
            ->on()->equalsField('ad.pro_id', 'pd.id');

        $arr = [];
        foreach ($columnsToUpdate as $bpmField) {
            foreach ($this->fieldsToMove as $fields) {
                foreach ($fields as $field) {
                    $arr[] = "$bpmField LIKE '%{$field}\_c%'";
                }
            }
        }

        $where = implode(' OR ', $arr);
        $query->where()->addRaw($where);

        $results = $query->execute();

        foreach ($results as $row) {
            $module = $row['pro_module'];
            if ($row['act_field_module'] !== $row['pro_module']) {
                $beanModule = BeanFactory::newBean($row['pro_module']);
                $beanModule->load_relationship($row['act_field_module']);
                $module = $beanModule->{$row['act_field_module']}->relationship->lhsLinkDef['module'] ?? $module;
            }

            $q = DBManagerFactory::getConnection()->createQueryBuilder();
            $q->update($bean->getTableName());

            foreach ($columnsToUpdate as $bpmField) {
                $this->renameCustomMarketField($row[$bpmField], $module);
                $q->set($bpmField, $q->createPositionalParameter($row[$bpmField]));
            }

            $expr = $q->expr();
            $q->where($expr->eq('id', $q->createPositionalParameter($row['id'])))->executeStatement();
        }

        // pmse_bpm_activity_definition.act_required_fields
        // pmse_bpm_activity_definition.act_readonly_fields
        $this->updateBPMEncodedFields();
    }

    /**
     * Generate "where" expression for next cases
     *  <code>"value":"sf_lastactivity_default_c"</code> and
     *  <code>"value":"{::Contacts::sf_lastactivity_default_c::}"</code>
     * and call update records
     * @param string $beanName
     * @param array $fieldsToUpdate
     * @param string $moduleField
     * @return void
     */
    protected function renameCustomFieldsInDBColumns(string $beanName, array $fieldsToUpdate, string $moduleField): void
    {
        foreach ($fieldsToUpdate as $bpmField) {
            $arr1 = $arr3 = [];
            foreach ($this->fieldsToMove as $moduleName => $fields) {
                $moduleName = ucfirst($moduleName);
                $arr2 = [];
                foreach ($fields as $field) {
                    $arr2[] = "$bpmField LIKE '%{$field}\_c%'";
                    $arr3[] = "$bpmField LIKE '%:{$moduleName}::$field\_c:%'";
                }
                $arr1[] = "($moduleField = '$moduleName' AND (" . implode(' OR ', $arr2) . "))";
            }
            $where = '(' . implode(' OR ', $arr1) . ') OR (' . implode(' OR ', $arr3) . ')';
        }

        $this->updateRecords($where, $beanName, $fieldsToUpdate, $moduleField);
    }

    /**
     * Rename custom fields in "act_required_fields" and "act_readonly_fields" columns
     * in pmse_bpm_activity_definition table
     * @return void
     * @throws SugarQueryException
     */
    protected function updateBPMEncodedFields(): void
    {
        $bean = BeanFactory::newBean('pmse_BpmActivityDefinition');
        $query = new SugarQuery();

        $query->select(['ad.id', 'ad.act_required_fields', 'ad.act_readonly_fields']);
        $query->select->fieldRaw('pd.pro_module', 'pro_module');
        $query->from($bean, ['alias' => 'ad']);
        $query->joinTable('pmse_bpm_process_definition', ['alias' => 'pd'])
            ->on()->equalsField('ad.pro_id', 'pd.id');

        $where = '((ad.act_required_fields IS NOT NULL AND ad.act_required_fields != \'W10=\') OR (ad.act_readonly_fields IS NOT NULL AND ad.act_readonly_fields != \'W10=\'))';
        $arr1 = [];
        foreach ($this->fieldsToMove as $table => $fields) {
            $beanName = ucfirst($table);
            $arr1[] = "pd.pro_module = '$beanName'";
        }

        $where .= ' AND (' . implode(' OR ', $arr1) . ')';
        $query->where()->addRaw($where);
        $results = $query->execute();

        foreach ($results as $row) {
            foreach (['act_required_fields', 'act_readonly_fields'] as $fieldName) {
                $decodedArr = json_decode(base64_decode($row[$fieldName]));

                if (!is_array($decodedArr)) {
                    continue;
                }

                $res = $this->renameCustomMarketField($decodedArr, strtolower($row['pro_module']));
                $row[$fieldName] = base64_encode(json_encode($res));
            }

            $q = DBManagerFactory::getConnection()->createQueryBuilder();
            $q->update($bean->getTableName())
                ->set('act_required_fields', ':value1')
                ->set('act_readonly_fields', ':value2')
                ->where('id = :id')
                ->setParameter('id', $row['id'])
                ->setParameter('value1', $row['act_required_fields'])
                ->setParameter('value2', $row['act_readonly_fields'])
                ->executeStatement();
        }
    }

    /**
     * Rename <code>_c</code> fields in Reports records
     * @return void
     */
    protected function updateReportsRecords(): void
    {
        $arr = [];
        foreach ($this->fieldsToMove as $fields) {
            foreach ($fields as $field) {
                $arr[] = "content LIKE '%{$field}\_c%'";
            }
        }

        // Update Record Cache for each record
        $updateReportCache = function ($row, $moduleName) {
            $reportCache = new ReportCache();
            if ($reportCache->retrieve($row['id']) && $reportCache->contents_array) {
                $contents = $reportCache->contents_array;
                $this->renameCustomMarketField($contents, $moduleName);
                $reportCache->contents = json_encode($contents);
                $reportCache->save();
            }
        };

        $where = implode(' OR ', $arr);
        $this->updateRecords($where, 'Reports', ['content'], 'module', $updateReportCache);
    }

    /**
     * Rename custom fields in DB records
     * @param string $where
     * @param string $beanName
     * @param array $columns List of columns to update
     * @param string $moduleField
     * @param Closure|null $callback
     * @return void
     */
    protected function updateRecords(string $where, string $beanName, array $columns = [], string $moduleField = '', ?Closure $callback = null): void
    {
        $bean = BeanFactory::newBean($beanName);
        $query = new SugarQuery();
        $query->select(['id', ...$columns, $moduleField]);
        $query->from($bean);
        $query->where()->addRaw($where);
        $results = $query->execute();

        foreach ($results as $row) {
            $q = DBManagerFactory::getConnection()->createQueryBuilder();
            $q->update($bean->getTableName());
            $moduleName = strtolower($row[$moduleField] ?? '');

            foreach ($columns as $columnName) {
                $updatedVal = $this->renameCustomMarketField($row[$columnName], $moduleName);
                $q->set($columnName, $q->createPositionalParameter($updatedVal));
            }

            $expr = $q->expr();
            $q->where($expr->eq('id', $q->createPositionalParameter($row['id'])))->executeStatement();

            if ($callback) {
                $callback($row, $moduleName);
            }
        }
    }

    /**
     * Remove <code>_c</code> for all array items
     * @param string|array $val
     * @return mixed
     */
    protected function renameCustomMarketField(string|array &$val, string $module = ''): mixed
    {
        if (is_array($val)) {
            foreach ($val as &$v) {
                $this->renameCustomMarketField($v, $module);
            }
            return $val;
        } elseif (is_string($val)) {
            $customFieldName = $this->isCustomMarketField($val, $module, 'consist');
            if ($customFieldName) {
                $val = str_replace("{$customFieldName}_c", $customFieldName, $val);
            }
        }

        return $val;
    }

    /**
     * Update data for all Market tables
     * @return void
     */
    protected function moveDataFromCstmTables(): void
    {
        foreach ($this->fieldsToMove as $table => $fields) {
            $this->moveTableData($table);
            $this->upgradeCustomViewDefs($table);
            $this->removeCustomFields($table, $fields);
        }
    }

    /**
     * Delete unused fields
     * @return void
     */
    protected function deleteUnusedFields(): void
    {
        foreach ($this->fieldsToDelete as $table => $fields) {
            $this->removeCustomFields($table, $fields);
        }
    }

    /**
     * Move data from custom table to regular in batches
     * @param string $table
     * @param string $alias
     * @param string $sql
     * @return void
     */
    protected function batchMoveTableData(string $table, string $alias, string $sql): void
    {
        // batch size can be set in config.php, default is 1000
        // even though the batch size can be increased to make this function run faster,
        // eg 10000, but 1000 should be a very safe number without causing any db error
        // when updating vary large amount of data
        $batchSize = SugarConfig::getInstance()->get('market_migration_batch_size', 1000);
        $minIdRow = $this->db->fetchOne('SELECT MIN(id) AS id FROM ' . $table);
        while ($minIdRow && !empty($minIdRow['id'])) {
            $minId = $minIdRow['id'];
            $nextIdSQL = 'SELECT id FROM ' . $table . ' WHERE id >= ' . $this->db->quoted($minId) . ' ORDER BY id';
            $nextIdSQL = $this->db->limitQuery($nextIdSQL, $batchSize, 1, false, '', false);
            $nextIdRow = $this->db->fetchOne($nextIdSQL);
            if ($nextIdRow && !empty($nextIdRow['id'])) {
                $nextId = $nextIdRow['id'];
                $batchSQL = $sql .' AND ' . $alias . '.id >= '. $this->db->quoted($minId) . ' AND ' . $alias . '.id < ' . $this->db->quoted($nextId);
            } else {
                $batchSQL = $sql .' AND ' . $alias . '.id >= '. $this->db->quoted($minId);
            }
            $this->db->query($batchSQL);
            $minIdRow = $nextIdRow;
        }
    }

    /**
     * Move data from custom table to regular
     * @param string $table
     * @return void
     */
    protected function moveTableData(string $table): void
    {
        $cstmTable = "{$table}_cstm";
        $this->log(sprintf("Move data from \"%s\" table to \"%s\".", $cstmTable, $table));
        $this->log(sprintf("DB Type: %s", $this->db->dbType));

        $beanName = ucfirst($table);
        $bean = BeanFactory::newBean($beanName);
        $fieldsDef = $bean->getFieldDefinitions();

        switch ($this->db->dbType) {
            case 'mssql':
                $q = $this->executeDataMoveMsSql($table, $cstmTable, $fieldsDef);
                break;
            default:
                $q = $this->executeDataMoveMysql($table, $cstmTable, $fieldsDef);
        }

        $this->batchMoveTableData($table, 't1', $q);
    }

    /**
     * Move data for MySql DB
     * @param string $table
     * @param string $cstmTable
     * @param array $fieldsDef
     * @return string
     */
    private function executeDataMoveMySql(string $table, string $cstmTable, array $fieldsDef): string
    {
        $where = [];
        $update = [];
        foreach ($this->fieldsToMove[$table] as $column) {
            $update[] = "t1.$column = t2.{$column}_c";

            $def = $fieldsDef[$column]['default'] ?? null;
            if ($def === null || $def === 'NULL') {
                $where[] = "t2.{$column}_c IS NOT NULL";
            } else {
                $where[] = "t2.{$column}_c != $def";
            }
        }

        $updateStr = implode(', ', $update);
        $whereStr = implode(' or ', $where);

        return "UPDATE $table t1 JOIN $cstmTable t2 ON t1.id = t2.id_c SET $updateStr WHERE $whereStr";
    }

    /**
     * Move data for MsSql DB
     * @param string $table
     * @param string $cstmTable
     * @param array $fieldsDef
     * @return string
     */
    private function executeDataMoveMsSql(string $table, string $cstmTable, array $fieldsDef): string
    {
        $where = [];
        $update = [];
        foreach ($this->fieldsToMove[$table] as $column) {
            $update[] = "$column = t2.{$column}_c";

            $def = $fieldsDef[$column]['default'] ?? null;
            if ($def === null || $def === 'NULL') {
                $where[] = "t2.{$column}_c IS NOT NULL";
            } else {
                $where[] = "t2.{$column}_c != $def";
            }
        }

        $updateStr = implode(', ', $update);
        $whereStr = implode(' or ', $where);

        return "UPDATE $table SET $updateStr FROM $table t1 JOIN $cstmTable t2 ON t1.id = t2.id_c WHERE $whereStr";
    }

    /**
     * Update custom views
     * @param string $table
     * @return void
     */
    public function upgradeCustomViewDefs(string $table): void
    {
        $moduleName = ucfirst($table);
        $upgraded = false;

        $path = "custom/modules/$moduleName/clients/base/views";
        $filesToUpdate = $this->getFiles($path);

        // upgrade viewdefs
        foreach ($filesToUpdate as $scanFile) {
            if (file_exists($scanFile)) {
                $changed = false;
                $viewdefs = [];

                include $scanFile;

                array_walk_recursive(
                    $viewdefs,
                    function (&$value, $key) use (&$changed, $table, $scanFile) {
                        if ($key === 'name' && $this->isCustomMarketField($value, $table)) {
                            $value = str_replace('_c', '', $value);
                            $changed = true;

                            $this->log(sprintf(
                                'PostMarketMigrate: Fix field name from "%s" to "%s" in file "%s"',
                                "{$value}_c",
                                $value,
                                $scanFile
                            ));
                        }
                    }
                );

                if ($changed) {
                    $this->backupFile($scanFile);
                    write_array_to_file('viewdefs', $viewdefs, $scanFile);
                }
            }
        }
    }

    /**
     * Check if string is related to Market. Return field name if the field is custom
     * @param string $fieldStr
     * @param string $module
     * @param string $type
     * @return bool|string
     */
    private function isCustomMarketField(string $fieldStr, string $module = '', string $type = 'eq'): bool | string
    {
        $module = strtolower($module);
        if (!$module) {
            $fields = $this->fieldsToMove;
        } else {
            $fields = isset($this->fieldsToMove[$module]) ? [$this->fieldsToMove[$module]] : [];
        }

        foreach ($fields as $fieldsOfGroup) {
            foreach ($fieldsOfGroup as $field) {
                if ($type === 'consist' && (strpos($fieldStr, "\"{$field}_c\"") !== false ||
                    strpos($fieldStr, ":{$field}_c:") !== false)) {
                    return $field;
                } elseif ($type === 'eq' && $fieldStr === "{$field}_c") {
                    return $field;
                }
            }
        }

        return false;
    }

    /**
     * Return list of files from the dir
     * @param string $path
     * @return array
     */
    private function getFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        return glob("$path/{,*/,*/*/,*/*/*/}*.php", GLOB_BRACE) ?: [];
    }

    /**
     * Remove Market custom fields
     * @param string $table
     * @param array $fields
     * @return void
     */
    protected function removeCustomFields(string $table, array $fields): void
    {
        $moduleName = ucfirst($table);
        $seed = BeanFactory::newBean($moduleName);
        $df = new DynamicField($moduleName);
        $df->setup($seed);

        $module = StudioModuleFactory::getStudioModule($moduleName);
        foreach ($fields as $fieldName) {
            $fieldNameC = "{$fieldName}_c";
            $field = $df->getFieldWidget($moduleName, $fieldNameC);
            // the field may have already been deleted
            if ($field) {
                $field->delete($df);
            }

            $module->removeFieldFromLayouts($fieldNameC);
            $this->log(sprintf(
                'PostMarketMigrate: custom field "%s" was removed.',
                $fieldNameC
            ));
        }
    }

    /**
     * Delete dashlets in dashboards
     */
    protected function deleteDashboardDashlets(): void
    {
        $dashletsToDelete = [
            'salesfusion-dash-leadsandcontactslistview',
            'salesfusion-dash-emails',
            'salesfusion-dash-landing',
            'salesfusion-dash-webhits',
        ];

        $bean = BeanFactory::newBean('Dashboards');
        $query = new SugarQuery();
        $query->select(['id', 'metadata']);
        $query->from($bean);
        $dashletsArr = array_map(fn($d) => "metadata LIKE '%{$d}%'", $dashletsToDelete);
        $query->where()->addRaw(implode(' OR ', $dashletsArr));
        $results = $query->execute();

        foreach ($results as $row) {
            $data = json_decode($row['metadata'], true);
            if (empty($data['dashlets']) || !is_array($data['dashlets'])) {
                continue;
            }

            // update dashlets field
            foreach ($data['dashlets'] as $index => $dashlet) {
                if (in_array($dashlet['view']['type'] ?? '', $dashletsToDelete, true)) {
                    unset($data['dashlets'][$index]);
                }
            }
            $fieldData = json_encode($data);

            // update record
            DBManagerFactory::getConnection()->createQueryBuilder()
                ->update($bean->getTableName())
                ->set('metadata', ':value')
                ->where('id = :id')
                ->setParameter('value', $fieldData)
                ->setParameter('id', $row['id'])
                ->executeStatement();

            $this->log(sprintf('Dashlets removed for dashboard "%s".', $row['id']));
        }
    }

    /**
     * Restore Focus Drawer dashboards
     * @return void
     */
    protected function restoreFocusDrawerDashboards(): void
    {
        $dashboardIds = [
            '1ae9f19e-5294-11ec-9832-525400bc1033',
            '1ae9f19e-5294-11ec-9832-525400bc1038',
            '1ae9f19e-5294-11ec-9832-525400bc1087',
            '1ae9f19e-5294-11ec-9832-525400bc1333',
        ];
        $idList = implode(',', array_map(fn($s) => "'$s'", $dashboardIds));
        $this->db->query("UPDATE dashboards SET deleted = 0 WHERE id in ($idList)");
    }

    /**
     * Restore Market settings from the temporary file, saved in PreMarketMigrate script
     * @return void
     */
    protected function restoreMarketSettings(): void
    {
        $settingsFile = 'cache/upgrades/settings.bak';
        if (!file_exists($settingsFile)) {
            return;
        }
        $settings = [];
        include $settingsFile;
        if ($settings) {
            $this->log('Restore Market settings');
            $source = SourceFactory::getSource('ext_rest_salesfusion');
            $source->restoreSettings($settings);
        }
        @unlink($settingsFile);
    }
}
