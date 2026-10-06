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
/*********************************************************************************
 * Description:  Defines the English language pack for the base application.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/

/**
 * Default SugarCRM Logger
 * @api
 */
class SugarLogger implements LoggerTemplate
{
    /**
     * properties for the SugarLogger
     */
    protected $logfile = 'sugarcrm';
    protected $ext = '.log';
    protected $dateFormat = '%c';
    protected $logSize = '10MB';
    protected $maxLogs = 10;
    protected $filesuffix = '';
    protected $date_suffix = '';
    protected $log_dir = '.';


    /**
     * used for config screen
     */
    public static $filename_suffix = [
        //bug#50265: Added none option for previous version users
        '' => 'None',
        '%m_%Y' => 'Month_Year',
        '%d_%m' => 'Day_Month',
        '%m_%d_%y' => 'Month_Day_Year',
    ];

    /**
     * Let's us know if we've initialized the logger file
     */
    protected $initialized = false;

    /**
     * Logger file handle
     */
    protected $fp = false;
    /**
     * @var string
     */
    protected $full_log_file;

    public function __get(
        $key
    ) {


        return $this->$key;
    }

    /**
     * Used by the diagnostic tools to get SugarLogger log file information
     */
    public function getLogFileNameWithPath()
    {
        return $this->full_log_file;
    }

    /**
     * Used by the diagnostic tools to get SugarLogger log file information
     */
    public function getLogFileName()
    {
        return ltrim($this->full_log_file, './');
    }

    /**
     * Constructor
     *
     * Reads the config file for logger settings
     */
    public function __construct()
    {
        $config = SugarConfig::getInstance();

        $logfileConfig = trim($config->get('logger.file.name', $this->logfile));
        if (strcmp($logfileConfig, '') != 0) {
            $this->logfile = $logfileConfig;
        }

        $this->dateFormat = trim($config->get('logger.file.dateFormat', $this->dateFormat));

        $logSizeConfig = trim($config->get('logger.file.maxSize', $this->logSize));
        if (strcmp($logSizeConfig, '') != 0) {
            $this->logSize = $logSizeConfig;
        }

        $maxLogsConfig = trim($config->get('logger.file.maxLogs', $this->maxLogs));
        if ($maxLogsConfig > 0) {
            $this->maxLogs = $maxLogsConfig;
        }

        $this->filesuffix = trim($config->get('logger.file.suffix', $this->filesuffix));
        $log_dir = trim($config->get('log_dir', $this->log_dir));
        $this->log_dir = $log_dir . (empty($log_dir) ? '' : '/');
        unset($config);
        $this->_doInitialization();
        LoggerManager::setLogger('default', 'SugarLogger');
    }

    /**
     * Handles the SugarLogger initialization
     */
    protected function _doInitialization()
    {

        if ($this->filesuffix && array_key_exists($this->filesuffix, self::$filename_suffix)) { //if the global config contains date-format suffix, it will create suffix by parsing datetime
            $this->date_suffix = '_' . date(str_replace('%', '', $this->filesuffix));
        }
        $this->full_log_file = $this->log_dir . $this->logfile . $this->date_suffix . $this->ext;
        $this->initialized = $this->_fileCanBeCreatedAndWrittenTo();
        $this->rollLog();
    }

    /**
     * Checks to see if the SugarLogger file can be created and written to
     */
    protected function _fileCanBeCreatedAndWrittenTo()
    {
        $this->_attemptToCreateIfNecessary();
        return file_exists($this->full_log_file) && is_writable($this->full_log_file);
    }

    /**
     * Creates the SugarLogger file if it doesn't exist
     */
    protected function _attemptToCreateIfNecessary()
    {
        if (file_exists($this->full_log_file)) {
            return;
        }
        @touch($this->full_log_file);
    }

    /**
     * see LoggerTemplate::log()
     */
    public function log(
        $level,
        $message
    ) {


        if (!$this->initialized) {
            return;
        }
        //lets get the current user id or default to -none- if it is not set yet
        $userID = (!empty($GLOBALS['current_user']->id)) ? $GLOBALS['current_user']->id : '-none-';

        //if we haven't opened a file pointer yet let's do that
        if (!$this->fp) {
            $this->fp = fopen($this->full_log_file, 'a');
        }


        // change to a string if there is just one entry
        if (is_array($message) && safeCount($message) == 1) {
            $message = array_shift($message);
        }
        // change to a human-readable array output if it's any other array
        if (is_array($message)) {
            $message = print_r($message, true);
        }

        // mute deprecation
        $time = @strftime($this->dateFormat);
        //write out to the file including the time in the dateFormat the process id , the user id , and the log level as well as the message
        $this->write($time . ' [' . getmypid() . '][' . $userID . '][' . strtoupper($level) . '] ' . $message . "\n");
    }

