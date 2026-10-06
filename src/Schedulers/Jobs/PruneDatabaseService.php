<?php

declare(strict_types=1);
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

namespace Sugarcrm\Sugarcrm\Schedulers\Jobs;

use DateTime;
use DateInterval;
use DBManagerFactory;
use JsonException;
use JsonSerializable;
use RunnableSchedulerJob;
use SchedulersJob;
use SugarConfig;
use Throwable;

/**
 * Service class for database pruning operations.
 * Handles failure tracking, table prioritization, and batch deletion with transaction safety.
 */
class PruneDatabaseService implements RunnableSchedulerJob, JsonSerializable
{
    private const DEFAULT_BATCH_SIZE = 500;
    private const DEFAULT_PRUNE_DELAY_MS = 24 * 60 * 60 * 1000;
    private const DEFAULT_MAX_DURATION = 20 * 60; // 20 minutes
    private const DEFAULT_MAX_RETRY_COUNT = 3;
    private const DEFAULT_FAILURE_RESET_DAYS = 7;
    private const DEFAULT_DEADLOCK_RETRY_ATTEMPTS = 3;
    private const DEFAULT_DEADLOCK_RETRY_DELAYS = [100, 500, 2000];
    private const JOB_ITERATION_DELAY = 5;
    private const TABLES_PER_ITERATION = 10;

    // Job internal status
    private const STATUS_INIT = 1;
    private const STATUS_PROCESS = 2;
    private const STATUS_COMPLETE = 3;

    private const SERIALIZABLE_PROPERTIES = [
        'status',
        'current_table_index',
        'total_tables',
        'processed_tables',
        'failed_tables',
        'failure_counts',
        'last_failure_timestamps',
        'threshold_time',
        'batch_size',
        'prune_delay_seconds',
        'max_duration',
        'max_table_retry_count',
        'failure_reset_days',
        'enable_failure_tracking',
        'tables_to_process',
        'deadlock_retry_attempts',
        'deadlock_retry_delays',
    ];

    /**
     * @var SchedulersJob
     */
    private $job;

    /**
     * @var SugarConfig
     */
    private $config;

    /**
     * @var int
     */
    private $status;

    /**
     * @var int
     */
    private $current_table_index = 0;

    /**
     * @var int
     */
    private $total_tables = 0;

    /**
     * @var int
     */
    private $processed_tables = 0;

    /**
     * @var array
     */
    private $failed_tables = [];

    /**
     * @var array
     */
    private $failure_counts = [];

    /**
     * @var array
     */
    private $last_failure_timestamps = [];

    /**
     * @var string
     */
    private $threshold_time;

    /**
     * @var int
     */
    private $batch_size;

    /**
     * @var int
     */
    private $prune_delay_seconds;

    /**
     * @var int
     */
    private $max_duration;

    /**
     * @var int
     */
    private $max_table_retry_count;

    /**
     * @var int
     */
    private $failure_reset_days;

    /**
     * @var bool
     */
    private $enable_failure_tracking;

    /**
     * @var array
     */
    private $tables_to_process = [];

    /**
     * @var int
     */
    private $deadlock_retry_attempts;

