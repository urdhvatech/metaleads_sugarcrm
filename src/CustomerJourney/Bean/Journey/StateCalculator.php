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

namespace Sugarcrm\Sugarcrm\CustomerJourney\Bean\Journey;

use Sugarcrm\Sugarcrm\CustomerJourney\Bean\Stage\StateCalculator as StageStateCalculator;

class StateCalculator
{
    /**
     * @var \DRI_Workflow
     */
    private $journey;

    /**
     * @var \DRI_SubWorkflow[]
     */
    private $stages;

    /**
     * StateCalculator constructor.
     * @param \DRI_Workflow $journey
     */
    public function __construct(\DRI_Workflow $journey)
    {
        $this->journey = $journey;
    }

    /**
     * @param \DRI_SubWorkflow[] $stages
     */
    public function setStages(array $stages)
    {
        $this->stages = $stages;
    }

    /**
     * Load the stages against journey
     */
    public function load()
    {
        if (is_null($this->stages)) {
            $this->stages = $this->journey->getStages();
        }
    }

    /**
     * Calculate the state
     *
     * @param bool $save
     */
    public function calculate($save = true)
    {
        // This code will not be run while updating actiivty since stage score and status is already calculate in
        // SmartGuideActivity, this code will be put in condition which will run in other scenarios but not in this scenario
        if ($this->journey->state === \DRI_SubWorkflow::STATE_CANCELLED || !$save) {
            return;
        }
        $this->journey->state = $this->getState();
    }

    /**
     * Calculate the Stage's state
     *
     * @param bool $save
     */
    public function calculateStageStates($save = true)
    {
        $this->load();

        foreach ($this->stages as $stage) {
            $calculator = $this->calculateStageState($stage);

            if ($save && $calculator->isStateChanged()) {
                $stage->save();
            }
        }
    }

    /**
     * @param $stage
     * @return \DRI_SubWorkflows\StateCalculator
     */
    private function calculateStageState($stage)
    {
        $calculator = new StageStateCalculator($stage);
        $calculator->calculate();

        return $calculator;
    }

    /**
     * @return string
     */
    public function getState()
    {
        $stages = $this->loadStageQuery();
        $count = safeCount((array)$stages);
        $notStarted = 0;
        $completed = 0;
        $deferred = 0;

        foreach ($stages as $stage) {
            switch ($stage['state']) {
                case \DRI_SubWorkflow::STATE_COMPLETED:
                    $completed++;
                    break;
                case \DRI_SubWorkflow::STATE_NOT_STARTED:
                    $notStarted++;
                    break;
                case \DRI_SubWorkflow::STATE_CANCELLED:
                    $deferred++;
                    break;
            }
        }

        if ($completed === $count) {
            return \DRI_Workflow::STATE_COMPLETED;
        }

        if ($notStarted === $count) {
            return \DRI_Workflow::STATE_NOT_STARTED;
        }

        if ($deferred + $completed === $count) {
            return \DRI_Workflow::STATE_CANCELLED;
        }

        return \DRI_Workflow::STATE_IN_PROGRESS;
    }

    /**
     * Loads the stages for specific journey
     *
     * @return array $stages
     */
    private function loadStageQuery()
    {
        $bean = \BeanFactory::newBean('DRI_SubWorkflows');
        $query = new \SugarQuery();
        $query->from($bean);
        $query->select('state');
        $query->orderBy('sort_order', 'ASC');
        $query->where()
            ->equals('dri_workflow_id', $this->journey->id);
        $stages = $query->execute();
        return $stages;
    }
}
