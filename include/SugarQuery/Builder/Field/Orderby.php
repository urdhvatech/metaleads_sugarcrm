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
 * SugarQuery_Builder_Field_Orderby
 * @api
 */
class SugarQuery_Builder_Field_Orderby extends SugarQuery_Builder_Field
{
    public $direction = 'DESC';
    public $nullsLast = false;

    public function __construct($field, SugarQuery $query, $direction = null, $nullsLast = false)
    {
        $this->direction = $direction;
        $this->nullsLast = $nullsLast;
        parent::__construct($field, $query);
    }

    public function expandField()
    {
        if (!empty($this->def['sort_on'])) {
            $this->def['sort_on'] = !is_array($this->def['sort_on']) ? [$this->def['sort_on']] : $this->def['sort_on'];
        }

        if (!empty($this->def['source']) && $this->def['source'] === 'non-db') {
            $this->markNonDb();
        }

        // Handle currency field conversion for proper sorting
        if ($this->isCurrencyFieldRequiringConversion()) {
            $this->handleCurrencyFieldOrderBy();
            return;
        }

        if (!empty($this->def['rname']) && !empty($this->def['link'])) {
            $jta = $this->query->getJoinAlias($this->def['link']);
            if (empty($jta)) {
                $this->def['link'] = $jta = $this->table;
            }

            $fieldsToOrder = empty($this->def['sort_on']) ? [$this->def['rname']] : $this->def['sort_on'];
            foreach ($fieldsToOrder as $fieldToOrder) {
                // Some sort_on fields are already prefixed with a table name, like
                // in the case of team_name. This cleans that up.
                $field = $this->getTrueFieldNameFromField($fieldToOrder);
                $this->query->orderBy("{$jta}.{$field}", $this->direction, $this->nullsLast);
                if (!$this->query->select->checkField($field, $this->table)) {
                    $this->query->select->addField("{$jta}.{$field}", ['alias' => DBManagerFactory::getInstance()->getValidDBName("{$this->def['link']}__{$field}", false, 'alias')]);
                }
            }

            $this->markNonDb();
        } elseif (!empty($this->def['rname']) && !empty($this->def['table'])) {
            $jta = $this->query->getJoinAlias($this->def['table'], false);
            if (empty($jta)) {
                $jta = empty($this->jta) ? $this->table : $this->jta;
            }

            $fieldsToOrder = empty($this->def['sort_on']) ? [$this->def['rname']] : $this->def['sort_on'];
            foreach ($fieldsToOrder as $fieldToOrder) {
                $field = $this->getTrueFieldNameFromField($fieldToOrder);
                $this->query->orderBy("{$jta}.{$field}", $this->direction, $this->nullsLast);
                if (!$this->query->select->checkField($field, $this->table)) {
                    $this->query->select->addField("{$jta}.{$field}", ['alias' => DBManagerFactory::getInstance()->getValidDBName("{$this->def['table']}__{$field}", false, 'alias')]);
                }
            }

            $this->markNonDb();
        } elseif (!empty($this->def['rname']) && !empty($this->jta)) {
            if (isset($this->def['module'])) {
                $rBean = BeanFactory::getDefinition($this->def['module']);
                if ($rBean?->field_defs[$this->def['rname']]['type'] == 'fullname') {
                    $nameFields = Localization::getObject()->getNameFormatFields($this->def['module']);
                    foreach ($nameFields as $partOfName) {
                        $this->query->orderBy("{$this->jta}.{$partOfName}", $this->direction, $this->nullsLast);
                        if (!$this->query->select->checkField($partOfName, $this->table)) {
                            $this->query->select->addField("{$this->jta}.{$partOfName}", ['alias' => DBManagerFactory::getInstance()->getValidDBName("{$this->jta}__{$partOfName}", false, 'alias')]);
                        }
                    }
                } else {
                    $this->query->orderBy("{$this->jta}.{$this->def['rname']}", $this->direction, $this->nullsLast);
                    if (!$this->query->select->checkField($this->def['rname'], $this->table)) {
                        $this->query->select->addField("{$this->jta}.{$this->def['rname']}", ['alias' => DBManagerFactory::getInstance()->getValidDBName("{$this->jta}__{$this->def['rname']}", false, 'alias')]);
                    }
                }
            }
            $this->markNonDb();
        } elseif (!empty($this->def['rname_link'])) {
            $this->query->orderBy("{$this->table}.{$this->def['rname_link']}", $this->direction, $this->nullsLast);
            if (!$this->query->select->checkField($this->def['rname_link'], $this->table)) {
                $this->query->select->addField("{$this->table}.{$this->def['rname_link']}", ['alias' => DBManagerFactory::getInstance()->getValidDBName("{$this->table}__{$this->def['rname_link']}", false, 'alias')]);
            }
            $this->markNonDb();
        } else {
            if (!empty($this->def['sort_on'])) {
                $table = $this->table;
                //Custom fields may use standard or custom fields for sort on.
                //Let that SugarQuery_Builder_Field figure out if it's custom or not.
                if (!empty($this->custom) && !empty($this->standardTable)) {
                    $table = $this->standardTable;
                }
                foreach ($this->def['sort_on'] as $field) {
                    $this->query->orderBy("{$table}.{$field}", $this->direction, $this->nullsLast);
                    if (!$this->query->select->checkField($field, $this->table)) {
                        $this->query->select->addField("{$table}.{$field}", ['alias' => DBManagerFactory::getInstance()->getValidDBName("{$this->table}__{$this->field}", false, 'alias')]);
                    }
                }
                $this->markNonDb();
            } else {
                if (!$this->query->select->checkField($this->field, $this->table)) {
                    $this->query->select->addField("{$this->table}.{$this->field}", ['alias' => DBManagerFactory::getInstance()->getValidDBName("{$this->table}__{$this->field}", false, 'alias')]);
                }
            }
        }

        $this->checkCustomField();
    }

