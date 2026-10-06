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

use Sugarcrm\Sugarcrm\InboundEmail\Polyfill\ImapPolyfillManager;
use Sugarcrm\Sugarcrm\InboundEmail\Polyfill\ImapConnection;
use Sugarcrm\Sugarcrm\InboundEmail\Polyfill\ImapResource;
use Sugarcrm\Sugarcrm\InboundEmail\Polyfill\MimeStructureBuilder;
use Sugarcrm\Sugarcrm\InboundEmail\Polyfill\MimePartResolver;
use Sugarcrm\Sugarcrm\InboundEmail\Polyfill\SearchCriteriaTokenizer;
use Sugarcrm\Sugarcrm\InboundEmail\Polyfill\MimeEncoder;

// Only define polyfill functions if imap_open doesn't exist
// This check covers both: native extension not loaded AND polyfill not already loaded
if (!function_exists('imap_open')) {
    // Define constants with individual checks to prevent conflicts
    if (!defined('SE_UID')) {
        define('SE_UID', 1);
    }
    if (!defined('SE_FREE')) {
        define('SE_FREE', 2);
    }
    if (!defined('SE_NOPREFETCH')) {
        define('SE_NOPREFETCH', 4);
    }
    if (!defined('SO_FREE')) {
        define('SO_FREE', 8);
    }
    if (!defined('SO_NOSERVER')) {
        define('SO_NOSERVER', 8);
    }
    if (!defined('FT_UID')) {
        define('FT_UID', 1);
    }
    if (!defined('FT_PEEK')) {
        define('FT_PEEK', 2);
    }
    if (!defined('FT_NOT')) {
        define('FT_NOT', 4);
    }
    if (!defined('FT_INTERNAL')) {
        define('FT_INTERNAL', 8);
    }
    if (!defined('FT_PREFETCHTEXT')) {
        define('FT_PREFETCHTEXT', 32);
    }
    if (!defined('NIL')) {
        define('NIL', 0);
    }
    if (!defined('OP_DEBUG')) {
        define('OP_DEBUG', 1);
    }
    if (!defined('OP_READONLY')) {
        define('OP_READONLY', 2);
    }
    if (!defined('OP_ANONYMOUS')) {
        define('OP_ANONYMOUS', 4);
    }
    if (!defined('OP_SHORTCACHE')) {
        define('OP_SHORTCACHE', 8);
    }
    if (!defined('OP_SILENT')) {
        define('OP_SILENT', 16);
    }
    if (!defined('OP_PROTOTYPE')) {
        define('OP_PROTOTYPE', 32);
    }
    if (!defined('OP_HALFOPEN')) {
        define('OP_HALFOPEN', 64);
    }
    if (!defined('OP_EXPUNGE')) {
        define('OP_EXPUNGE', 128);
    }
    if (!defined('OP_SECURE')) {
        define('OP_SECURE', 256);
    }
    if (!defined('CL_EXPUNGE')) {
        define('CL_EXPUNGE', 32768);
    }
    if (!defined('ST_UID')) {
        define('ST_UID', 1);
    }
    if (!defined('ST_SILENT')) {
        define('ST_SILENT', 2);
    }
    if (!defined('ST_SET')) {
        define('ST_SET', 4);
    }
    if (!defined('CP_UID')) {
        define('CP_UID', 1);
    }
    if (!defined('CP_MOVE')) {
        define('CP_MOVE', 2);
    }
    if (!defined('SA_MESSAGES')) {
        define('SA_MESSAGES', 1);
    }
    if (!defined('SA_RECENT')) {
        define('SA_RECENT', 2);
    }
    if (!defined('SA_UNSEEN')) {
        define('SA_UNSEEN', 4);
    }
    if (!defined('SA_UIDNEXT')) {
        define('SA_UIDNEXT', 8);
    }
    if (!defined('SA_UIDVALIDITY')) {
        define('SA_UIDVALIDITY', 16);
    }
    if (!defined('SA_ALL')) {
        define('SA_ALL', 31);
    }
    if (!defined('SORTDATE')) {
        define('SORTDATE', 0);
    }
    if (!defined('SORTARRIVAL')) {
        define('SORTARRIVAL', 1);
    }
    if (!defined('SORTFROM')) {
        define('SORTFROM', 2);
    }
    if (!defined('SORTSUBJECT')) {
        define('SORTSUBJECT', 3);
    }
    if (!defined('SORTTO')) {
        define('SORTTO', 4);
    }
    if (!defined('SORTCC')) {
        define('SORTCC', 5);
    }
    if (!defined('SORTSIZE')) {
        define('SORTSIZE', 6);
    }
    if (!defined('TYPETEXT')) {
        define('TYPETEXT', 0);
    }
    if (!defined('TYPEMULTIPART')) {
        define('TYPEMULTIPART', 1);
    }
    if (!defined('TYPEMESSAGE')) {
        define('TYPEMESSAGE', 2);
    }
    if (!defined('TYPEAPPLICATION')) {
        define('TYPEAPPLICATION', 3);
    }
    if (!defined('TYPEAUDIO')) {
        define('TYPEAUDIO', 4);
    }
    if (!defined('TYPEIMAGE')) {
        define('TYPEIMAGE', 5);
    }
    if (!defined('TYPEVIDEO')) {
        define('TYPEVIDEO', 6);
    }
    if (!defined('TYPEMODEL')) {
        define('TYPEMODEL', 7);
    }
    if (!defined('TYPEOTHER')) {
        define('TYPEOTHER', 8);
    }
    if (!defined('ENC7BIT')) {
        define('ENC7BIT', 0);
    }
    if (!defined('ENC8BIT')) {
        define('ENC8BIT', 1);
    }
    if (!defined('ENCBINARY')) {
        define('ENCBINARY', 2);
    }
    if (!defined('ENCBASE64')) {
        define('ENCBASE64', 3);
    }
    if (!defined('ENCQUOTEDPRINTABLE')) {
        define('ENCQUOTEDPRINTABLE', 4);
    }
    if (!defined('ENCOTHER')) {
        define('ENCOTHER', 5);
    }
    if (!defined('LATT_NOINFERIORS')) {
        define('LATT_NOINFERIORS', 1);
    }
    if (!defined('LATT_NOSELECT')) {
        define('LATT_NOSELECT', 2);
    }
    if (!defined('LATT_MARKED')) {
        define('LATT_MARKED', 4);
    }
    if (!defined('LATT_UNMARKED')) {
        define('LATT_UNMARKED', 8);
    }
    if (!defined('LATT_REFERRAL')) {
        define('LATT_REFERRAL', 16);
    }
    if (!defined('LATT_HASCHILDREN')) {
        define('LATT_HASCHILDREN', 32);
    }
    if (!defined('LATT_HASNOCHILDREN')) {
        define('LATT_HASNOCHILDREN', 64);
    }

    // Timeout constants (for imap_timeout function)
    if (!defined('IMAP_OPENTIMEOUT')) {
        define('IMAP_OPENTIMEOUT', 1);
    }
    if (!defined('IMAP_READTIMEOUT')) {
        define('IMAP_READTIMEOUT', 2);
    }
    if (!defined('IMAP_WRITETIMEOUT')) {
        define('IMAP_WRITETIMEOUT', 3);
    }
    if (!defined('IMAP_CLOSETIMEOUT')) {
        define('IMAP_CLOSETIMEOUT', 4);
    }
    if (!defined('IMAP_GC_ELT')) {
        define('IMAP_GC_ELT', 1);
    }
    if (!defined('IMAP_GC_ENV')) {
        define('IMAP_GC_ENV', 2);
    }
    if (!defined('IMAP_GC_TEXTS')) {
        define('IMAP_GC_TEXTS', 4);
    }

    function imap_open(
        string $mailbox,
        string $username,
        string $password,
        int $options = 0,
        int $retries = 0,
        array $params = []
    ): ImapResource|false {
        try {
            $manager = ImapPolyfillManager::getInstance();

            $connectionOptions = [
                'options' => $options,
                'retries' => $retries,
                'params' => $params,
            ];

            $connection = new ImapConnection($mailbox, $username, $password, $connectionOptions);
            return $manager->registerConnection($connection);
        } catch (\Exception $e) {
            ImapPolyfillManager::getInstance()->addError($e->getMessage());
            return false;
        }
    }

    function imap_close(ImapResource $imap, int $flags = 0): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        if ($flags & CL_EXPUNGE) {
            imap_expunge($imap);
        }

        $manager->unregisterConnection($imap->getId());
        return true;
    }

    function imap_headers(ImapResource $imap): array|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Use folder->overview() for performance - doesn't fetch bodies
            // Note: overview() is keyed by UID, but the order represents message sequence
            $overview = $folder->overview("1:*");
            $headers = [];

            // The array order represents message sequence, so we can use array index + 1 as msgno
            $msgno = 1;
            foreach ($overview as $uid => $data) {
                $from = $data['fromaddress'] ?? 'unknown';
                $subject = $data['subject'] ?? '(no subject)';
                $date = $data['date'] ?? null;

                // Format date
                $dateStr = 'unknown-date';
                if ($date) {
                    try {
                        if ($date instanceof Carbon) {
                            $dateStr = $date->format('d-M-Y');
                        } else {
                            $dateStr = Carbon::parse((string)$date)->format('d-M-Y');
                        }
                    } catch (\Throwable $e) {
                        $dateStr = 'unknown-date';
                        if (isset($GLOBALS['log'])) {
                            $dateContext = 'n/a';
                            if ($date !== null) {
                                if (is_scalar($date)) {
                                    $dateContext = (string)$date;
                                } elseif ($date instanceof \DateTimeInterface) {
                                    $dateContext = $date->format(DATE_ATOM);
                                } elseif (is_object($date)) {
                                    $dateContext = 'object:' . get_class($date);
                                } else {
                                    $dateContext = gettype($date);
                                }
                            }
                            $GLOBALS['log']->warning(
                                sprintf(
                                    'imap_headers date formatting failed: [%s] %s (date context: %s)',
                                    get_class($e),
                                    $e->getMessage(),
                                    $dateContext
                                )
                            );
                        }
                    }
                }

                // overview() doesn't provide size, use 0 as placeholder
                $size = 0;

                $headers[] = sprintf(
                    '%5d)%s %s %-20s (%d chars)',
                    $msgno,
                    $dateStr,
                    $from,
                    $subject,
                    $size
                );

                $msgno++;
            }

            return $headers;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_fetchheader(ImapResource $imap, int $message_num, int $flags = 0): string|false
    {
        $folder = imap_polyfill_get_folder($imap, $manager);
        if (!$folder) {
            return false;
        }

        try {
            $query = $folder->query();
            $message = imap_polyfill_get_message($query, $message_num, $flags, $manager);

            if (!$message) {
                return false;
            }

            return $message->getHeader()->raw;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_body(ImapResource $imap, int $message_num, int $flags = 0): string|false
    {
        $folder = imap_polyfill_get_folder($imap, $manager);
        if (!$folder) {
            return false;
        }

        try {
            $query = $folder->query();
            $message = imap_polyfill_get_message($query, $message_num, $flags, $manager);

            if (!$message) {
                return false;
            }

            if (!($flags & FT_PEEK)) {
                $message->setFlag('Seen');
            }

            // Return raw message body (MIME content without the RFC822 headers)
            // Note: Native imap_body() returns only the body portion, not headers.
            // Use imap_fetchheader() to retrieve headers separately.
            return $message->getRawBody();
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Helper function to retrieve a message by number or UID.
     *
     * @param mixed $query The folder query object
     * @param int $message_num Message number or UID
     * @param int $flags Flags (FT_UID to use UID instead of sequence number)
     * @param ImapPolyfillManager $manager Manager instance for error reporting
     * @return mixed|null Message object or null on failure
     */
    function imap_polyfill_get_message($query, int $message_num, int $flags, ImapPolyfillManager $manager)
    {
        try {
            return ($flags & FT_UID)
                ? $query->getMessageByUid($message_num)
                : $query->getMessageByMsgn($message_num);
        } catch (\Exception $e) {
            $manager->addError("Failed to retrieve message {$message_num}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper function to get connection and folder from IMAP resource.
     *
     * @param ImapResource $imap IMAP resource
     * @param ImapPolyfillManager|null $managerOut Output parameter to receive manager instance
     * @return \Webklex\PHPIMAP\Folder|false Folder object or false on failure
     */
    function imap_polyfill_get_folder(ImapResource $imap, &$managerOut = null)
    {
        $manager = ImapPolyfillManager::getInstance();
        $managerOut = $manager;

        $connection = $manager->getConnectionFromResource($imap);
        if (!$connection) {
            return false;
        }

        $folder = $connection->getCurrentFolder();
        if (!$folder) {
            return false;
        }

        return $folder;
    }

    /**
     * Helper function to get connection from IMAP resource.
     *
     * @param ImapResource $imap IMAP resource
     * @param ImapPolyfillManager|null $managerOut Output parameter to receive manager instance
     * @return \Sugarcrm\Sugarcrm\InboundEmail\Polyfill\ImapConnection|false Connection or false on failure
     */
    function imap_polyfill_get_connection(ImapResource $imap, &$managerOut = null)
    {
        $manager = ImapPolyfillManager::getInstance();
        $managerOut = $manager;

        $connection = $manager->getConnectionFromResource($imap);
        if (!$connection) {
            return false;
        }

        return $connection;
    }

    function imap_fetchbody(ImapResource $imap, int $message_num, string $section, int $flags = 0): string|false
    {
        $folder = imap_polyfill_get_folder($imap, $manager);
        if (!$folder) {
            return false;
        }

        try {
            $query = $folder->query();
            $message = imap_polyfill_get_message($query, $message_num, $flags, $manager);

            if (!$message) {
                $manager->addError("Message {$message_num} not found");
                return false;
            }

            if (!($flags & FT_PEEK)) {
                $message->setFlag('Seen');
            }

            return MimePartResolver::resolveSection($message, $section);
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_fetchstructure(ImapResource $imap, int $message_num, int $flags = 0): \stdClass|false
    {
        $folder = imap_polyfill_get_folder($imap, $manager);
        if (!$folder) {
            return false;
        }

        try {
            $query = $folder->query();
            $message = imap_polyfill_get_message($query, $message_num, $flags, $manager);

            if (!$message) {
                $manager->addError("Message {$message_num} not found");
                return false;
            }

            return MimeStructureBuilder::buildFromMessage($message);
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Helper function to format date for IMAP header.
     *
     * @param mixed $date Date object or string
     * @return string Formatted date string
     */
    function imap_polyfill_format_header_date($date): string
    {
        if (!$date) {
            return '';
        }

        if ($date instanceof Carbon) {
            return $date->format('D, d M Y H:i:s O');
        }

        if (method_exists($date, '__toString')) {
            try {
                $carbonDate = Carbon::parse((string)$date);
                return $carbonDate->format('D, d M Y H:i:s O');
            } catch (\Exception $e) {
                return '';
            }
        }

        return '';
    }

    /**
     * Helper function to get timestamp from date object.
     *
     * @param mixed $date Date object or string
     * @return int Unix timestamp
     */
    function imap_polyfill_get_timestamp($date): int
    {
        if ($date instanceof Carbon) {
            return $date->getTimestamp();
        }

        if ($date && method_exists($date, '__toString')) {
            try {
                return Carbon::parse((string)$date)->getTimestamp();
            } catch (\Exception $e) {
                return time();
            }
        }

        return time();
    }

    /**
     * Helper function to build address object from email address.
     *
     * @param object $address Address object with mail and personal properties
     * @return \stdClass Address object with mailbox, host, and personal properties
     */
    function imap_polyfill_build_address_object($address): \stdClass
    {
        $obj = new \stdClass();
        $parts = explode('@', $address->mail);
        $obj->mailbox = $parts[0] ?? '';
        $obj->host = $parts[1] ?? '';
        $obj->personal = $address->personal ?? '';
        return $obj;
    }

    function imap_headerinfo(
        ImapResource $imap,
        int $message_num,
        int $from_length = 0,
        int $subject_length = 0
    ): \stdClass|false {
        $folder = imap_polyfill_get_folder($imap, $manager);
        if (!$folder) {
            return false;
        }

        try {
            $message = imap_polyfill_get_message($folder->query(), $message_num, 0, $manager);

            if (!$message) {
                return false;
            }

            $header = new \stdClass();

            // Date
            $date = $message->getDate();
            $header->date = imap_polyfill_format_header_date($date);
            $header->Date = $header->date;

            // Subject
            $header->subject = (string)$message->subject;
            $header->Subject = $header->subject;

            // From
            $from = $message->getFrom()[0] ?? null;
            if ($from) {
                $header->from = [imap_polyfill_build_address_object($from)];
                $header->fromaddress = $from->full ?? $from->mail;
            } else {
                $header->from = [];
                $header->fromaddress = '';
            }

            // To
            $to = $message->getTo();
            $header->to = [];
            $header->toaddress = '';
            foreach ($to as $recipient) {
                $header->to[] = imap_polyfill_build_address_object($recipient);
                $toAddress = $recipient->full ?? $recipient->mail;
                $header->toaddress .= ($header->toaddress ? ', ' : '') . $toAddress;
            }

            // Message metadata
            $header->message_id = (string)$message->message_id;
            $header->Msgno = $message->getMsgn();
            $header->Size = $message->getSize();
            $header->udate = imap_polyfill_get_timestamp($date);

            return $header;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_search(
        ImapResource $imap,
        string $criteria,
        int $options = SE_FREE,
        string $charset = ''
    ): array|false {
        $connection = imap_polyfill_get_connection($imap, $manager);
        if (!$connection) {
            return false;
        }

        try {
            // Use tokenizer to properly parse quoted strings and dates
            $criteriaArray = SearchCriteriaTokenizer::tokenize($criteria);
            return $connection->search($criteriaArray, $options);
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_fetch_overview(ImapResource $imap, string $sequence, int $flags = 0): array|false
    {
        $folder = imap_polyfill_get_folder($imap, $manager);
        if (!$folder) {
            return false;
        }

        try {
            $sequences = explode(',', $sequence);
            $overview = [];

            foreach ($sequences as $seq) {
                $seq = trim($seq);
                if (empty($seq)) {
                    continue;
                }

                $query = $folder->query();

                try {
                    if ($flags & FT_UID) {
                        $message = $query->getMessageByUid((int)$seq);
                    } else {
                        $message = $query->getMessageByMsgn((int)$seq);
                    }
                } catch (\Exception $e) {
                    continue;
                }

                if (!$message) {
                    continue;
                }

                $obj = new \stdClass();
                $obj->subject = (string)$message->subject;

                $from = $message->getFrom()[0] ?? null;
                if ($from) {
                    $obj->from = $from->full ?? $from->mail;
                } else {
                    $obj->from = '';
                }

                $to = $message->getTo();
                if (!empty($to)) {
                    $obj->to = $to[0]->full ?? $to[0]->mail;
                } else {
                    $obj->to = '';
                }

                $date = $message->getDate();
                $obj->date = '';
                if ($date) {
                    // Handle both Carbon and Attribute objects
                    if ($date instanceof Carbon) {
                        $obj->date = $date->format('D, d M Y H:i:s O');
                    } elseif (method_exists($date, '__toString')) {
                        $dateStr = (string)$date;
                        $carbonDate = Carbon::parse($dateStr);
                        $obj->date = $carbonDate->format('D, d M Y H:i:s O');
                    }
                }
                $obj->message_id = (string)$message->message_id;
                $obj->size = $message->getSize();
                $obj->uid = $message->getUid();
                $obj->msgno = $message->getMsgn();
                $obj->recent = 0;
                $obj->flagged = $message->hasFlag('Flagged') ? 1 : 0;
                $obj->answered = $message->hasFlag('Answered') ? 1 : 0;
                $obj->deleted = $message->hasFlag('Deleted') ? 1 : 0;
                $obj->seen = $message->hasFlag('Seen') ? 1 : 0;
                $obj->draft = $message->hasFlag('Draft') ? 1 : 0;

                // Handle date timestamp
                $date = $message->getDate();
                if ($date instanceof Carbon) {
                    $obj->udate = $date->getTimestamp();
                } elseif ($date && method_exists($date, '__toString')) {
                    $obj->udate = Carbon::parse((string)$date)->getTimestamp();
                } else {
                    $obj->udate = time();
                }

                $overview[] = $obj;
            }

            return $overview;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_sort(
        ImapResource $imap,
        int $criteria,
        int $reverse,
        int $options = 0,
        ?string $search_criteria = null,
        ?string $charset = null
    ): array|false {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Note: Webklex setFetchOrder() exists but only supports asc/desc for message sequence order.
            // For complex sorting (by date, from, subject, etc.), we use overview() + manual sorting.
            // This approach fetches headers only (not bodies) for better performance.

            // Use overview for performance - doesn't fetch bodies
            $overview = $folder->overview("1:*");

            if (empty($overview)) {
                return [];
            }

            // Convert overview to sortable array with both UID and msgno
            $sortableData = [];
            $msgno = 1;
            foreach ($overview as $uid => $data) {
                $sortableData[] = [
                    'uid' => $uid,
                    'msgno' => $msgno++,
                    'data' => $data
                ];
            }

            // Sort the data
            usort($sortableData, function ($a, $b) use ($criteria, $reverse) {
                $result = 0;
                $aData = $a['data'];
                $bData = $b['data'];

                try {
                    switch ($criteria) {
                        case SORTDATE:
                            $aDate = $aData['date'] ?? null;
                            $bDate = $bData['date'] ?? null;
                            $aTimestamp = 0;
                            $bTimestamp = 0;

                            if ($aDate instanceof Carbon) {
                                $aTimestamp = $aDate->getTimestamp();
                            } elseif ($aDate) {
                                try {
                                    $aTimestamp = Carbon::parse((string)$aDate)->getTimestamp();
                                } catch (\Exception $e) {
                                }
                            }

                            if ($bDate instanceof Carbon) {
                                $bTimestamp = $bDate->getTimestamp();
                            } elseif ($bDate) {
                                try {
                                    $bTimestamp = Carbon::parse((string)$bDate)->getTimestamp();
                                } catch (\Exception $e) {
                                }
                            }

                            $result = $aTimestamp <=> $bTimestamp;
                            break;

                        case SORTARRIVAL:
                            $result = $a['msgno'] <=> $b['msgno'];
                            break;

                        case SORTFROM:
                            $aFrom = $aData['fromaddress'] ?? '';
                            $bFrom = $bData['fromaddress'] ?? '';
                            $result = strcasecmp($aFrom, $bFrom);
                            break;

                        case SORTSUBJECT:
                            $aSubject = $aData['subject'] ?? '';
                            $bSubject = $bData['subject'] ?? '';
                            $result = strcasecmp($aSubject, $bSubject);
                            break;

                        case SORTTO:
                            $aTo = $aData['toaddress'] ?? '';
                            $bTo = $bData['toaddress'] ?? '';
                            $result = strcasecmp($aTo, $bTo);
                            break;

                        case SORTSIZE:
                            // Limitation: overview() doesn't provide message size data.
                            // Fetching size for all messages would negate performance benefits.
                            // Return 0 to maintain API compatibility but with no sorting effect.
                            // Note: Native imap_sort() SORTSIZE is rarely used in practice.
                            $result = 0;
                            break;
                    }
                } catch (\Exception $e) {
                    $result = 0;
                }

                return $reverse ? -$result : $result;
            });

            // Return UIDs or message numbers
            if ($options & SE_UID) {
                return array_map(fn($item) => $item['uid'], $sortableData);
            }

            return array_map(fn($item) => $item['msgno'], $sortableData);
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_mail_move(ImapResource $imap, string $sequence, string $mailbox, int $flags = 0): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Ensure folder is in read-write mode for moving messages
            $folder->select();

            $client = $connection->getClient();
            $targetFolderName = preg_replace('/^.*\}/', '', $mailbox);

            // Parse sequence into individual numbers
            $sequences = explode(',', $sequence);

            foreach ($sequences as $seq) {
                $seq = trim($seq);
                if (empty($seq)) {
                    continue;
                }

                // Use protocol-level moveMessage to avoid issues with Message::move()
                // moveMessage internally does MOVE or falls back to COPY+DELETE
                try {
                    $protocol = $client->getConnection();
                    $useUid = ($flags & CP_UID) ? \Webklex\PHPIMAP\IMAP::ST_UID : \Webklex\PHPIMAP\IMAP::ST_MSGN;
                    $result = $protocol->moveMessage($targetFolderName, (int)$seq, null, $useUid);

                    if (!$result->validatedData()) {
                        $manager->addError("Failed to move message {$seq}");
                    }
                } catch (\Exception $e) {
                    $manager->addError("Move error: " . $e->getMessage());
                }
            }

            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_mail_copy(ImapResource $imap, string $sequence, string $mailbox, int $flags = 0): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Ensure folder is in read-write mode before copy operations
            $folder->select();

            $client = $connection->getClient();
            $targetFolderName = preg_replace('/^.*\}/', '', $mailbox);

            // Parse sequence into individual numbers
            $sequences = explode(',', $sequence);

            foreach ($sequences as $seq) {
                $seq = trim($seq);
                if (empty($seq)) {
                    continue;
                }

                // Use protocol-level copyMessage to avoid issues with Message::copy()
                // which calls examineFolder and fetchNewMail
                try {
                    $protocol = $client->getConnection();
                    $useUid = ($flags & CP_UID) ? \Webklex\PHPIMAP\IMAP::ST_UID : \Webklex\PHPIMAP\IMAP::ST_MSGN;
                    $result = $protocol->copyMessage($targetFolderName, (int)$seq, null, $useUid);

                    if (!$result->validatedData()) {
                        $manager->addError("Failed to copy message {$seq}");
                    }
                } catch (\Exception $e) {
                    $manager->addError("Copy error: " . $e->getMessage());
                }
            }

            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_list(ImapResource $imap, string $reference, string $pattern): array|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        return $connection->getMailboxes($pattern);
    }

    function imap_getmailboxes(ImapResource $imap, string $reference, string $pattern): array|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        $folders = $connection->getClient()->getFolders(false);
        $results = [];

        foreach ($folders as $folder) {
            $folderName = $folder->full_name;

            // Apply pattern matching
            if ($pattern !== '*' && !fnmatch($pattern, $folderName)) {
                continue;
            }

            $obj = new \stdClass();
            $obj->name = $reference . $folderName;
            $obj->delimiter = '/';
            $obj->attributes = 64; // LATT_HASCHILDREN default

            $results[] = $obj;
        }

        return $results ?: false;
    }

    function imap_reopen(ImapResource $imap, string $mailbox, int $flags = 0, int $retries = 0): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folderName = preg_replace('/^.*\}/', '', $mailbox);
            // Check if OP_READONLY flag is set
            $readOnly = ($flags & OP_READONLY) !== 0;
            return $connection->selectFolder($folderName, $readOnly);
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_delete(ImapResource $imap, string $message_nums, int $flags = 0): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            $nums = explode(',', $message_nums);

            foreach ($nums as $num) {
                $query = $folder->query();

                try {
                    if ($flags & FT_UID) {
                        $message = $query->getMessageByUid((int)trim($num));
                    } else {
                        $message = $query->getMessageByMsgn((int)trim($num));
                    }
                } catch (\Exception $e) {
                    continue;
                }

                if ($message) {
                    $message->setFlag('Deleted');
                }
            }

            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_undelete(ImapResource $imap, string $message_nums, int $flags = 0): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            $nums = explode(',', $message_nums);

            foreach ($nums as $num) {
                $query = $folder->query();

                try {
                    if ($flags & FT_UID) {
                        $message = $query->getMessageByUid((int)trim($num));
                    } else {
                        $message = $query->getMessageByMsgn((int)trim($num));
                    }
                } catch (\Exception $e) {
                    continue;
                }

                if (!$message) {
                    continue;
                }

                $message->unsetFlag('Deleted');
            }

            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_expunge(ImapResource $imap): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $client = $connection->getClient();
            if (!$client) {
                return false;
            }

            $client->expunge();
            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_setflag_full(ImapResource $imap, string $sequence, string $flag, int $options = 0): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Ensure folder is in read-write mode before modifying flags
            $folder->select();

            $nums = explode(',', $sequence);

            foreach ($nums as $num) {
                $query = $folder->query();

                try {
                    if ($options & ST_UID) {
                        $message = $query->getMessageByUid((int)trim($num));
                    } else {
                        $message = $query->getMessageByMsgn((int)trim($num));
                    }
                } catch (\Exception $e) {
                    continue;
                }

                if ($message) {
                    $flags = explode(' ', trim($flag, '\\'));
                    foreach ($flags as $f) {
                        $message->setFlag(trim($f, '\\'));
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_clearflag_full(ImapResource $imap, string $sequence, string $flag, int $options = 0): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Ensure folder is in read-write mode before modifying flags
            $folder->select();

            $nums = explode(',', $sequence);

            foreach ($nums as $num) {
                $query = $folder->query();

                try {
                    if ($options & ST_UID) {
                        $message = $query->getMessageByUid((int)trim($num));
                    } else {
                        $message = $query->getMessageByMsgn((int)trim($num));
                    }
                } catch (\Exception $e) {
                    continue;
                }

                if ($message) {
                    $flags = explode(' ', trim($flag, '\\'));
                    foreach ($flags as $f) {
                        $message->unsetFlag(trim($f, '\\'));
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_append(
        ImapResource $imap,
        string $folder,
        string $message,
        string $options = '',
        string $internal_date = ''
    ): bool {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $client = $connection->getClient();
            // Extract folder name from mailbox string (remove {server} part)
            $folderName = preg_replace('/^.*\}/', '', $folder);
            $targetFolder = $client->getFolder($folderName);

            if (!$targetFolder) {
                return false;
            }

            // Convert options string to array for Webklex library
            // Native: "\\Seen \\Flagged" -> Webklex: ["\\Seen", "\\Flagged"]
            $optionsArray = null;
            if (!empty($options)) {
                $optionsArray = array_filter(explode(' ', $options));
            }

            // Convert internal_date empty string to null for Webklex
            $internalDate = !empty($internal_date) ? $internal_date : null;

            $targetFolder->appendMessage($message, $optionsArray, $internalDate);
            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_utf8(string $mime_encoded_text): string
    {
        return MimeEncoder::decodeMimeWords($mime_encoded_text);
    }

    function imap_utf7_encode(string $data): string
    {
        return MimeEncoder::encodeUtf7($data);
    }

    function imap_utf7_decode(string $data): string
    {
        return MimeEncoder::decodeUtf7($data);
    }

    function imap_mime_header_decode(string $text): array|false
    {
        $decoded = imap_utf8($text);

        $result = new \stdClass();
        $result->charset = 'UTF-8';
        $result->text = $decoded;

        return [$result];
    }

    function imap_base64(string $text): string|false
    {
        return MimeEncoder::decodeBase64($text);
    }

    function imap_qprint(string $string): string|false
    {
        return MimeEncoder::decodeQuotedPrintable($string);
    }

    function imap_binary(string $string): string
    {
        return MimeEncoder::decodeBinary($string);
    }

    function imap_8bit(string $string): string
    {
        return MimeEncoder::decode8Bit($string);
    }

    function imap_ping(ImapResource $imap): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            // Try to check connection by examining current folder
            $folder = $connection->getCurrentFolder();
            if ($folder) {
                // Use select() to keep folder in read-write mode
                $folder->select();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_createmailbox(ImapResource $imap, string $mailbox): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $client = $connection->getClient();
            $folderName = preg_replace('/^.*\}/', '', $mailbox);
            // Pass false for expunge - creating a folder doesn't need EXPUNGE command
            // EXPUNGE is only needed when deleting messages, not folders
            $client->createFolder($folderName, false);
            return true;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_deletemailbox(ImapResource $imap, string $mailbox): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $client = $connection->getClient();
            $folderName = preg_replace('/^.*\}/', '', $mailbox);
            $folder = $client->getFolder($folderName);

            if ($folder) {
                // Pass false for expunge - deleting folders doesn't need EXPUNGE command
                $folder->delete(false);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_renamemailbox(ImapResource $imap, string $old_name, string $new_name): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $client = $connection->getClient();
            $oldFolderName = preg_replace('/^.*\}/', '', $old_name);
            $newFolderName = preg_replace('/^.*\}/', '', $new_name);

            $folder = $client->getFolder($oldFolderName);

            if ($folder) {
                // Pass false for expunge - renaming folders doesn't need EXPUNGE command
                $folder->rename($newFolderName, false);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_status(ImapResource $imap, string $mailbox, int $flags): \stdClass|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $client = $connection->getClient();
            $folderName = preg_replace('/^.*\}/', '', $mailbox);
            $folder = $client->getFolder($folderName);

            if (!$folder) {
                return false;
            }

            // Use select() to keep folder in read-write mode
            $status = $folder->select();

            $result = new \stdClass();
            $result->flags = $flags;

            if ($flags & SA_MESSAGES) {
                $result->messages = $status['exists'] ?? 0;
            }

            if ($flags & SA_RECENT) {
                $result->recent = $status['recent'] ?? 0;
            }

            if ($flags & SA_UNSEEN) {
                $result->unseen = $status['unseen'] ?? 0;
            }

            if ($flags & SA_UIDNEXT) {
                $result->uidnext = $status['uidnext'] ?? 0;
            }

            if ($flags & SA_UIDVALIDITY) {
                $result->uidvalidity = $status['uidvalidity'] ?? 0;
            }

            return $result;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_msgno(ImapResource $imap, int $uid): int|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Use getMessageByUid() as per documentation
            try {
                $message = $folder->query()->getMessageByUid($uid);
            } catch (\Exception $e) {
                $manager->addError($e->getMessage());
                return false;
            }

            if (!$message) {
                return false;
            }

            // Use getMsgn() magic method - returns int
            return $message->getMsgn();
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_uid(ImapResource $imap, int $message_num): int|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            try {
                $message = $folder->query()->getMessageByMsgn($message_num);
            } catch (\Exception $e) {
                return false;
            }

            if (!$message) {
                return false;
            }

            // Use getUid() method which returns int
            return $message->getUid();
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_subscribe(ImapResource $imap, string $mailbox): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $client = $connection->getClient();
            $folderName = preg_replace('/^.*\}/', '', $mailbox);
            $folder = $client->getFolder($folderName);

            if ($folder) {
                $folder->subscribe();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_unsubscribe(ImapResource $imap, string $mailbox): bool
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $client = $connection->getClient();
            $folderName = preg_replace('/^.*\}/', '', $mailbox);
            $folder = $client->getFolder($folderName);

            if ($folder) {
                $folder->unsubscribe();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_rfc822_parse_adrlist(string $address, string $default_host): array
    {
        $addresses = explode(',', $address);
        $result = [];

        foreach ($addresses as $addr) {
            $addr = trim($addr);
            if (empty($addr)) {
                continue;
            }

            $obj = new \stdClass();

            if (preg_match('/"?([^"<]+)"?\s*<([^>]+)>/', $addr, $matches)) {
                $obj->personal = trim($matches[1]);
                $email = trim($matches[2]);
            } else {
                $obj->personal = '';
                $email = $addr;
            }

            $parts = explode('@', $email);
            $obj->mailbox = $parts[0] ?? '';
            $obj->host = $parts[1] ?? $default_host;

            $result[] = $obj;
        }

        return $result;
    }

    function imap_rfc822_write_address(
        string $mailbox,
        string $host,
        string $personal
    ): string|false {
        if (empty($mailbox) || empty($host)) {
            return false;
        }

        // Quote mailbox if it contains special characters (like @)
        if (str_contains($mailbox, '@') || str_contains($mailbox, ' ') || str_contains($mailbox, '"')) {
            $mailbox = '"' . str_replace('"', '\\"', $mailbox) . '"';
        }

        $email = $mailbox . '@' . $host;

        if (!empty($personal)) {
            // RFC822 specials that require quoting in personal names:
            // ( ) < > @ , ; : \ " . [ ]
            // NOT quoted: ' (apostrophe), - (hyphen), _ (underscore)
            if (preg_match('/[()<>@,;:\\\\".\[\]]/', $personal)) {
                return '"' . str_replace('"', '\\"', $personal) . '" <' . $email . '>';
            }
            return $personal . ' <' . $email . '>';
        }

        return $email;
    }

    function imap_rfc822_parse_headers(
        string $headers,
        string $default_host = 'UNKNOWN'
    ): \stdClass {
        $result = new \stdClass();
        $lines = explode("\r\n", $headers);

        foreach ($lines as $line) {
            if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);

                switch ($key) {
                    case 'date':
                        $result->date = $value;
                        break;
                    case 'subject':
                        $result->subject = $value;
                        break;
                    case 'from':
                        $result->from = imap_rfc822_parse_adrlist($value, $default_host);
                        $result->fromaddress = $value;
                        break;
                    case 'to':
                        $result->to = imap_rfc822_parse_adrlist($value, $default_host);
                        $result->toaddress = $value;
                        break;
                    case 'cc':
                        $result->cc = imap_rfc822_parse_adrlist($value, $default_host);
                        $result->ccaddress = $value;
                        break;
                    case 'reply-to':
                        $result->reply_to = imap_rfc822_parse_adrlist($value, $default_host);
                        $result->reply_toaddress = $value;
                        break;
                    case 'message-id':
                        $result->message_id = $value;
                        break;
                }
            }
        }

        return $result;
    }

    function imap_timeout(int $timeout_type, int $timeout = -1): int|bool
    {
        $manager = ImapPolyfillManager::getInstance();

        if ($timeout === -1) {
            return $manager->getTimeout($timeout_type);
        }

        $manager->setTimeout($timeout_type, $timeout);
        return true;
    }

    function imap_check(ImapResource $imap): \stdClass|false
    {
        $folder = imap_polyfill_get_folder($imap, $manager);
        if (!$folder) {
            return false;
        }

        try {
            // Use select() to keep folder in read-write mode (not examine which is read-only)
            $status = $folder->select();

            $check = new \stdClass();
            $check->Date = date('D, d M Y H:i:s O');
            $check->Driver = 'webklex/php-imap';
            $check->Mailbox = $folder->full_name;
            $check->Nmsgs = $status['exists'] ?? 0;
            $check->Recent = $status['recent'] ?? 0;

            return $check;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_num_msg(ImapResource $imap): int|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Use select() to keep folder in read-write mode
            $status = $folder->select();
            return $status['exists'] ?? 0;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_num_recent(ImapResource $imap): int|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Use select() to keep folder in read-write mode
            $status = $folder->select();
            return $status['recent'] ?? 0;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_mailboxmsginfo(ImapResource $imap): \stdClass|false
    {
        $manager = ImapPolyfillManager::getInstance();
        $connection = $manager->getConnectionFromResource($imap);

        if (!$connection) {
            return false;
        }

        try {
            $folder = $connection->getCurrentFolder();
            if (!$folder) {
                return false;
            }

            // Use select() to keep folder in read-write mode
            $status = $folder->select();
            $messages = $folder->query()->all()->get();

            $info = new \stdClass();
            $info->Date = date('D, d M Y H:i:s O');
            $info->Driver = 'webklex/php-imap';
            $info->Mailbox = $folder->full_name;
            $info->Nmsgs = $status['exists'] ?? 0;
            $info->Recent = $status['recent'] ?? 0;
            $info->Unread = 0;
            $info->Deleted = 0;
            $info->Size = 0;

            foreach ($messages as $message) {
                if (!$message->hasFlag('Seen')) {
                    $info->Unread++;
                }
                if ($message->hasFlag('Deleted')) {
                    $info->Deleted++;
                }
                // Use getSize() to avoid fetching entire message body
                $info->Size += $message->getSize();
            }

            return $info;
        } catch (\Exception $e) {
            $manager->addError($e->getMessage());
            return false;
        }
    }

    function imap_alerts(): array|false
    {
        $manager = ImapPolyfillManager::getInstance();
        return $manager->getAlerts();
    }

    function imap_errors(): array|false
    {
        $manager = ImapPolyfillManager::getInstance();
        return $manager->getErrors();
    }

    function imap_last_error(): string|false
    {
        $manager = ImapPolyfillManager::getInstance();
        return $manager->getLastError();
    }

    /**
     * Sanitize header values to prevent email header injection attacks.
     *
     * This helper removes CR/LF/NULL characters that could be used to inject
     * additional headers, and normalizes any non-string input to string.
     *
     * @param mixed $value The header value to sanitize
     * @return string The sanitized header value
     */
    function imap_polyfill_sanitize_header(mixed $value): string
    {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        // Remove CR/LF/NULL characters that could inject additional headers
        return str_replace(["\r", "\n", "\0"], '', $value);
    }

    function imap_mail_compose(array $envelope, array $bodies): string|false
    {
        try {
            $message = '';

            // Add FROM header
            if (isset($envelope['from'])) {
                $message .= "From: " . imap_polyfill_sanitize_header($envelope['from']) . "\r\n";
            }

            // Add SUBJECT header
            if (isset($envelope['subject'])) {
                $message .= "Subject: " . imap_polyfill_sanitize_header($envelope['subject']) . "\r\n";
            }

            // Add TO header
            if (isset($envelope['to'])) {
                $message .= "To: " . imap_polyfill_sanitize_header($envelope['to']) . "\r\n";
            }

            // Add CC header
            if (isset($envelope['cc'])) {
                $message .= "Cc: " . imap_polyfill_sanitize_header($envelope['cc']) . "\r\n";
            }

            // Add DATE header (if not provided, use current)
            if (isset($envelope['date'])) {
                $message .= "Date: " . imap_polyfill_sanitize_header($envelope['date']) . "\r\n";
            }

            // Add MIME-Version header
            $message .= "MIME-Version: 1.0\r\n";

            // Process body parts
            if (!empty($bodies) && isset($bodies[0])) {
                $body = $bodies[0];

                // Add Content-Type based on body type
                $contentType = 'text/plain';
                if (isset($body['type'])) {
                    switch ($body['type']) {
                        case TYPETEXT:
                            $subtype = $body['subtype'] ?? 'plain';
                            $contentType = "text/" . strtolower($subtype);
                            break;
                        case TYPEMULTIPART:
                            $subtype = $body['subtype'] ?? 'mixed';
                            $contentType = "multipart/" . strtolower($subtype);
                            break;
                    }
                }

                $message .= "Content-Type: " . $contentType . "\r\n";
            }

            // End headers
            $message .= "\r\n";

            // Add body content
            foreach ($bodies as $body) {
                if (isset($body['contents.data'])) {
                    $message .= $body['contents.data'];
                } elseif (isset($body['data'])) {
                    $message .= $body['data'];
                }
            }

            return $message;
        } catch (\Exception $e) {
            ImapPolyfillManager::getInstance()->addError($e->getMessage());
            return false;
        }
    }
}
