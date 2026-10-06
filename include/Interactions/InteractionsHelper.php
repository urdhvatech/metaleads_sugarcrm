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
 * This class is here to provide functions to do logic involved in interactions
 */
class InteractionsHelper
{
    /**
     * SugarBean bean
     * @var SugarBean
     */
    protected $bean;

    /**
     * InteractionsHelper constructor
     *
     * @param $bean SugarBean
     */
    public function __construct(SugarBean $bean)
    {
        $this->bean = $bean;
    }

    /**
     * Saves Interaction data
     */
    public function saveInteraction(): void
    {
        global $dictionary;

        if (!isset($dictionary[$this->bean->getObjectName()]['interactions']) || empty($this->bean->parent_id)) {
            return;
        }

        foreach ($dictionary[$this->bean->getObjectName()]['interactions'] as $keyInteraction => $interaction) {
            foreach ($interaction as $trigger) {
                if ($this->bean->parent_type !== $trigger['module']) {
                    continue;
                }

                $parentBean = $this->getInteractionParentBean($trigger['module'], $this->bean->parent_id);
                if ($parentBean === null) {
                    continue;
                }
                $interactionParentId = $keyInteraction . '_interaction_parent_id';
                $relatedField = $trigger['related_field'];
                $valueKey = array_search($this->bean->$relatedField, $trigger['related_value']);
                // do nothing if $bean is not contain interaction trigger value' and is not Last Interaction
                if ($valueKey !== false || $parentBean->$interactionParentId === $this->bean->id) {
                    $interactionDate = $keyInteraction . '_interaction_date';
                    $dateField = $trigger['date_field'];
                    // If interaction trigger_related field just has been changed from interaction trigger related value
                    // or last|first interaction date is more late|early
                    switch ($keyInteraction) {
                        case 'last':
                            $compare  = $parentBean->$interactionDate < $this->bean->$dateField;
                            break;
                        default:
                            $compare = false;
                    }

                    if (($trigger['related_value'][$valueKey] === $this->bean->$relatedField && $compare) ||
                        $parentBean->$interactionParentId === $this->bean->id) {
                        $this->updateInteraction($parentBean, $keyInteraction, $trigger);
                    }
                }
            }
        }
    }

    /**
     * Deletes Interaction data for parent module
     *
     * @param string|null $parentId
     */
    public function deleteInteraction(?string $parentId = null): void
    {
        global $dictionary;

        $parentId = $parentId ?? $this->bean->parent_id;

        if (!isset($dictionary[$this->bean->getObjectName()]['interactions']) || empty($parentId)) {
            return;
        }

        foreach ($dictionary[$this->bean->getObjectName()]['interactions'] as $keyInteraction => $interaction) {
            foreach ($interaction as $trigger) {
                $relatedField = $trigger['related_field'];

                if ($this->bean->parent_type != $trigger['module']
                    || !in_array($this->bean->$relatedField, $trigger['related_value'])) {
                    continue;
                }

                $parentBean = $this->getInteractionParentBean($trigger['module'], $parentId);
                if ($parentBean === null) {
                    continue;
                }
                $this->updateInteraction(
                    $parentBean,
                    $keyInteraction,
                    $trigger,
                    ['module' => $this->bean->module_name, 'id' => $this->bean->id]
                );
            }
        }
    }

    /**
     * Updates Interaction data for its parent
     *
     * @param SugarBean $parentBean
     * @param string $keyInteraction
     * @param array $trigger
     * @param array $deletedBean
     */
    protected function updateInteraction(
        SugarBean $parentBean,
        string    $keyInteraction,
        array     $trigger,
        array     $deletedBean = []
    ): void {
        global $dictionary;

        if (!isset($dictionary[$parentBean->getObjectName()]['interaction_modules'])) {
            return;
        }

        $interactionType = [];

        foreach ($dictionary[$parentBean->getObjectName()]['interaction_modules'] as $module) {
            $dateField = $trigger['date_field'];
            $row = $this->selectPotentialLastInteractions($parentBean, $module, $trigger, $deletedBean);

            // we should left the newest|oldest only
            switch ($keyInteraction) {
                case 'last':
                    $compare  = (!empty($row) && !empty($interactionType)) ?
                        $row[$dateField] >= $interactionType[$dateField] : false;
                    break;
                default:
                    $compare = false;
            }

            if (!empty($row) && (empty($interactionType) || $compare)) {
                    $interactionType = $row;
                    $interactionType['module'] = $module;
                    $interactionType['date'] = $row[$dateField];
            }
        }

        $interactionDate = $keyInteraction . '_interaction_date';
        $interactionParentType = $keyInteraction . '_interaction_parent_type';
        $interactionParentId = $keyInteraction . '_interaction_parent_id';
        $interactionParentName = $keyInteraction . '_interaction_parent_name';

        if (empty($interactionType)) {
            $parentBean->$interactionDate = '';
            $parentBean->$interactionParentType = '';
            $parentBean->$interactionParentId = '';
            $parentBean->$interactionParentName = '';
            $parentBean->save();
        } else {
            $parentBean->$interactionDate = $interactionType['date'];
            $parentBean->$interactionParentType = $interactionType['module'];
            $parentBean->$interactionParentId = $interactionType['id'];
            $parentBean->$interactionParentName = $interactionType['name'];
            $parentBean->save();
        }
    }

    /**
     * Selects potential Last Interactions from DB
     *
     * @param SugarBean $parentBean
     * @param string $module
     * @param array $trigger
     * @param array $deletedBean
     * @return array|null
     * @throws SugarQueryException
     */
    protected function selectPotentialLastInteractions(
        SugarBean $parentBean,
        string $module,
        array $trigger,
        array $deletedBean = []
    ): array|null {
        $dateField = $trigger['date_field'];
        $relatedValue = $trigger['related_value'];
        $relatedField = $trigger['related_field'];

        $sq = new SugarQuery();
        $sq->select(['id', 'name', $dateField]);
        $sq->from(BeanFactory::newBean($module));
        $sq->where()
            ->equals('parent_id', $parentBean->id)
            ->equals('parent_type', $parentBean->getModuleName());

        // this is used for deletion only, to escape the case when the record to be deleted will be chosen
        if (!empty($deletedBean) && $module === $deletedBean['module']) {
            $sq->where()->notEquals('id', $deletedBean['id']);
        }

        $or = $sq->where()->queryOr();
        foreach ($relatedValue as $triggerValue) {
            $or->equals($relatedField, $triggerValue);
        }

        $sq->orderBy($dateField, 'DESC');
        $sq->limit(1);
        $data = $sq->execute();

        return array_shift($data);
    }

    /**
     * Wrapper for static method
     *
     * @param string $module
     * @param string $id
     *
     * @return SugarBean
     */
    protected function getInteractionParentBean(string $module, string $id): ?SugarBean
    {
        return BeanFactory::retrieveBean($module, $id);
    }
}