    /**
     * @var array
     */
    private $deadlock_retry_delays;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->config = SugarConfig::getInstance();
    }

    /**
     * Set the job instance.
     *
     * @param SchedulersJob $job
     */
    public function setJob(SchedulersJob $job): void
    {
        $this->job = $job;
    }

    /**
     * Run the job with the given data.
     *
     * @param string $data The job data set for this particular Scheduled Job instance
     * @return bool True if the run succeeded; false otherwise
     */
    public function run($data): bool
    {
        $this->initialize($data);

        switch ($this->status) {
            case self::STATUS_INIT:
                $this->doInit();
                break;

            case self::STATUS_PROCESS:
                $this->doProcess();
                break;

            case self::STATUS_COMPLETE:
                $this->doComplete();
                break;

            default:
                return false;
        }

        return true;
    }

    /**
     * Initialize job state from serialized data.
     *
     * @param string $data JSON-encoded job data
     */
    private function initialize(string $data): void
    {
        if (!empty($data)) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                foreach ($decoded as $property => $value) {
                    if (property_exists($this, $property)) {
                        $this->{$property} = $value;
                    }
                }
            }
        }

        // Initialize status if not set
        if (!isset($this->status)) {
            $this->status = self::STATUS_INIT;
        }
    }

    /**
     * Serialize job state to JSON.
     */
    private function setData(): void
    {
        $this->job->data = json_encode($this);
    }

    /**
     * Update job data in database.
     */
    private function updateJobData(): void
    {
        $this->setData();
        $this->job->save();
    }

    /**
     * Set job status and update data.
     *
     * @param int $status
     */
    private function setStatus(int $status): void
    {
        $this->status = $status;
        $this->updateJobData();
    }

    /**
     * Calculate and set progress percentage.
     */
    private function setProgress(): void
    {
        if ($this->total_tables > 0) {
            $this->job->percent_complete = ($this->processed_tables / $this->total_tables) * 100;
            $this->job->percent_complete = min(100, $this->job->percent_complete);
        } else {
            $this->job->percent_complete = 100;
        }
    }

    /**
     * Serialize object for JSON encoding.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $data = [];
        foreach (self::SERIALIZABLE_PROPERTIES as $property) {
            if (property_exists($this, $property)) {
                $data[$property] = $this->{$property};
            }
        }
        return $data;
    }

    /**
     * Initialize phase: load configuration and prepare tables list.
     */
    private function doInit(): void
    {
        $this->job->message = 'Initializing database pruning...';
        $GLOBALS['log']->info('PruneDatabase: Initializing job');

        // Initialize arrays if not set
        if (!isset($this->failed_tables)) {
            $this->failed_tables = [];
        }
        if (!isset($this->failure_counts)) {
            $this->failure_counts = [];
        }
        if (!isset($this->last_failure_timestamps)) {
            $this->last_failure_timestamps = [];
        }

        // Load configuration
        $this->batch_size = $this->config->get('prune_job_batch_size', self::DEFAULT_BATCH_SIZE);
        $pruneDelay = $this->config->get('prune_delay', self::DEFAULT_PRUNE_DELAY_MS);
        $this->prune_delay_seconds = max(0, (int) round($pruneDelay / 1000));
        $this->max_duration = $this->config->get('prune_job.max_duration', self::DEFAULT_MAX_DURATION);
        $this->enable_failure_tracking = $this->config->get('prune_job.enable_failure_tracking', true);
        $this->max_table_retry_count = $this->config->get('prune_job.max_table_retry_count', self::DEFAULT_MAX_RETRY_COUNT);
        $this->failure_reset_days = $this->config->get('prune_job.failure_reset_days', self::DEFAULT_FAILURE_RESET_DAYS);
        $this->deadlock_retry_attempts = $this->config->get('prune_job.deadlock_retry_attempts', self::DEFAULT_DEADLOCK_RETRY_ATTEMPTS);
        $this->deadlock_retry_delays = $this->config->get('prune_job.deadlock_retry_delay_ms', self::DEFAULT_DEADLOCK_RETRY_DELAYS);

        // Calculate threshold time
        $thresholdTime = new DateTime();
        $thresholdTime->sub(new DateInterval('PT' . $this->prune_delay_seconds . 'S'));
        $this->threshold_time = $thresholdTime->format('Y-m-d H:i:s');

        $now = (new DateTime())->format('Y-m-d H:i:s');
        $phpTimezone = date_default_timezone_get();

        $GLOBALS['log']->info(
            "PruneDatabase: Configuration - batch_size: {$this->batch_size}, " .
            "prune_delay_seconds: {$this->prune_delay_seconds}, " .
            "threshold_time: {$this->threshold_time}, " .
            "max_duration: {$this->max_duration}s, " .
            "php_now: {$now}, " .
            "php_timezone: {$phpTimezone}"
        );

        // Get all tables
        $db = DBManagerFactory::getInstance();
        $tables = $db->getTablesArray();

        if (empty($tables)) {
            $this->total_tables = 0;
            $this->processed_tables = 0;
            $this->tables_to_process = [];
            $this->job->message = 'No tables found to process';
            $this->setData();
            $this->job->succeedJob();
            return;
        }

        // Load failure tracking state if enabled
        if ($this->enable_failure_tracking && empty($this->failure_counts)) {
            $oldState = $this->getJobState($this->job);
            $this->failed_tables = $oldState['failed_tables'] ?? [];
            $this->failure_counts = $oldState['failure_counts'] ?? [];
            $this->last_failure_timestamps = $oldState['last_failure_timestamps'] ?? [];

            if (!empty($this->failure_counts)) {
                $failedTablesLog = implode(', ', array_keys($this->failure_counts));
                $GLOBALS['log']->info(
                    "PruneDatabase: Loaded failure tracking state - " .
                    "tables with failures: {$failedTablesLog}"
                );
            }

            // Reset old failures
            foreach (array_keys($this->failure_counts) as $table) {
                $this->resetTableFailureCount($table);
            }
        }

        // Prioritize tables
        $allTablesCount = count($tables);
        $this->tables_to_process = $this->enable_failure_tracking
            ? $this->prioritizeTables($tables)
            : $tables;

        $this->total_tables = count($this->tables_to_process);
        $skippedCount = $allTablesCount - $this->total_tables;
        $this->current_table_index = 0;
        $this->processed_tables = 0;

        $GLOBALS['log']->info(
            "PruneDatabase: Initialized with {$this->total_tables} tables to process " .
            ($skippedCount > 0 ? "({$skippedCount} skipped due to failure tracking)" : "")
        );

        $this->setStatus(self::STATUS_PROCESS);
        $this->job->postponeJob(null, self::JOB_ITERATION_DELAY);
    }

    /**
     * Process phase: process tables in batches.
     */
    private function doProcess(): void
    {
        $startTime = microtime(true);
        $maxDuration = $this->max_duration;
        $db = DBManagerFactory::getInstance();
        $allTables = $db->getTablesArray();

        $tablesProcessedThisIteration = 0;

        while ($this->current_table_index < $this->total_tables && $tablesProcessedThisIteration < self::TABLES_PER_ITERATION) {
            $table = $this->tables_to_process[$this->current_table_index];

            $this->job->message = sprintf(
                'Processing table %d of %d: %s (threshold: %s)',
                $this->current_table_index + 1,
                $this->total_tables,
                $table,
                $this->threshold_time
            );
            $GLOBALS['log']->info($this->job->message);

            // Check if table exists before processing
            if (!in_array($table, $allTables)) {
                $GLOBALS['log']->debug("PruneDatabase: Skipping table {$table} (table does not exist)");
                $this->current_table_index++;
                $this->processed_tables++;
                $this->setProgress();
                $this->updateJobData();
                $tablesProcessedThisIteration++;
                continue;
            }

            // Get table columns to check for required fields BEFORE querying
            $columns = $db->get_columns($table);

            // Skip tables without required columns
            if (empty($columns['deleted']) || empty($columns['date_modified'])) {
                $GLOBALS['log']->debug("PruneDatabase: Skipping table {$table} (missing required columns)");
                $this->current_table_index++;
                $this->processed_tables++;
                $this->setProgress();
                $this->updateJobData();
                $tablesProcessedThisIteration++;
                continue;
            }

            // Verify how many records are eligible for deletion
            $verifyCount = $db->getConnection()->createQueryBuilder()
                ->select('COUNT(*) as cnt')
                ->from($table)
                ->where('deleted = :deleted')
                ->andWhere('date_modified < :threshold')
                ->setParameter('deleted', 1)
                ->setParameter('threshold', $this->threshold_time)
                ->executeQuery()
                ->fetchOne();

            $GLOBALS['log']->info(
                "PruneDatabase: Table '{$table}' has {$verifyCount} records eligible for deletion " .
                "(deleted=1 AND date_modified < '{$this->threshold_time}')"
            );

            // Check if table has custom table
            $hasCstm = false;
            if (in_array($table . '_cstm', $allTables)) {
                $custom_columns = $db->get_columns($table . '_cstm');
                $hasCstm = !empty($custom_columns['id_c']);
            }

            // Check if table has audit table
            $hasAudit = false;
            if (in_array($table . '_audit', $allTables)) {
                $custom_columns = $db->get_columns($table . '_audit');
                $hasAudit = !empty($custom_columns['parent_id']);
            }

            // Process table
            $result = $this->batchDeleteFromTable(
                $table,
                $hasCstm,
                $this->batch_size,
                $this->threshold_time,
                $maxDuration,
                $startTime,
                [
                    'attempts' => $this->deadlock_retry_attempts,
                    'delays' => $this->deadlock_retry_delays,
                ],
                $hasAudit
            );

            // Check time limit FIRST before marking table as processed
            if ($result['timeout'] || (microtime(true) - $startTime) > $maxDuration) {
                // Timeout reached - do NOT increment table index so we continue this table next run
                if ($result['success']) {
                    $GLOBALS['log']->info(
                        "PruneDatabase: Table '{$table}' partially processed, " .
                        "timeout reached. Will continue this table in next run."
                    );
                } else {
                    $GLOBALS['log']->error(
                        "PruneDatabase: Failed to process table {$table}: " .
                        ($result['error'] ?? 'Unknown error')
                    );
                    if ($this->enable_failure_tracking) {
                        $this->recordTableFailure($table, $result['error'] ?? 'Unknown error');
                    }
                }
                
                $this->job->message = sprintf(
                    'Time limit reached while processing table %d of %d: %s. Postponing...',
                    $this->current_table_index + 1,
                    $this->total_tables,
                    $table
                );
                $GLOBALS['log']->info($this->job->message);
                $this->updateJobData();
                $this->job->postponeJob(null, self::JOB_ITERATION_DELAY);
                return;
            }

            // Handle result - only reached if no timeout
            if ($result['success']) {
                if ($this->enable_failure_tracking) {
                    $this->clearTableFailure($table);
                }
                $GLOBALS['log']->info("PruneDatabase: Successfully processed table {$table}");
            } else {
                if ($this->enable_failure_tracking) {
                    $this->recordTableFailure($table, $result['error'] ?? 'Unknown error');
                }
                $GLOBALS['log']->error("PruneDatabase: Failed to process table {$table}: " . ($result['error'] ?? 'Unknown error'));
            }

            // Only increment counters if table fully processed (no timeout)
            $this->current_table_index++;
            $this->processed_tables++;
            $this->setProgress();
            $this->updateJobData();
            $tablesProcessedThisIteration++;
        }

        // Check if all tables processed
        if ($this->current_table_index >= $this->total_tables) {
            $this->setStatus(self::STATUS_COMPLETE);
            $this->doComplete();
            return;
        }

        $this->job->message = sprintf(
            'Waiting %d seconds for next iteration (%d/%d tables complete)',
            self::JOB_ITERATION_DELAY,
            $this->processed_tables,
            $this->total_tables
        );
        $this->setProgress();
        $this->setData();
        $this->job->postponeJob(null, self::JOB_ITERATION_DELAY);
    }

    /**
     * Complete phase: finalize job.
     */
    private function doComplete(): void
    {
        $failedCount = count($this->failed_tables);
        $successCount = $this->processed_tables - $failedCount;

        $this->job->message = sprintf(
            'Database pruning complete. Processed %d tables: %d successful, %d failed.',
            $this->processed_tables,
            $successCount,
            $failedCount
        );

        if ($failedCount > 0) {
            $GLOBALS['log']->warning(
                "PruneDatabase: Completed with {$failedCount} failed tables: " .
                implode(', ', $this->failed_tables)
            );
        } else {
            $GLOBALS['log']->info('PruneDatabase: Completed successfully');
        }

        $this->job->percent_complete = 100;
        $this->setData(); // Save final state before succeeding
        $this->job->succeedJob();
    }

    /**
     * Execute database pruning job (legacy/backward compatible entry point).
     *
     * @param SchedulersJob|null $job The job instance
     * @return bool Success status
     */
    public function execute(?SchedulersJob $job = null): bool
    {
        // If called with a job, use the new pattern
        if ($job !== null) {
            $this->setJob($job);
            return $this->run($job->data ?? '');
        }

        // Legacy direct execution without job context - not recommended
        $GLOBALS['log']->warning('PruneDatabase: execute() called without job context - this is deprecated');
        return false;
    }

    /**
     * Retrieves the prune job state from the SchedulersJob data field.
     * Used for loading persistent state on initialization.
     *
     * @param SchedulersJob|null $job The job instance
     * @return array State structure with failure tracking data
     */
    private function getJobState(?SchedulersJob $job): array
    {
        $defaultState = [
            'failed_tables' => [],
            'last_processed_table' => null,
            'failure_counts' => [],
            'last_failure_timestamps' => [],
        ];

        if ($job === null || empty($job->data)) {
            return $defaultState;
        }

        try {
            $state = json_decode($job->data, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($state)) {
                $GLOBALS['log']->warning('pruneDatabase: Invalid state data, using defaults');
                return $defaultState;
            }

            // Extract only the failure tracking fields
            return [
                'failed_tables' => $state['failed_tables'] ?? [],
                'last_processed_table' => $state['last_processed_table'] ?? null,
                'failure_counts' => $state['failure_counts'] ?? [],
                'last_failure_timestamps' => $state['last_failure_timestamps'] ?? [],
            ];
        } catch (JsonException $e) {
            $GLOBALS['log']->error('pruneDatabase: Failed to decode job state: ' . $e->getMessage());
            return $defaultState;
        }
    }

    /**
     * Resets table failure count if the failure is older than configured threshold.
     *
     * @param string $table The table name
     * @return void
     */
    private function resetTableFailureCount(string $table): void
    {
        if (!isset($this->last_failure_timestamps[$table])) {
            return;
        }

        $lastFailureTimestamp = $this->last_failure_timestamps[$table];
        $thresholdTimestamp = time() - ($this->failure_reset_days * 24 * 60 * 60);

        if ($lastFailureTimestamp < $thresholdTimestamp) {
            // Reset failure count and remove from failed tables list
            unset($this->failure_counts[$table]);
            unset($this->last_failure_timestamps[$table]);
            $this->failed_tables = array_values(
                array_diff($this->failed_tables, [$table])
            );

            $GLOBALS['log']->info(
                "PruneDatabase: Reset failure count for table '{$table}' (failure older than {$this->failure_reset_days} days)"
            );
        }
    }

    /**
     * Records a table failure.
     *
     * @param string $table The table name
     * @param string $error The error message
     * @return void
     */
    private function recordTableFailure(string $table, string $error): void
    {
        $this->failure_counts[$table] = ($this->failure_counts[$table] ?? 0) + 1;
        $this->last_failure_timestamps[$table] = time();

        if (!in_array($table, $this->failed_tables)) {
            $this->failed_tables[] = $table;
        }

        $GLOBALS['log']->error("PruneDatabase: Failed to prune table '{$table}': {$error}");
    }

    /**
     * Clears a table's failure tracking.
     *
     * @param string $table The table name
     * @return void
     */
    private function clearTableFailure(string $table): void
    {
        if (isset($this->failure_counts[$table])) {
            unset($this->failure_counts[$table]);
            unset($this->last_failure_timestamps[$table]);
            $this->failed_tables = array_values(
                array_diff($this->failed_tables, [$table])
            );
        }
    }

    /**
     * Prioritizes tables for pruning based on failure history.
     * Returns an array of tables ordered by priority, excluding tables that exceeded retry limit.
     *
     * @param array $allTables All available tables in the database
     * @return array Prioritized array of tables to process
     */
    private function prioritizeTables(array $allTables): array
    {
        $neverFailed = [];
        $failedButRetryable = [];
        $exceededRetries = [];

        foreach ($allTables as $table) {
            $failureCount = $this->failure_counts[$table] ?? 0;

            if ($failureCount === 0) {
                // Priority 1: Tables that never failed
                $neverFailed[] = $table;
            } elseif ($failureCount < $this->max_table_retry_count) {
                // Priority 2: Failed tables that can still be retried
                $failedButRetryable[] = $table;
            } else {
                // Exceeded retry limit - skip and log
                $exceededRetries[] = $table;
            }
        }

        // Log tables that will be skipped
        foreach ($exceededRetries as $table) {
            $failureCount = $this->failure_counts[$table];
            $GLOBALS['log']->error(
                "PruneDatabase: Skipping table '{$table}' after {$failureCount} failed attempts. " .
                "Manual intervention required. Consider pruning this table manually or investigating " .
                "the cause of failures (timeout, deadlock, or table structure issues)."
            );
        }

        // Return prioritized list: never failed first, then retryable failed tables
        return array_merge($neverFailed, $failedButRetryable);
    }

    /**
     * Performs batch deletion on a table with optional custom table support.
     * Wraps deletes in transactions and handles deadlocks with exponential backoff.
     *
     * @param string $table The table name
     * @param bool $hasCstm Whether the table has a corresponding _cstm table
     * @param int $batchSize Number of records to delete per batch
     * @param string $thresholdTime Formatted timestamp threshold for deletion
     * @param int $maxDuration Maximum execution time in seconds
     * @param float $startTime Job start time from microtime(true)
     * @param array $deadlockRetryConfig Deadlock retry configuration ['attempts' => int, 'delays' => array]
     * @param bool $hasAudit Whether the table has a corresponding audit table
     * @return array Result with keys: 'success' => bool, 'error' => string|null, 'timeout' => bool
     */
    private function batchDeleteFromTable(
        string $table,
        bool $hasCstm,
        int $batchSize,
        string $thresholdTime,
        int $maxDuration,
        float $startTime,
        array $deadlockRetryConfig,
        bool $hasAudit
    ): array {
        $db = DBManagerFactory::getInstance();
        $conn = $db->getConnection();

        $deadlockAttempts = (int) ($deadlockRetryConfig['attempts'] ?? self::DEFAULT_DEADLOCK_RETRY_ATTEMPTS);
        $effectiveAttempts = max(1, $deadlockAttempts);

        $deadlockDelays = $deadlockRetryConfig['delays'] ?? self::DEFAULT_DEADLOCK_RETRY_DELAYS;

        $totalDeleted = 0;
        $totalCstmDeleted = 0;
        $totalAuditBeansDeleted = 0;
        $totalAuditEventsDeleted = 0;
        $tablesWithUploads = ['document_revisions', 'notes'];
        $uploads = [];

        try {
            while (true) {
                // Attempt delete with deadlock retry
                $deleteSuccess = false;
                for ($attempt = 0; $attempt < $effectiveAttempts; $attempt++) {
                    try {
                        // Always use explicit transaction for atomic operations
                        $conn->beginTransaction();

                        // Fetch batch of IDs to delete INSIDE transaction
                        // This prevents race conditions with parallel prune jobs
                        $ids = $conn->createQueryBuilder()
                            ->select('id')
                            ->from($table)
                            ->where('deleted = :deleted')
                            ->andWhere('date_modified < :threshold')
                            ->setParameter('deleted', 1)
                            ->setParameter('threshold', $thresholdTime)
                            ->setMaxResults($batchSize)
                            ->executeQuery()
                            ->fetchFirstColumn();

                        $batchCount = safeCount($ids);
                        if ($batchCount === 0) {
                            // No more records to delete
                            $conn->commit();
                            if ($totalDeleted > 0) {
                                $GLOBALS['log']->info(
                                    "PruneDatabase: Deleted {$totalDeleted} records from table '{$table}'"
                                );
                            }
                            break 2; // Exit both retry and main loop
                        }

                        $sampleIds = implode(', ', array_slice($ids, 0, 3));
                        $GLOBALS['log']->debug(
                            "PruneDatabase: Found {$batchCount} records to delete from table '{$table}' " .
                            "(threshold: {$thresholdTime}, sample IDs: {$sampleIds})"
                        );

                        $placeholders = implode(',', array_fill(0, count($ids), '?'));

                        $cstmAffected = 0;
                        if ($hasCstm) {
                            // Delete from custom table first
                            $cstmAffected = $conn->executeStatement(
                                "DELETE FROM {$table}_cstm WHERE id_c IN ($placeholders)",
                                $ids
                            );
                        }

                        $auditBeansAffected = 0;
                        $auditEventsAffected = 0;
                        if ($hasAudit) {
                            // Delete from audit table
                            $auditBeansAffected = $conn->executeStatement(
                                "DELETE FROM {$table}_audit WHERE parent_id IN ($placeholders)",
                                $ids
                            );
                            // Delete from audit_events table
                            $moduleName = $this->getModuleName($table);
                            if ($moduleName) {
                                $auditEventsAffected = $conn->executeStatement(
                                    "DELETE FROM audit_events WHERE parent_id IN ($placeholders) AND module_name = ?",
                                    [...$ids, $moduleName]
                                );
                            } else {
                                $GLOBALS['log']->error("PruneDatabase: Undefined module name for table {$table}");
                            }
                        }

                        //Finds the uploaded files for deletion
                        if (in_array($table, $tablesWithUploads)) {
                            if ($table === 'document_revisions') {
                                $uploads = $conn->createQueryBuilder()
                                    ->select('id')
                                    ->from($table)
                                    ->where('deleted = :deleted')
                                    ->andWhere('date_modified < :threshold')
                                    ->setParameter('deleted', 1)
                                    ->setParameter('threshold', $thresholdTime)
                                    ->executeQuery()
                                    ->fetchFirstColumn();
                            }

                            if ($table === 'notes') {
                                $uploads = $conn->createQueryBuilder()
                                    ->select('upload_id')
                                    ->from($table)
                                    ->where('deleted = :deleted')
                                    ->andWhere('date_modified < :threshold')
                                    ->setParameter('deleted', 1)
                                    ->setParameter('threshold', $thresholdTime)
                                    ->executeQuery()
                                    ->fetchFirstColumn();
                            }
                        }

                        // Delete from main table - include deleted=1 check for safety
                        $params = array_merge($ids, [1, $thresholdTime]);
                        $mainAffected = $conn->executeStatement(
                            "DELETE FROM {$table} WHERE id IN ($placeholders) AND deleted = ? AND date_modified < ?",
                            $params
                        );

                        //Deletes a file from the file system unless the file is being shared by multiple records
                        if (!empty($uploads)) {
                            foreach ($uploads as $file) {
                                \UploadFile::unlink_file($file);
                            }

                            $uploads = [];
                        }

                        $conn->commit();
                        $deleteSuccess = true;
                        $totalDeleted += $mainAffected; // Use actual affected rows, not batch count
                        $totalCstmDeleted += $cstmAffected;
                        $totalAuditBeansDeleted += $auditBeansAffected;
                        $totalAuditEventsDeleted += $auditEventsAffected;

                        $auditLog = "audit: (bean: {$auditBeansAffected}, event: {$auditEventsAffected})";
                        $GLOBALS['log']->debug(
                            "PruneDatabase: Deleted batch from '{$table}' - " .
                            "main: {$mainAffected}, cstm: {$cstmAffected}, $auditLog, expected: {$batchCount}"
                        );

                        if ($mainAffected === 0) {
                            $GLOBALS['log']->warning(
                                "PruneDatabase: DELETE returned 0 affected rows for '{$table}' " .
                                "but SELECT found {$batchCount} records. Sample IDs: {$sampleIds}. " .
                                "This may indicate parallel deletion by another process or isolation level issue."
                            );
                            // Break from both retry loop and main while loop to avoid infinite loop
                            break 2;
                        }

                        break; // Success, exit retry loop
                    } catch (Throwable $e) {
                        // Rollback on any error
                        if ($conn->isTransactionActive()) {
                            $conn->rollback();
                        }

                        // Check if it's a deadlock error
                        if ($this->isDeadlockException($e)) {
                            $GLOBALS['log']->warning(
                                "pruneDatabase: Deadlock detected on table '{$table}', " .
                                "attempt " . ($attempt + 1) . " of {$effectiveAttempts}"
                            );

                            // Exponential backoff before retry
                            if ($attempt < $effectiveAttempts - 1) {
                                $delayMs = $deadlockDelays[$attempt] ?? end($deadlockDelays);
                                usleep($delayMs * 1000);
                            }
                        } else {
                            // Non-deadlock error, don't retry
                            return [
                                'success' => false,
                                'error' => $e->getMessage(),
                                'timeout' => false,
                            ];
                        }
                    }
                }

                if (!$deleteSuccess) {
                    return [
                        'success' => false,
                        'error' => "Deadlock after {$effectiveAttempts} attempts",
                        'timeout' => false,
                    ];
                }

                // Check time limit after each batch
                if ((microtime(true) - $startTime) > $maxDuration) {
                    return [
                        'success' => true,
                        'error' => null,
                        'timeout' => true,
                    ];
                }
            }

            // All records deleted successfully, optimize table
            if ($totalDeleted > 0) {
                $db->optimizeTable($table);
            }
            if ($totalCstmDeleted > 0) {
                $db->optimizeTable("{$table}_cstm");
            }
            if ($totalAuditBeansDeleted > 0) {
                $db->optimizeTable("{$table}_audit");
            }
            if ($totalAuditEventsDeleted > 0) {
                $db->optimizeTable("audit_events");
            }

            return [
                'success' => true,
                'error' => null,
                'timeout' => false,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timeout' => false,
            ];
        }
    }

    /**
     * Checks if an exception is a deadlock error.
     *
     * @param Throwable $e The exception to check
     * @return bool True if deadlock, false otherwise
     */
    private function isDeadlockException(Throwable $e): bool
    {
        $message = $e->getMessage();

        // MySQL deadlock patterns
        if (stripos($message, 'deadlock') !== false) {
            return true;
        }

        // MySQL error code 1213
        if (stripos($message, '1213') !== false) {
            return true;
        }

        // MSSQL deadlock patterns
        if (stripos($message, 'deadlock victim') !== false) {
            return true;
        }

        // Oracle deadlock
        if (stripos($message, 'ORA-00060') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Get bean module name for a given table name (cached).
     */
    private function getModuleName(string $tableName): ?string
    {
        static $cache = [];

        if (!$cache) {
            foreach ($GLOBALS['beanList'] as $module => $beanName) {
                $beanName = $GLOBALS['objectList'][$module] ?? $beanName;

                if (empty($GLOBALS['dictionary'][$beanName])) {
                    \VardefManager::loadVardef($module, $beanName);
                }

                $table = $GLOBALS['dictionary'][$beanName]['table'] ?? null;
                if ($table) {
                    $cache[$table] = $module;
                }
            }
        }

        return $cache[$tableName] ?? null;
    }
}
