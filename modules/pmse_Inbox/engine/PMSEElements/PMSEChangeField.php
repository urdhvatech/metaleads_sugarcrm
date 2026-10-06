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


use Sugarcrm\Sugarcrm\ProcessManager;
use Sugarcrm\Sugarcrm\ProcessManager\Registry;

class PMSEChangeField extends PMSEScriptTask
{
    protected $beanList;
    protected $currentUser;
    protected $evaluator;
    protected $pmseRelatedModule;

    /**
     *
     * @global type $beanList
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        global $beanList, $current_user;
        $this->beanList = $beanList;
        $this->currentUser = $current_user;
        $this->evaluator = ProcessManager\Factory::getPMSEObject('PMSEEvaluator');
        $this->pmseRelatedModule = ProcessManager\Factory::getPMSEObject('PMSERelatedModule');
        parent::__construct();
    }

    /**
     *
     * @return type
     * @codeCoverageIgnore
     */
    public function getBeanList()
    {
        return $this->beanList;
    }

    /**
     *
     * @return type
     * @codeCoverageIgnore
     */
    public function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     *
     * @param type $beanList
     * @codeCoverageIgnore
     */
    public function setBeanList($beanList)
    {
        $this->beanList = $beanList;
    }

    /**
     *
     * @param type $currentUser
     * @codeCoverageIgnore
     */
    public function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
    }

    public function allowFieldAccess(
        SugarBean $bean,
        string $field,
        ?array $bpmDenylistedFields,
        array $allowedFields = []
    ): bool {
        //If $bpmDenylistedFields isn't set then use frontend visibility validation
        if (is_null($bpmDenylistedFields)) {
            return safeInArray(
                $field,
                array_column($allowedFields, 'value'),
            );
        }

        $moduleDenylistedFields = $bpmDenylistedFields[$bean->module_name] ?? [];

        return !safeInArray($field, $moduleDenylistedFields);
    }

    protected function getCrmDataWrapper()
    {
        return ProcessManager\Factory::getPMSEObject('PMSECrmDataWrapper');
    }

    public function setRegistry(?SugarBean $bean = null, bool $target = true)
    {
        return PMSEEngineUtils::setRegistry($bean, $target);
    }

    public function changeTeams($bean, $field)
    {
        return PMSEEngineUtils::changeTeams($bean, $field);
    }

    public function saveAssociatedBean(SugarBean $bean)
    {
        return PMSEEngineUtils::saveAssociatedBean($bean);
    }

    public function getEditableFields(SugarBean $bean): array
    {
        $crmDataWrapper = $this->getCrmDataWrapper();

        //Retrieve fields in Change Field Mode. The list of fields available for
        //editing through BPM will be returned.
        $res = $crmDataWrapper->retrieveFields($bean->module_name, null, 'CF');

        return $res['result'] ?? [];
    }

    public function allowUserIsAdminFieldAccess(?array $bpmDenylistedFields): bool
    {
        $userBean = BeanFactory::newBean('Users');

        return $this->allowFieldAccess(
            $userBean,
            'is_admin',
            $bpmDenylistedFields,
            []
        );
    }

    /**
     * This method prepares the response of the current element based on the
     * $bean object and the $flowData, an external action such as
     * ROUTE or ADHOC_REASSIGN could be also processed.
     *
     * This method probably should be override for each new element, but it's
     * not mandatory. However the response structure always must pass using
     * the 'prepareResponse' Method.
     *
     * As defined in the example:
     *
     * $response['route_action'] = 'ROUTE'; //The action that should process the Router
     * $response['flow_action'] = 'CREATE'; //The record action that should process the router
     * $response['flow_data'] = $flowData; //The current flowData
     * $response['flow_filters'] = array('first_id', 'second_id');
     * //This attribute is used to filter the execution of the following elements
     * $response['flow_id'] = $flowData['id']; // The flowData id if present
     *
     *
     * @param type $flowData
     * @param type $bean
     * @param type $externalAction
     * @return type
     */
    public function run($flowData, $bean = null, $externalAction = '', $arguments = [])
    {
        global $beanList;
        switch ($externalAction) {
            case 'RESUME_EXECUTION':
                $flowAction = 'UPDATE';
                break;
            default:
                $flowAction = 'CREATE';
                break;
        }

        $isRelated = false;
        $bpmnElement = $this->retrieveDefinitionData($flowData['bpmn_id']);
        $act_field_module = $bpmnElement['act_field_module'];
        $act_fields = htmlspecialchars_decode($bpmnElement['act_fields'], ENT_COMPAT);
        $fields = json_decode($act_fields);
        $ifields = 0;

        $idMainModule = $bean->id;
        $moduleName = $bean->module_name;

        $this->logger->info("[{$flowData['cas_id']}][{$flowData['cas_index']}] Getting $moduleName ID: $idMainModule");

        //Save original bean of project definition
        $beanModule = $bean;

        $beans = [];
        if (isset($bpmnElement['act_params'])) {
            $beans = $this->pmseRelatedModule->getChainedRelationshipBeans([$bean], json_decode($bpmnElement['act_params']));
            $isRelated = true;
        } elseif (!isset($beanList[$act_field_module])) {
            $beans[] = $this->pmseRelatedModule->getRelatedModule($bean, $act_field_module);
            $isRelated = true;
        } else {
            $beans[] = $bean;
        }

        $isStartCas = $flowData['cas_id'] === Registry\Registry::getInstance()->get('start_cas_id');
        $projectId = $isStartCas ? BeanFactory::getBean('pmse_BpmnProcess', $flowData['pro_id'])->prj_id : null;

        $bpmDenylistedFields = \SugarConfig::getInstance()->get('bpm_denylisted_fields');
        $allowUpdateIsAdmin = User::$allowUpdateIsAdmin;
        User::$allowUpdateIsAdmin = $this->allowUserIsAdminFieldAccess($bpmDenylistedFields);

        foreach ($beans as $bean) {
            if ($isStartCas) {
                Registry\Registry::getInstance()->set('process_attributes', ['project_id' => $projectId], true);
            }
            $this->setRegistry($beanModule);
            $this->setRegistry($bean, false);
            if (isset($bean) && is_object($bean)) {
                if ($act_field_module == $moduleName || $isRelated) {
                    $editableFields = $this->getEditableFields($bean);
                    foreach ($fields as $field) {
                        $infoMessage = sprintf(
                            '[%s][%s] Setting field %s to %s',
                            $flowData['cas_id'],
                            $flowData['cas_index'],
                            $field->field,
                            $field->value
                        );

                        $errorMessage = sprintf(
                            '[%s][%s] Field %s is not accessible in module %s',
                            $flowData['cas_id'],
                            $flowData['cas_index'],
                            $field->field,
                            $bean->module_name,
                        );

                        if (
                            $this->allowFieldAccess(
                                $bean,
                                $field->field,
                                $bpmDenylistedFields,
                                $editableFields
                            )
                        ) {
                            $this->logger->info($infoMessage);
                        } else {
                            $this->logger->critical($errorMessage);
                            continue;
                        }

                        if (isset($bean->field_defs[$field->field])) {
                            // check if of type link
                            if (
                                (isset($bean->field_defs[$field->field]['type'])) &&
                                ($bean->field_defs[$field->field]['type'] == 'link') &&
                                !(empty($bean->field_defs[$field->field]['name']))
                            ) {
                                // if its a link then go through cases on basis of "name" here.
                                // Currently only supporting teams
                                switch ($bean->field_defs[$field->field]['name']) {
                                    case 'teams':
                                        $this->changeTeams($bean, $field);
                                        break;
                                }
                            } elseif (
                                isset($bean->field_defs[$field->field]['type']) &&
                                $bean->field_defs[$field->field]['type'] == 'multienum'
                            ) {
                                $bean->{$field->field} = encodeMultienumValue($field->value);
                            } else {
                                if (!$this->emailHandler->doesPrimaryEmailExists($field, $bean, null)) {
                                    if (is_array($field->value)) {
                                        // Handle regular evaluation of values
                                        try {
                                            $newValue = $this->beanHandler->processValueExpression($field->value, $beanModule);
                                        } catch (PMSEExpressionEvaluationException $e) {
                                            if (
                                                $e->getCode() ===
                                                PMSEExpressionEvaluator::getExceptionCode('NO_BUSINESS_CENTER')
                                            ) {
                                                continue;
                                            } else {
                                                throw $e;
                                            }
                                        }
                                        // For null values only
                                        if (!isset($newValue)) {
                                            // Used to set these fields to null in db
                                            $newValue = '';
                                        } else {
                                            // Handle special field type processing
                                            $newValue = $this->handleFieldTypeProcessing($newValue, $field, $bean);
                                        }
                                    } else {
                                        if ($field->field == 'assigned_user_id') {
                                            $field->value = $this->getCustomUser($field->value, $beanModule);
                                        }
                                        $newValue = $this->beanHandler->mergeBeanInTemplate($beanModule, $field->value);
                                    }
                                    if (!empty($bean->field_defs[$field->field]['required'])) {
                                        $invalid = false;
                                        switch (gettype($newValue)) {
                                            case 'boolean':
                                            case 'integer':
                                            case 'double':
                                                break;
                                            case 'string':
                                                $invalid = !strlen($newValue);
                                                break;
                                            default:
                                                $invalid = empty($newValue);
                                        }
                                        if ($invalid) {
                                            throw new PMSEElementException('Cannot fill a required field ' . $field->field . ' with an empty value', $flowData, $this);
                                        }
                                    }

                                    // Validate relate field values (SUS-36)
                                    if (
                                        isset($bean->field_defs[$field->field]['type']) &&
                                        $bean->field_defs[$field->field]['type'] === 'relate'
                                    ) {
                                        $tempField = (object)[
                                            'field' => $field->field,
                                            'value' => $newValue,
                                        ];
                                        if (!$this->validateRelateFieldValue($bean, $tempField)) {
                                            $this->logger->warning(
                                                "[{$flowData['cas_id']}][{$flowData['cas_index']}] " .
                                                "Skipping invalid relate field {$field->field}"
                                            );
                                            continue 2;
                                        }
                                    }

                                    // Set the new value of the field onto the bean
                                    // For relate fields, set both the ID field and display field (SUS-36)
                                    if (
                                        isset($bean->field_defs[$field->field]['type']) &&
                                        $bean->field_defs[$field->field]['type'] === 'relate' &&
                                        isset($bean->field_defs[$field->field]['id_name'])
                                    ) {
                                        $idField = $bean->field_defs[$field->field]['id_name'];
                                        $bean->{$idField} = $newValue;
                                    }

                                    $bean->{$field->field} = $newValue;
                                }
                            }
                            $ifields++;
                        }
                    }
                    $this->setBeanNewWithId($bean);

                    // When mutiple PDs are triggered for the same target module, we need to register the dataChanges
                    // for later use as the saveAssociatedBean($bean) will override the original dataChanges in $bean.
                    if ($bean->dataChanges) {
                        $key = 'bean-data-changes-' . $bean->id;
                        $dc = Registry\Registry::getInstance()->get($key, []);
                        $dc = array_merge($dc, $bean->dataChanges);
                        Registry\Registry::getInstance()->set($key, $dc, true);
                    }

                    $this->saveAssociatedBean($bean);
                } else {
                    $this->logger->warning(
                        "[{$flowData['cas_id']}][{$flowData['cas_index']}] "
                        . "Trying to use '$act_field_module' fields to be set in $moduleName"
                    );
                }
                $this->logger->info(
                    "[{$flowData['cas_id']}][{$flowData['cas_index']}] "
                    . "number of fields changed: {$ifields}"
                );
            } else {
                $this->logger->info(
                    "[{$flowData['cas_id']}][{$flowData['cas_index']}] "
                    . 'Fields cannot be changed, none Module was set.'
                );
            }
        }

        $params = [];
        $params['cas_id'] = $flowData['cas_id'];
        $params['cas_index'] = $flowData['cas_index'];
        $params['act_id'] = $bpmnElement['id'];
        $params['pro_id'] = $bpmnElement['pro_id'];
        $params['user_id'] = $this->currentUser->id;
        $params['frm_action'] = 'Event Changed Fields';
        $params['frm_comment'] = 'Changed Fields Applied';
        $this->caseFlowHandler->saveFormAction($params);

        User::$allowUpdateIsAdmin = $allowUpdateIsAdmin;

        return $this->prepareResponse($flowData, 'ROUTE', $flowAction);
    }

    /**
     * @param $value
     * @param $fieldType
     * @return bool|float|string
     * @deprecated
     */
    public function postProcessValue($value, $fieldType)
    {
        global $timedate;
        switch (strtolower($fieldType)) {
            case 'date':
                $date = $timedate->fromIsoDate($value);
                $value = $date->asDbDate();
                break;
            case 'datetime':
            case 'datetimecombo':
                $date = $timedate->fromIso($value);
                $value = $date->asDb();
                break;
            case 'float':
            case 'double':
            case 'integer':
                $value = (float)$value;
                break;
            case 'string':
                $value = (string)$value;
                break;
            case 'boolean':
                $value = (bool)$value;
                break;
        }
        return $value;
    }

    /**
     * handle certain types of field types where the bean may need to be modified as well
     * @param type value
     * @param type field
     * @param type bean
     * @return value
     */
    public function handleFieldTypeProcessing($value, $field, $bean)
    {
        // Handle certain fields that require special handling
        switch (strtolower($field->type)) {
            case 'currency':
                // For currency fields, the return value is a json encoded string. So need to json_decode.
                $currencyFields = json_decode($value);
                if (is_object($currencyFields) && isset($currencyFields->expField) && isset($currencyFields->expValue)) {
                    // we need to take into account the type of currency too
                    $bean->currency_id = $currencyFields->expField;
                    $value = $currencyFields->expValue;
                }
                break;

            case 'datetime':
            case 'date':
                $value = $this->getDBDate($field, $value);
                break;
        }

        return $value;
    }

    /**
     * Set the new_with_id property of the bean based on its existence and the in_save and in_import states,
     * therefore determining if the $bean->save() method should perform an INSERT or UPDATE operation.
     * @param SugarBean bean
     */
    public function setBeanNewWithId($bean)
    {
        $isNew = !$bean->deleted && $bean->new_with_id && ($bean->in_save || $bean->in_import);
        $bean->new_with_id = $isNew;
    }

    /**
     * Validates a relate field value before setting it on the bean.
     * Checks if the related record exists.
     *
     * @param SugarBean $bean The bean containing the field
     * @param object $field The field definition with 'field' and 'value' properties
     * @return bool True if valid, false if invalid
     */
    protected function validateRelateFieldValue(SugarBean $bean, object $field): bool
    {
        if (!isset($bean->field_defs[$field->field])) {
            return false;
        }

        $fieldDef = $bean->field_defs[$field->field];

        if ($fieldDef['type'] !== 'relate') {
            return true;
        }

        if (empty($field->value)) {
            return true;
        }

        if (is_array($field->value)) {
            return true;
        }

        if (!empty($fieldDef['module'])) {
            $relatedBean = BeanFactory::getBean($fieldDef['module'], $field->value, ['deleted' => false]);
            if (empty($relatedBean) || empty($relatedBean->id)) {
                $this->logger->warning(
                    "Relate field {$field->field} validation failed - " .
                    "Invalid or non-existent related ID: {$field->value} for module {$fieldDef['module']}"
                );
                return false;
            }
        }

        return true;
    }
}
