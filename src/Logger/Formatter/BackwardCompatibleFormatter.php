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

namespace Sugarcrm\Sugarcrm\Logger\Formatter;

use Monolog\Logger;
use Monolog\LogRecord;
use Sugarcrm\Sugarcrm\Logger\Formatter;

/**
 * Formatter for backward compatibility with legacy error levels
 *
 * In case if the message contains legacy level name, it restores the original message
 * and replaces the PSR level with the legacy one
 */
class BackwardCompatibleFormatter extends Formatter
{
    /**
     * {@inheritdoc}
     *
     * @see SugarPsrLogger::log()
     */
    public function format(array|LogRecord $record): string
    {
        $message = is_array($record) ? $record['message'] ?? '' : $record->message;
        $levelName = is_array($record) ? $record['level_name'] ?? 'info' : $record->level->getName();

        // Process the message to extract level name if present in a specific format
        $message = preg_replace_callback('/^\[LEVEL:([^\]]+)\] (.*)/', function ($matches) use (&$levelName) {
            $levelName = $matches[1];
            return $matches[2];
        }, $message);

        if (is_array($record)) {
            $newRecord = new LogRecord(
                datetime: isset($record['datetime']) ? new \DateTimeImmutable($record['datetime']) : new \DateTimeImmutable(),
                channel: $record['channel'] ?? 'default',
                level: Logger::toMonologLevel($levelName),
                message: $message,
                context: $record['context'] ?? [],
                extra: $record['extra'] ?? []
            );
        } else {
            $newRecord = new LogRecord(
                datetime: $record->datetime,
                channel: $record->channel,
                level: Logger::toMonologLevel($levelName),
                message: $message,
                context: $record->context,
                extra: $record->extra
            );
        }

        return parent::format($newRecord);
    }
}