    /**
     * Writing log to file
     *
     * @param string $string Message to log
     */
    protected function write($string)
    {
        if ($this->fp) {
            fwrite($this->fp, $string);
        }
    }

    /**
     * rolls the logger file to start using a new file
     */
    protected function rollLog(
        $force = false
    ) {


        if (!$this->initialized || empty($this->logSize)) {
            return;
        }
        // bug#50265: Parse the its unit string and get the size properly
        $units = [
            'b' => 1,                   //Bytes
            'k' => 1024,                //KBytes
            'm' => 1024 * 1024,         //MBytes
            'g' => 1024 * 1024 * 1024,  //GBytes
        ];
        if (preg_match('/^\s*([0-9]+\.[0-9]+|\.?[0-9]+)\s*(k|m|g|b)(b?ytes)?/i', $this->logSize, $match)) {
            $rollAt = ( int )$match[1] * $units[strtolower($match[2])];
        }
        //check if our log file is greater than that or if we are forcing the log to roll if and only if roll size assigned the value correctly
        if ($force || (!empty($rollAt) && filesize($this->full_log_file) >= $rollAt)) {
            $this->performRotation($rollAt);
        }
    }

    /**
     * Performs the actual log rotation
     * Separated for better testability and clarity
     *
     * @param int $rollAt Size threshold for rotation
     */
    protected function performRotation(int $rollAt): void
    {
        // Close the current file handle before rotation to prevent writing to renamed files
        $this->closeFileHandle();

        // Move current log to temp file to preserve content
        // Use realpath() on directory (not file) so it works even when file doesn't exist yet
        $logDir = realpath(dirname($this->full_log_file)) ?: dirname($this->full_log_file);
        $logDir = sugar_get_writable_shadow_path($logDir);

        $tempFile = tempnam(
            $logDir,
            'sugarlog_'
        );

        try {
            if ($this->moveLogToTemp($tempFile)) {
                $this->processRotation(
                    $tempFile,
                    $rollAt
                );
            }
        } finally {
            // Always reopen file handle regardless of rotation success/failure
            $this->openFileHandle();
        }
    }

    /**
     * Close the current file handle
     */
    protected function closeFileHandle(): void
    {
        if ($this->fp) {
            fflush($this->fp);  // Flush any pending writes
            fclose($this->fp);
            $this->fp = false;
        }
    }

    /**
     * Open file handle for writing
     */
    protected function openFileHandle(): void
    {
        $this->fp = fopen(
            $this->full_log_file,
            'a'
        );
    }

    /**
     * Move log file to temporary location
     *
     * @param string $tempFile Temp file path
     * @return bool Success
     */
    protected function moveLogToTemp(string $tempFile): bool
    {
        return sugar_rename(
            $this->full_log_file,
            $tempFile
        );
    }

    /**
     * Process the rotation of log files
     *
     * @param string $tempFile Temporary file containing log content
     * @param int $rollAt Size threshold
     */
    protected function processRotation(string $tempFile, int $rollAt): void
    {
        // Copy to second temp file to measure size consistently
        $realLogDir = dirname($tempFile);
        $realLogDir = sugar_get_writable_shadow_path($realLogDir);

        $tempCopy = tempnam(
            $realLogDir,
            'sugarlog_copy_'
        );
        copy(
            $tempFile,
            $tempCopy
        );

        // Check size of copied file to verify it's worth rotating
        if (filesize($tempCopy) >= $rollAt) {
            $this->rotateNumberedLogs();

            // Move temp file directly to _1 (no data loss)
            $rotated_name = $this->log_dir . $this->logfile . $this->date_suffix . '_1' . $this->ext;
            sugar_rename(
                $tempFile,
                $rotated_name
            );

            // Remove copy temp file (original temp file was renamed)
            @unlink($tempCopy);
        } else {
            // Another process already rotated, discard temp files
            @unlink($tempFile);
            @unlink($tempCopy);
        }
    }

