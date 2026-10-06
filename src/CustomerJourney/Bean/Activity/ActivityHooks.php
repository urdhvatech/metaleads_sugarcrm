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

namespace Sugarcrm\Sugarcrm\CustomerJourney\Bean\Activity;

use Sugarcrm\Sugarcrm\CustomerJourney\Bean\Activity\Helper\StatusHelper;
use Sugarcrm\Sugarcrm\CustomerJourney\LogicHooks\ActivityHooksHelper;
use Sugarcrm\Sugarcrm\CustomerJourney\Bean\Stage\ProgressCalculator;
use Sugarcrm\Sugarcrm\CustomerJourney\Bean\Stage\StateCalculator;

/**
 * This class contains logic hooks related to the
 * Smart Guide plugin for the activity modules
 */
class ActivityHooks
{
    /**
     * @var \Sugarcrm\Sugarcrm\CustomerJourney\LogicHooks\ActivityHooksHelper|mixed
     */
    public $hooksHelper;

    public function __construct()
    {
        $this->hooksHelper = new ActivityHooksHelper();
    }

    /**
     * All before_save logic hooks is inside this function.
     *
     * @param object $activity
     * @param string $event
     * @param array $arguments
     */
    public function beforeSave($activity, $event, $arguments)
    {
        if (!hasSystemAutomateLicense()) {
            return;
        }
        $this->hooksHelper->saveFetchedRow($activity);
        $this->hooksHelper->validate($activity);
        $this->hooksHelper->reorder($activity);
        $this->hooksHelper->calculate($activity);
        $this->hooksHelper->calculateMomentum($activity);
        $this->hooksHelper->beforeStatusChange($activity);
        $this->hooksHelper->setActualSortOrder($activity);
        $this->hooksHelper->checkRelatedSugarAction($activity, $event, $arguments);

        if ($activity->getModuleName() === 'Tasks') {
            $this->hooksHelper->setCJDaysToComplete($activity);
        }
    }

    /**
     * All after_save logic hooks is inside this function.
     *
     * @param object $activity
     * @param string $event
     * @param array $arguments
     */
    public function afterSave($activity, $event, $arguments)
    {
        if (!hasSystemAutomateLicense()) {
            return;
        }
        if ($activity->is_customer_journey_activity && !isset($activity->journeyCreate)) {
            if (!empty($activity->cj_parent_activity_id)) {
                $this->updateParent($activity, $event, $arguments);
            } else {
                $this->updateStage($activity, $event, $arguments);
            }
        }
        if ($this->isStatusProgressUpdated($arguments)) {
            $this->hooksHelper->resaveIfChanged($activity);
            $this->hooksHelper->startNextJourneyIfCompleted($activity, $event, $arguments);
        }
    }

    /**
     * If the activity that is being updated is a child then update its parent with the correct
     * status, score and momentum
     *
     * @param string $event
     * @param array $arguments
     */
    public function updateParent($activity, $event, $arguments)
    {
        $parent = \BeanFactory::retrieveBean($activity->cj_parent_activity_type, $activity->cj_parent_activity_id);
        if (isset($parent)) {
            $parent->cancelling_smart_guide = $activity->cancelling_smart_guide;
            if (isset($arguments['dataChanges']['customer_journey_score']) || !isset($activity->dri_workflow_task_template_id)) {
                $factor = $arguments['dataChanges']['customer_journey_score']['before'] < $activity->dataChanges['customer_journey_score']['after'] ? 1 : -1;
                $parent->customer_journey_score += $factor * $activity->customer_journey_score;
                $hooksHelper = new ActivityHooksHelper();
                $handler = ActivityHandlerFactory::factory($activity->module_dir);
                $hooksHelper->calculate($activity);
                $handler->calculateStatus($parent);
                $hooksHelper->calculate($parent);
                $statusHelper = new StatusHelper();
                //The following check works in case of non-template activities
                //especially when they are added in smart guide for first time
                //it updates the stage and journey after that
                if (!isset($activity->dri_workflow_task_template_id) && $arguments['dataChanges']['status']['after'] === $statusHelper->getNotStartedStatus($activity)) {
                    $this->updateStage($activity, $event, $arguments, true);
                }
            }
            $parent->is_cj_parent_activity = true;
            $parent->save();
        }
    }

    /**
     * When the activity is updated, update its Stage with correct status, score and momentum
     *
     * @param string $event
     * @param array $arguments
     * @param bool $updateStage
     */
    public function updateStage($activity, $event, $arguments, $updateStage = false)
    {
        $stage = \BeanFactory::retrieveBean('DRI_SubWorkflows', $activity->dri_subworkflow_id);
        $stage->cancelling_smart_guide = $activity->cancelling_smart_guide;
        if (isset($stage)) {
            $stage->setActivity($activity);
            $stageChanged = false;
            if (isset($arguments['dataChanges']['customer_journey_score']) || $updateStage) {
                $calculator = new ProgressCalculator($stage);
                $calculator->calculate();
                $stageChanged = true;
            }
            if ((isset($arguments['dataChanges']['status']['before']) &&
                $arguments['dataChanges']['status']['before'] != $arguments['dataChanges']['status']['after']) ||
                $stage->cancelling_smart_guide || $updateStage) {
                $calculator = new StateCalculator($stage);
                $calculator->calculate();
                $stageChanged = true;
            }
            if ($stageChanged) {
                $stage->save();
                if ($stage->state === 'completed') {
                    $nextStageId = ActivityHooksHelper::getNextStageId($stage->dri_workflow_id, $stage->sort_order + 1);
                    if (isset($nextStageId)) {
                        $nextStage = \BeanFactory::retrieveBean('DRI_SubWorkflows', $nextStageId);
                        if (isset($nextStage)) {
                            $calculator = new StateCalculator($nextStage);
                            $calculator->calculate();
                            $nextStage->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * check if the data is changed or not
     *
     * @param array $arguments
     */
    public function isStatusProgressUpdated(array $arguments)
    {
        return (isset($arguments['dataChanges']['status']) &&
                isset($arguments['dataChanges']['customer_journey_progress']) &&
                isset($arguments['dataChanges']['customer_journey_score'])) || isset($arguments['dataChanges']['customer_journey_points']);
    }

    /**
     * All before_delete logic hooks is inside this function.
     *
     * @param object $activity
     * @param string $event
     * @param array $arguments
     */
    public function beforeDelete($activity, $event, $arguments)
    {
        if (!hasSystemAutomateLicense()) {
            return;
        }
        $this->hooksHelper->beforeDelete($activity);
        $this->hooksHelper->removeChildren($activity);
    }

    /**
     * All after_delete logic hooks is inside this function.
     *
     * @param object $activity
     * @param string $event
     * @param array $arguments
     */
    public function afterDelete($activity, $event, $arguments)
    {
        if (!hasSystemAutomateLicense()) {
            return;
        }
        $this->hooksHelper->afterDelete($activity);
        $this->hooksHelper->resave($activity);
    }
}
