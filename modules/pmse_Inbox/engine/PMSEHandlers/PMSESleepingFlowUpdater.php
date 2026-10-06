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
declare(strict_types=1);


use Sugarcrm\Sugarcrm\ProcessManager;

/**
 * Handles updating sleeping timer events when date fields referenced in
 * Fixed Date expressions change on the related bean.
 *
 * This ensures timer events stay synchronized with the current data when
 * users modify date fields that timer expressions depend on.
 */
class PMSESleepingFlowUpdater
{
    protected PMSELogger $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = PMSELogger::getInstance();
    }

    /**
     * Updates sleeping timer events for a bean by recalculating their dates
     *
     * This method finds all SLEEPING timer events for the given bean and
     * recalculates their due dates based on the current bean data. This is
     * called automatically after every bean save to keep timers in sync.
     *
     * @param SugarBean $bean The bean that was saved
     * @return void
     */
    public function updateSleepingFlowsForBean(SugarBean $bean): void
    {
        $flows = $this->findSleepingTimerFlows($bean);

        if (empty($flows)) {
            return;
        }

        foreach ($flows as $flow) {
            try {
                $eventDef = $this->getEventDefinition($flow['bpmn_id']);

                if (empty($eventDef) || empty($eventDef['evn_criteria'])) {
                    continue;
                }

                if (!isset($eventDef['evn_marker']) || strcasecmp((string)$eventDef['evn_marker'], 'TIMER') !== 0) {
                    continue;
                }

                $newDueDate = $this->evaluateDateExpression($eventDef['evn_criteria'], $bean);

                if ($newDueDate && $flow['cas_due_date'] !== $newDueDate) {
                    $this->updateFlowDates($flow['id'], $newDueDate);
                }
            } catch (Throwable $e) {
                $this->logger->error(
                    "Error updating sleeping flow {$flow['id']}: " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Finds sleeping timer events for a given bean
     *
     * @param SugarBean $bean
     * @return array
     */
    protected function findSleepingTimerFlows(SugarBean $bean): array
    {
        $q = new SugarQuery();
        $q->select(['id', 'bpmn_id', 'cas_due_date', 'cas_flow_status']);
        $q->from(BeanFactory::newBean('pmse_BpmFlow'));
        $q->where()
            ->equals('cas_sugar_object_id', $bean->id)
            ->equals('cas_sugar_module', $bean->module_name)
            ->equals('cas_flow_status', 'SLEEPING')
            ->equals('bpmn_type', 'bpmnEvent')
            ->equals('deleted', 0);

        return $q->execute();
    }

    /**
     * Gets the event definition for a timer event
     *
     * @param string $bpmnId The ID of the event definition (from flow's bpmn_id)
     * @return array|null Array with evn_id, evn_criteria, evn_marker or null
     */
    protected function getEventDefinition(string $bpmnId): ?array
    {
        $bean = BeanFactory::retrieveBean('pmse_BpmEventDefinition', $bpmnId);

        if (empty($bean) || empty($bean->id) || empty($bean->evn_criteria)) {
            return null;
        }

        return [
            'evn_id' => $bean->id,
            'evn_criteria' => $bean->evn_criteria,
            'evn_marker' => $bean->evn_marker ?? 'TIMER',
        ];
    }

    /**
     * Evaluates a Fixed Date expression and returns the calculated date
     *
     * This method parses Process Author Fixed Date expressions which support:
     * - Simple: [field]
     * - With offset: [field, operator, offset] e.g., [date_due, -, 1d]
     * - Complex: [field, op1, offset1, op2, offset2, ...] e.g., [date_due, -, 1d, +, 2h]
     * - Business hours: [field, operator, business_hours_offset, bc_bean_id]
     *
     * The expression is evaluated by extracting the base date from the bean,
     * then applying operators and offsets sequentially.
     *
     * @param string $criteria JSON encoded criteria
     * @param SugarBean $bean The bean containing the date field
     * @return string|null Date in database format (Y-m-d H:i:s) or null on failure
     */
    protected function evaluateDateExpression(string $criteria, SugarBean $bean): ?string
    {
        try {
            $tokens = json_decode(html_entity_decode($criteria, ENT_COMPAT));

            if (!is_array($tokens) || count($tokens) < 1) {
                return null;
            }

            $baseDate = $this->getBaseDateFromTokens($tokens, $bean);
            if (!$baseDate) {
                return null;
            }

            // Apply offsets sequentially
            // Format: [field, op1, offset1, op2, offset2, ...]
            for ($i = 1; $i < count($tokens) - 1; $i += 2) {
                $operator = $tokens[$i]->expValue ?? null;
                $offsetToken = $tokens[$i + 1] ?? null;

                if (!$offsetToken || !$operator) {
                    continue;
                }

                $offset = $offsetToken->expValue ?? null;
                $businessCenterId = $offsetToken->expBean ?? null;

                if ($offset && in_array($operator, ['+', '-'], true)) {
                    $baseDate = $this->applyDateOffset($baseDate, $operator, $offset, $businessCenterId);
                }
            }

            return $baseDate->asDb();
        } catch (Throwable $e) {
            $this->logger->error("Error evaluating date expression: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extracts the base date from expression tokens
     *
     * @param array $tokens Expression tokens
     * @param SugarBean $bean Bean containing the date field
     * @return SugarDateTime|null
     */
    protected function getBaseDateFromTokens(array $tokens, SugarBean $bean): ?SugarDateTime
    {
        if (empty($tokens[0]) || !isset($tokens[0]->expValue)) {
            return null;
        }

        $fieldName = $tokens[0]->expValue;

        // Try to get field value - will be null/empty if not set
        $fieldValue = $bean->$fieldName ?? null;

        if (empty($fieldValue)) {
            return null;
        }

        $date = TimeDate::getInstance()->fromDb($fieldValue);

        if (!$date) {
            return null;
        }

        return $date;
    }

    /**
     * Applies a date offset (e.g., +1d, -2w, +2bh) to a date
     *
     * Handles standard date units (y, m, w, d, h, min) and business hours (bh).
     * Note: 'm' is months, 'min' is minutes (following SugarCRM convention).
     * For business hours, delegates to PMSEExpressionEvaluator which handles
     * business center calendars correctly.
     *
     * @param SugarDateTime $date Base date
     * @param string $operator '+' or '-'
     * @param string $offset Offset string like '1d', '2w', '2bh', '1y', '5min', '3m' (months)
     * @param string|null $businessCenterId Business center ID for business hours
     * @return SugarDateTime Modified date
     * @throws Exception If offset format is invalid
     */
    protected function applyDateOffset(SugarDateTime $date, string $operator, string $offset, ?string $businessCenterId = null): SugarDateTime
    {
        if (PMSEEngineUtils::isForBusinessTimeOp($offset)) {
            return $this->applyBusinessHoursOffset($date, $operator, $offset, $businessCenterId);
        }

        // Use PMSEExpressionEvaluator to parse and process the offset
        $evaluator = ProcessManager\Factory::getPMSEObject('PMSEExpressionEvaluator');
        $dateInterval = $evaluator->processDateInterval($offset);

        if ($operator === '-') {
            $date->sub($dateInterval);
        } else {
            $date->add($dateInterval);
        }

        return $date;
    }

    /**
     * Applies business hours offset using business center calendar
     *
     * @param SugarDateTime $date Base date
     * @param string $operator '+' or '-'
     * @param string $offset Business hours offset (e.g., '2bh')
     * @param string|null $businessCenterId Business center bean ID
     * @return SugarDateTime Modified date
     * @throws Exception If business hours cannot be applied
     */
    protected function applyBusinessHoursOffset(SugarDateTime $date, string $operator, string $offset, ?string $businessCenterId): SugarDateTime
    {
        if (!$businessCenterId) {
            $this->logger->warning(
                "Business hours offset '{$offset}' requires business center ID; skipping business-hours adjustment."
            );

            throw new Exception("Business hours offset '{$offset}' requires business center ID");
        }

        $evaluator = ProcessManager\Factory::getPMSEObject('PMSEExpressionEvaluator');

        $result = $evaluator->executeDateSpanBCOp(
            $date->asDb(),
            $operator,
            $offset,
            $businessCenterId
        );

        // Normalize to SugarDateTime regardless of return type
        if ($result instanceof SugarDateTime) {
            return $result;
        }
        if ($result instanceof DateTimeInterface) {
            return TimeDate::getInstance()->fromDb($result->format('Y-m-d H:i:s'));
        }
        // Assume string
        return TimeDate::getInstance()->fromDb((string)$result);
    }

    /**
     * Updates the cas_due_date and cas_delegate_date for a flow atomically
     *
     * Uses a conditional UPDATE to avoid race conditions where the flow status
     * changes between read and write operations. Only updates if the flow is
     * still in SLEEPING status.
     *
     * @param string $flowId Flow ID
     * @param string $newDueDate Date in database format
     * @return bool Success status (true if exactly one row was updated)
     */
    protected function updateFlowDates(string $flowId, string $newDueDate): bool
    {
        try {
            $db = DBManagerFactory::getInstance();
            $conn = $db->getConnection();

            $flowBean = BeanFactory::newBean('pmse_BpmFlow');
            $table = $flowBean->getTableName();
            $sql = "UPDATE {$table} 
                    SET cas_due_date = ?, 
                        cas_delegate_date = ? 
                    WHERE id = ? 
                      AND cas_flow_status = ? 
                      AND deleted = 0";

            $rows = $conn->executeStatement($sql, [
                $newDueDate,
                $newDueDate,
                $flowId,
                'SLEEPING',
            ]);

            if ($rows === 0) {
                $this->logger->warning(
                    "Flow {$flowId} was not updated - either not found, not SLEEPING, or already deleted"
                );
                return false;
            }

            return true;
        } catch (Throwable $e) {
            $this->logger->error(
                "Failed to update flow {$flowId}: " . $e->getMessage()
            );
            return false;
        }
    }
}
