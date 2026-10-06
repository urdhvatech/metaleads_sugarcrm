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

// @codingStandardsIgnoreLine
class SummarizationGai extends SugarBean
{
    public $new_schema = true;
    public $module_dir = 'GAI/SummarizationGai';
    public $module_name = 'SummarizationGai';

    public $object_name = 'SummarizationGai';
    public $table_name = 'summarization_gai';
    public $acl_category = 'SummarizationGai';
    public $importable = false;
    public $disable_custom_fields = true;
    public $tracker_visibility = false;
    public $disable_row_level_security = true;
    public $update_modified_by = false;
    public $set_created_by = false;

    public $id;
    public $date_entered;
    public $date_modified;
    public $deleted;
    public $parent_id;
    public $parent_module;
    public $hash;
    public $status;
    public $error_message;
    public $summary;
    public $participants;
    public $sentiment;
    public $sentiment_actions;
    public $suggested_next_steps;
    public $eval_id;
    public $language;
    public $is_translate;
    public $last_sync_date;
    public $needed_followup;
    public $engaged_contacts;

    /**
     * Hard delete the record.
     *
     * @return void
     */
    public function hardDelete()
    {
        $db = DBManagerFactory::getInstance();
        $conn = DBManagerFactory::getInstance()->getConnection();
        $tableName = $this->getTableName();
        $id = $this->id;

        $hasCustomTable = false;

        if ($db->tableExists($this->table_name . '_cstm')) {
            $hasCustomTable = true;
        }

        $conn->executeUpdate(
            "DELETE FROM {$tableName} WHERE id = ?",
            [$id]
        );

        if ($hasCustomTable) {
            $conn->executeUpdate(
                "DELETE FROM {$tableName}_cstm WHERE id_c = ?",
                [$id]
            );
        }
    }

    public function bean_implements($interface)
    {
        switch ($interface) {
            case 'ACL':
                return true;
        }
        return false;
    }
}
