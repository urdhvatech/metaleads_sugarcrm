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

use Sugarcrm\Sugarcrm\Dbal\Connection;

class SugarJobInitInteractions implements JsonSerializable, RunnableSchedulerJob
{
    private const PROCESS_CHUNK_SIZE = 100;
    private const JOB_ITERATION_DELAY = 2;
    private const SERIALIZABLE_PROPERTIES = [
        'totals', 'offset', 'module', 'related_field', 'related_value',
        'date_field', 'interaction_types', 'interaction_modules',
    ];

    /**
     * @var SchedulersJob
     */
    private SchedulersJob $job;

    /**
     * @var DBManager|false|null
     */
    private DBManager $db;

    /**
     * @var int
     */
    private int $offset = 0;

    /**
     * @var int
     */
    private int $totals = 0;

    /**
     * @var string $module
     */
    private string $module = '';

    /**
     * @var string
     */
    private string $related_field = '';

    /**
     * @var array
     */
    private array $related_value = [];

    /**
     * @var string
     */
    private string $date_field = '';

    /**
     * @var array
     */
    private array $interaction_modules = [];

    /**
     * @var array
     */
    private array $interaction_types = [];

    private array $unions = [];

    /**
     * SugarJobInitInteractions constructor.
     */
    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
    }

    /**
     * @param string $beanId
     * @param string $interactionType
     * @return string
     * @throws Exception
     */
    private function getInteractionQuery(string $beanId, string $interactionType)
    {
        $mainQuery = $this->db->getConnection()->createQueryBuilder();
        $order = $interactionType === 'last' ? 'DESC' : 'ASC';

        $innerSql = '(';

        foreach ($this->interaction_modules as $mIndex => $module) {
            $tableName = BeanFactory::newBean($module)->getTableName();
            $subQuery = $this->db->getConnection()->createQueryBuilder();
            $subQuery->select('id', 'name', $this->date_field, "'{$module}' AS parent")
                ->from($tableName)
                ->where('parent_id IS NOT NULL')
                ->andWhere("parent_id = ?")
                ->andWhere('deleted = 0')
                ->andWhere("status IN (?)")
                ->orderBy($this->date_field, $order)
                ->setMaxResults(1);

            $mainQuery->setParameter(($mIndex * 2), $beanId);
            $mainQuery->setParameter(($mIndex * 2 + 1), $this->related_value, Connection::PARAM_STR_ARRAY);

            $innerSql .= $subQuery->getSQL() . ') UNION (';
        }

        $innerSql = rtrim($innerSql, ' UNION (');

        $mainQuery->select('sub.id', 'sub.name', 'sub.' . $this->date_field, 'sub.parent')
            ->from('(' . $innerSql . ')', 'sub')
            ->orderBy('sub.' . $this->date_field, $order)
            ->setMaxResults(1);

        return $mainQuery;
    }

    /**
     * @return void
     * @throws SugarApiExceptionNotFound
     * @throws \Doctrine\DBAL\Exception
     */
    private function initLastInteraction(): void
    {
        $this->setProgress();
        $this->job->message = 'Started interactions initialization';

        $this->updateJobData();
        $tableName = BeanFactory::newBean($this->module)->getTableName();

        $sql = 'SELECT id FROM ' . $tableName . ' WHERE deleted = 0';

        $sql = $this->db->getConnection()->getDatabasePlatform()->modifyLimitQuery($sql, self::PROCESS_CHUNK_SIZE, $this->offset);
        $moduleRecords = $this->db->getConnection()->fetchAllAssociative($sql);

        foreach ($this->interaction_types as $interactionType) {
            $interactionDate = $interactionType . '_interaction_date';
            $interactionParentType = $interactionType . '_interaction_parent_type';
            $interactionParentId = $interactionType . '_interaction_parent_id';
            $interactionParentName = $interactionType . '_interaction_parent_name';

            foreach ($moduleRecords as $record) {
                $query = $this->getInteractionQuery($record['id'], $interactionType);

                $interaction = $query->executeQuery()->fetchAssociative();

                if (empty($interaction)) {
                    continue;
                }

                $queryBuilder = $this->db->getConnection()->createQueryBuilder();
                $queryBuilder->update($tableName)
                    ->set($interactionDate, ':interaction_date')
                    ->set($interactionParentType, ':interaction_parent_type')
                    ->set($interactionParentId, ':interaction_parent_id')
                    ->set($interactionParentName, ':interaction_parent_name')
                    ->where('id = :beanId')
                    ->setParameter('beanId', $record['id'])
                    ->setParameter('interaction_date', $interaction[$this->date_field])
                    ->setParameter('interaction_parent_type', $interaction['parent'])
                    ->setParameter('interaction_parent_id', $interaction['id'])
                    ->setParameter('interaction_parent_name', $interaction['name'])
                    ->executeQuery();

                $this->offset++;
            }
        }

        $this->setProgress();
        $this->updateJobData();

        if ($this->offset >= $this->totals) {
            $this->job->message = 'Finished iteration... waiting for next one...';
            $this->setData();
            $this->succeedJob();
        } else {
            $this->job->message = 'waiting for the next iteration';
            $this->setData();
            $this->job->postponeJob(null, self::JOB_ITERATION_DELAY);
        }
    }

    /**
     * @param $data
     * @return void
     * @throws Exception
     */
    private function initialize($data): void
    {
        if (empty($data)) {
            throw new Exception('Job data is empty');
        }

        $decodedData = json_decode($data, true);
        foreach ($decodedData as $prop => $value) {
            $this->{$prop} = $value;
        }

        if (0 === $this->totals) {
            $tableName = BeanFactory::newBean($this->module)->getTableName();
            $this->totals = (int)$this->db->getOne("SELECT COUNT(*) FROM $tableName");
        }
    }

    /**
     * @return void
     */
    private function setData(): void
    {
        $this->job->data = json_encode($this);
    }

    /**
     * @return void
     */
    private function succeedJob(): void
    {
        $this->job->message = "Last interaction job completed";
        $this->job->succeedJob();
    }

    /**
     * @return void
     */
    private function setProgress(): void
    {
        if ($this->totals > 0) {
            $this->job->percent_complete = ($this->offset / $this->totals) * 100;
            $this->job->percent_complete = $this->job->percent_complete > 100 ? 100 : $this->job->percent_complete;
        } else {
            $this->job->percent_complete = 100;
        }
    }

    /**
     * @return void
     */
    private function updateJobData(): void
    {
        $this->setData();
        $this->job->save();
    }

    /**
     * @param SchedulersJob $job
     * @return void
     */
    public function setJob(SchedulersJob $job)
    {
        $this->job = $job;
    }

    /**
     * @param $data
     * @return true
     * @throws Exception
     */
    public function run($data)
    {
        $this->initialize($data);
        $this->initLastInteraction();

        return true;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $data = [];

        foreach (self::SERIALIZABLE_PROPERTIES as $property) {
            $data[$property] = $this->{$property};
        }

        return $data;
    }
}