    /**
     * Check if this is a currency field that requires base currency conversion for sorting
     * @return bool
     */
    protected function isCurrencyFieldRequiringConversion()
    {
        if (!empty($this->def['rname']) || !empty($this->def['link'])) {
            return false;
        }

        // Check if this field has related_fields containing currency_id or base_rate
        if (empty($this->def['related_fields']) || !is_array($this->def['related_fields'])) {
            return false;
        }

        // Check if any of the related fields is currency_id or base_rate
        foreach ($this->def['related_fields'] as $relatedFieldName) {
            if (in_array($relatedFieldName, ['currency_id', 'base_rate'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle ORDER BY for currency fields with proper base currency conversion
     */
    protected function handleCurrencyFieldOrderBy()
    {
        // Get database instance
        $db = DBManagerFactory::getInstance();

        // Build the currency conversion expression: (field_value / IFNULL(base_rate, 1))
        $fieldColumn = "{$this->table}.{$this->field}";
        $baseRateColumn = $this->getTrueBaseRateColumn();
        $baseRateWithDefault = $db->convert($baseRateColumn, 'IFNULL', [1]);
        $convertedField = "({$fieldColumn} / {$baseRateWithDefault})";

        // Use the raw orderBy method to add the converted expression
        // Note: orderByRaw() handles direction separately, so don't append it to the expression
        $this->query->orderByRaw($convertedField, $this->direction);

        // Add the field to select if it's not already there
        if (!$this->query->select->checkField($this->field, $this->table)) {
            $this->query->select->addField($fieldColumn, ['alias' =>
                DBManagerFactory::getInstance()->getValidDBName("{$this->table}__{$this->field}", false, 'alias')]);
        }

        // Also add base_rate to select for the conversion
        if (!$this->query->select->checkField('base_rate', $this->table)) {
            $this->query->select->addField($baseRateColumn, ['alias' =>
                DBManagerFactory::getInstance()->getValidDBName("{$this->table}__base_rate", false, 'alias')]);
        }

        $this->markNonDb();
    }

    /**
     * Get the true base rate column table and name
     *
     * @return string
     */
    protected function getTrueBaseRateColumn()
    {
        $tableName = $this->table;

        if ($this->custom && !empty($this->standardTable)) {
            $bean = $this->query->getFromBean();
            $baseRateDef = $bean?->getFieldDefinition('base_rate');
            if (!empty($baseRateDef) && empty($baseRateDef['source'])) {
                $tableName = $this->standardTable;
            }
        }

        return "{$tableName}.base_rate";
    }
}
