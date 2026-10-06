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

use Doctrine\DBAL\Schema\Table;

$GLOBALS['log']->debug('[PB] - started execution');

require_once 'post_install_functions.php';
require_once 'db_customizations.php';
require_once 'relationships_data.php';
require_once 'ModuleInstall/ModuleInstaller.php';
require_once 'modules/DynamicFields/DynamicField.php';

$moduleInstaller = new ModuleInstaller();

global $db, $sugar_config;

// checks if users_cstm exists on the current system. If it doesn't, then manually create the table(issue found on 7.7.2.1 ENT)
if (!$db->tableExists('users_cstm')) {
    $table = new Table('users_cstm');
    $table->addColumn('id_c', 'string', ['length' => 36]);
    $table->setPrimaryKey(['id_c']);
    $table->addOption('charset', 'utf8');
    $table->addOption('collate', 'utf8_general_ci');
    $sm = $db->getConnection()->getSchemaManager();
    $sm->createTable($table);
}

// config additional processing
$configs = $db_customizations['config'] ?? [];
if (!empty($configs)) {
    $GLOBALS['log']->debug('[PB] - configs to install: ' . var_export($configs, true));
    $admin = BeanFactory::getBean('Administration');
    foreach ($configs as $config) {
        extract($config);
        $admin->saveSetting($category, $name, $value, $platform);
    }
    unset($db_customizations['config']);
}

// fields additional processing
$fields = $db_customizations['fields_meta_data'] ?? [];
$GLOBALS['log']->debug('[PB] - fields to install: ' . var_export($fields, true));
unset($db_customizations['fields_meta_data']);
$fieldsTable = 'fields_meta_data';

$modulesDummyBeans = []; // Use this in order to create only one dummy bean for each field module
foreach ($fields as $key => $rowInfo) {
    // Skip this logic for base fields
    if (array_key_exists('id', $rowInfo) === false) {
        continue;
    }

    if (array_key_exists('id', $rowInfo) && strlen($rowInfo['id']) > 36) {
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('id')
            ->from($fieldsTable)
            ->where($qb->expr()->eq('name', $qb->expr()->literal($rowInfo['name'])))
            ->andWhere($qb->expr()->eq('category', $qb->expr()->literal($action['category'])))
            ->andWhere($qb->expr()->eq('custom_module', $qb->expr()->literal($rowInfo['custom_module'])));
        $stmt = $qb->executeQuery();
        $existingId = $stmt->fetchAssociative();
        $rowInfo['id'] = $existingId !== false ? $existingId['id'] : \Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
    }

    $fieldModule = $rowInfo['custom_module'];

    // Populate dummy beans list - this bean is used to check if _cstm table exists and create it
    if (isset($modulesDummyBeans[$fieldModule]) === false) {
        $modulesDummyBeans[$fieldModule] = BeanFactory::newBean($fieldModule); // Create dummy bean for given module
    }

    // Check if _cstm table exists
    if ($GLOBALS['db']->tableExists($modulesDummyBeans[$fieldModule]->table_name . '_cstm') === false) {
        // If does not, create it
        $df = new DynamicField($fieldModule);
        $df->setup($modulesDummyBeans[$fieldModule]);
        $df->createCustomTable(true);
    }

    upsertRow($fieldsTable, $rowInfo);
}

if ($fields) {
    foreach ($fields as $key => $value) {
        $fields[$key]['module'] = $fields[$key]['custom_module'];
    }

    // Insert custom actionbuttons fields into custom table (ex: accounts_cstm, contacts_cstm ...etc)
    require_once 'modules/DynamicFields/FieldCases.php';
    foreach ($fields as $key => $field) {
        $fieldType = $field['type'];
        if ($fieldType === 'actionbutton') {
            try {
                $moduleBean = BeanFactory::newBean($field['module']);

                $fieldObject = get_widget($fieldType);
                $fieldObject->populateFromRow($field);
                $fieldObject->tablename = $moduleBean->table_name . '_cstm';
                $tableColumns = $db->get_columns($fieldObject->tablename);
                $fieldName = $field['name'];

                // Insert column in _cstm table only if column does not exists
                if (isset($tableColumns[$fieldName]) === false) {
                    $query = $fieldObject->get_db_add_alter_table($fieldObject->tablename);
                    $GLOBALS['db']->query($query, true, 'Cannot create column');
                }
            } catch (Exception $e) {
                $GLOBALS['log']->debug('Action Button Installation FAILED for ' . $field['name'] . ' field');
            }
        }
    }
}

//relatipnships additional proccessing
foreach ($relationships_data as $key => $filePath) {
    $moduleInstaller->install_relationship($filePath);
}

//sync database entries
foreach ($db_customizations as $tableName => $tableContent) {
    $GLOBALS['log']->debug('[PB] - Processing for ' . $tableName);

    if (!$tableContent) {
        continue;
    }

    // Skip acl_actions, this table is used for acl_roles_actions inserts
    if ($tableName === 'acl_actions') {
        continue;
    }

    foreach ($tableContent as $key => $rowInfo) {
        // For acl_roles_actions, check if action_id exists, if not, find it and set the coresponding action_id
        if ($tableName === 'acl_roles_actions') {
            if (actionIdExists($rowInfo['action_id']) === false) {
                $tableContent[$key]['action_id'] = getRightActionId($rowInfo, $db_customizations['acl_actions']);
            }
        }
    }

    $existingRows = getExistingRows($tableName, array_column($tableContent, 'id'));
    upsertBulk($tableName, $tableContent, $existingRows);
}

//workflows additional processing
$workflowIds = [];
if (!empty($db_customizations['workflow'])) {
    foreach ($db_customizations['workflow'] as $key => $row) {
        $workflowIds[] = $row['id'];
    }
}
foreach ($workflowIds as $key => $id) {
    $workflowBean = BeanFactory::retrieveBean('WorkFlow', $id);
    if ($workflowBean) {
        $workflowBean->check_logic_hook_file();
        $workflowBean->write_workflow();
        $workflowBean->save();
    }
}

$GLOBALS['log']->debug('[PB] - ended execution');