    /**
     * Rotate existing numbered log files
     */
    protected function rotateNumberedLogs(): void
    {
        for ($i = $this->maxLogs - 2; $i > 0; $i--) {
            $old_name = $this->log_dir . $this->logfile . $this->date_suffix . '_' . $i . $this->ext;
            if (file_exists($old_name)) {
                $to = $i + 1;
                $new_name = $this->log_dir . $this->logfile . $this->date_suffix . '_' . $to . $this->ext;
                sugar_rename(
                    $old_name,
                    $new_name
                );
            }

            $baseDir = $this->log_dir;
            if (defined('SHADOW_INSTANCE_DIR')) {
                $baseDir = SHADOW_INSTANCE_DIR . str_replace('./', '/', $this->log_dir);
            }

            $allFilesPattern = $baseDir . $this->logfile . '{' . $this->ext . ',_*' . $this->ext . '}';
            $allFiles = glob($allFilesPattern, GLOB_BRACE);

            if ($allFiles === false) {
                return;
            }

            $rolledFiles = $this->getRolledFiles($allFiles);

            if (count($rolledFiles) > $this->maxLogs - 1) {
                $this->removeExcessLogFiles($rolledFiles);
            }
        }
    }

    /**
     * Get only the rolled log files from all files
     */
    protected function getRolledFiles($allFiles): array
    {
        return array_filter($allFiles, function ($file) {
            return preg_match(
                '/^' . preg_quote($this->logfile, '/') .
                '(?:_\d+){0,4}_\d+' . preg_quote($this->ext, '/') . '$|^' .
                preg_quote($this->logfile, '/') . preg_quote($this->ext, '/') . '$/',
                basename($file)
            );
        });
    }

    /**
     * Remove extra log files if the maxLogs setting is reduced or changed date sufix
     */
    protected function removeExcessLogFiles($files): void
    {
        $timestamps = [];
        $formats = [];

        foreach ($files as $file) {
            $base = basename($file);

            if (preg_match('/^' . preg_quote($this->logfile, '/') . '_(\d+)'
                    . preg_quote($this->ext, '/') . '$/', $base, $m)) {
                $formats[] = 'index';
                $timestamps[$file] = isset($m[1]) ? (int)$m[1] : 0;
            } elseif (preg_match('/^' . preg_quote($this->logfile, '/') . '_(\d{1,2})_(\d{4})(?:_(\d+))?'
                    . preg_quote($this->ext, '/') . '$/', $base, $m)) {
                $formats[] = 'month_year';
                $month = $m[1];
                $year = $m[2];
                $index = isset($m[3]) ? (int)$m[3] : 0;
                $timestamps[$file] = strtotime($year . '-' . $month . '-01') + $index;
            } elseif (preg_match('/^' . preg_quote($this->logfile, '/')
                    . '_(\d{1,2})_(\d{1,2})(?:_(\d+))?'
                        . preg_quote($this->ext, '/') . '$/', $base, $m)) {
                $formats[] = 'day_month';
                $day = $m[1];
                $month = $m[2];
                $year = date('Y');
                $index = isset($m[3]) ? (int)$m[3] : 0;
                $timestamps[$file] = strtotime($year . '-' . $month . '-' . $day) + $index;
            } elseif (preg_match('/^' . preg_quote($this->logfile, '/')
                    . '_(\d{1,2})_(\d{1,2})_(\d{2,4})(?:_(\d+))?'
                        . preg_quote($this->ext, '/') . '$/', $base, $m)) {
                $formats[] = 'day_month_year';
                $day = $m[1];
                $month = $m[2];
                $year = $m[3];
                $index = isset($m[4]) ? (int)$m[4] : 0;
                $timestamps[$file] = strtotime($year . '-' . $month . '-' . $day) + $index;
            } else {
                $formats[] = 'unknown';
                $timestamps[$file] = filemtime($file);
            }
        }

        $isAllSameFormat = (count(array_unique($formats)) === 1);

        usort($files, function ($a, $b) use ($timestamps, $isAllSameFormat) {
            if ($isAllSameFormat) {
                if ($timestamps[$a] == $timestamps[$b]) {
                    return 0;
                }

                return ($timestamps[$a] > $timestamps[$b]) ? -1 : 1;
            } else {
                $mtimeA = filemtime($a);
                $mtimeB = filemtime($b);
                if ($mtimeA == $mtimeB) {
                    return 0;
                }

                return ($mtimeA < $mtimeB) ? -1 : 1;
            }
        });

        $totalFiles = count($files) + 1;
        if ($totalFiles > $this->maxLogs) {
            $excess = $totalFiles - $this->maxLogs;

            $oldestFiles = array_slice($files, 0, $excess, true);
            foreach ($oldestFiles as $oldFile) {
                unlink(getAbsolutePath($oldFile));
            }
        }
    }

    /**
     * This is needed to prevent unserialize vulnerability
     */
    public function __wakeup()
    {
        // clean all properties
        foreach (get_object_vars($this) as $k => $v) {
            $this->$k = null;
        }
        throw new Exception('Not a serializable object');
    }

    /**
     * Destructor
     *
     * Closes the SugarLogger file handle
     */
    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
            $this->fp = false;
        }
    }
}
