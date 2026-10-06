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

namespace Sugarcrm\Sugarcrm\SystemProcessLock;

use Sugarcrm\Sugarcrm\AccessControl\AccessControlManager;

/**
 * Process lock/unlock implementation
 *
 * @package Sugarcrm\Sugarcrm\ProcessLock
 */
class SystemProcessLock
{
    private $uniqueId;
    private $additionalKey;
    private $db;
    private $iterationWaitMicroseconds;
    private $iterationsBeforeFault;
    private $lockAttemptCounter = 0;
    private $lockTimeoutSeconds = 0;
    private $locked = false;
    private static $lockLevel = 0;
    private const DEFAULT_ITERATION_WAIT_MICROSECONDS = 5000000;
    private const DEFAULT_ITERATION_BEFORE_FAULT = 36;
    private const DEFAULT_LOCK_TIMEOUT_SECONDS = 300;

    public function __construct(string $uniqueId, string $additionalKey = '', array $options = [])
    {
        $this->uniqueId = $uniqueId;
        $this->additionalKey = $additionalKey;
        // Oracle workaround
        if ($this->additionalKey == '') {
            $this->additionalKey = '#';
        }
        $this->db = new DbImplementation();
        $this->setOptions($options);

        if (defined('SUGAR_PHPUNIT_RUNNER')) {
            $this->resetInstance();
        }
    }

    /**
     * Prevent process race conditions for cache building functionality
     *
     * @param callable $checkCondition Check cache state - is rebuild necessary
     * @param callable $longRunningFunction Do rebuild and return a result
     * @param callable $onRefused Unable to acquire a lock but rebuild is necessary
     * @return void|null|mixed
     */
    public function isolatedCall(
        callable  $checkCondition,
        callable  $longRunningFunction,
        ?callable $onRefused = null
    ) {

        if (self::$lockLevel > 0 || !$this->db->isAvailable) {
            return $longRunningFunction($this->lockAttemptCounter);
        }
        $returnValue = null;
        while (true) {
            // try to acquire lock
            if ($this->lock()) {
                // locked, ensure that rebuild still necessary, if not - unlock and stop trying to rebuild
                if (!$checkCondition()) {
                    $this->unlock();
                } else {
                    self::$lockLevel++;
                    $returnValue = $longRunningFunction($this->lockAttemptCounter);
                    self::$lockLevel--;
                    $this->unlock();
                }
                return $returnValue;
            }

            $this->wait();
            // check again: if rebuild is not necessary stop trying to do this
            if (!$checkCondition($this->lockAttemptCounter)) {
                return;
            }
            $this->db->processTimedOutLocks();

            if ($this->isAttemptLimitReached()) {
                break;
            }
        }

        // was not locked and rebuild still necessary
        if ($onRefused !== null) {
            $returnValue = $onRefused($this->lockAttemptCounter);
        }

        return $returnValue;
    }

    public function lock(): bool
    {
        $this->locked = true;
        $this->lockAttemptCounter++;
        $this->locked = $this->db->lock($this->uniqueId, $this->additionalKey, $this->lockTimeoutSeconds);
        return $this->locked;
    }

    public function unlock(): void
    {
        $this->locked = false;
        $this->lockAttemptCounter = 0;
        $this->db->unlock($this->uniqueId, $this->additionalKey);
    }

    public function wait(): void
    {
        // Disconnect from database to free connection resources while waiting
        $this->db->disconnect();

        usleep($this->iterationWaitMicroseconds);

        // Reconnect to database for next lock attempt
        $this->db->reconnect();
    }

    public function isAttemptLimitReached(): bool
    {
        return $this->lockAttemptCounter >= $this->iterationsBeforeFault;
    }

    public function resetAttemptCounter(): void
    {
        $this->lockAttemptCounter = 0;
    }

    public function resetInstance(): void
    {
        self::$lockLevel = 0;
    }

    private function setOptions(array $options = []): void
    {
        $this->iterationWaitMicroseconds = $options['iteration_wait_microseconds']
            ?? self::DEFAULT_ITERATION_WAIT_MICROSECONDS;
        $this->iterationsBeforeFault = $options['iterations_before_fault']
            ?? self::DEFAULT_ITERATION_BEFORE_FAULT;
        $this->lockTimeoutSeconds = $options['lock_timeout_seconds']
            ?? self::DEFAULT_LOCK_TIMEOUT_SECONDS;

        assert($this->iterationWaitMicroseconds >= 0);
        assert($this->iterationsBeforeFault > 0);
        assert($this->lockTimeoutSeconds > 0);
    }

    /**
     * Creates a refuse function that only allows admin users to execute the operation
     * when they couldn't acquire the lock immediately. Regular users will get the default return value.
     *
     * @param callable $operation The operation to execute for admin users
     * @param mixed $defaultReturn The default return value for regular users (null by default)
     * @return callable The refuse function
     */
    public static function createAdminOnlyRefuseFunction(callable $operation, $defaultReturn = null): callable
    {
        return function (int $attempt) use ($operation, $defaultReturn) {
            // Only allow admin users to execute expensive operations when they couldn't acquire the lock immediately
            // Regular users should assume the admin process completed successfully
            if ($GLOBALS['current_user']->isAdmin() || AccessControlManager::instance()->getAdminWork()) {
                return $operation($attempt);
            }
            // For regular users, return the default value
            return $defaultReturn;
        };
    }
}
