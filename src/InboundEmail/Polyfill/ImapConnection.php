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

namespace Sugarcrm\Sugarcrm\InboundEmail\Polyfill;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;

class ImapConnection
{
    private ?Client $client = null;
    private ?Folder $currentFolder = null;
    private string $mailbox;

    public function __construct(string $mailbox, string $username, string $password, array $options = [])
    {
        $this->mailbox = $mailbox;
        $this->connect($mailbox, $username, $password, $options);
    }

    private function connect(string $mailbox, string $username, string $password, array $options): void
    {
        $parsedMailbox = $this->parseMailbox($mailbox);

        // Get timeout from manager or use default
        $manager = ImapPolyfillManager::getInstance();
        $timeout = $manager->getTimeout(1); // IMAP_OPENTIMEOUT

        $config = [
            'host' => $parsedMailbox['host'],
            'port' => $parsedMailbox['port'],
            'encryption' => $parsedMailbox['encryption'],
            'validate_cert' => $parsedMailbox['validate_cert'],
            'username' => $username,
            'password' => $password,
            'protocol' => $parsedMailbox['protocol'],
            'timeout' => $timeout,
        ];

        // Handle OAuth2 authentication
        if ($parsedMailbox['oauth2']) {
            $config['authentication'] = 'oauth';
            $config['access_token'] = $password;
            // Clear password field when using OAuth2 to avoid duplication
            $config['password'] = null;
        }

        $cm = new ClientManager();
        $this->client = $cm->make($config);
        $this->client->connect();

        // Clear sensitive data from memory after connection is established
        unset($config['password'], $config['access_token'], $username, $password);

        if (!empty($parsedMailbox['folder'])) {
            // Open folder in read-write mode by default (not read-only)
            $this->selectFolder($parsedMailbox['folder'], false);
        }
    }

    private function parseMailbox(string $mailbox): array
    {

        // Parse IMAP mailbox string: {host:port/service=protocol/flags}FOLDER
        // Example: {outlook.office365.com:993/service=imap/notls/novalidate-cert/secure}INBOX
        // Example: {imap.gmail.com:993/imap/ssl}INBOX
        // Example: {imap.gmail.com/imap/ssl}INBOX (port optional)

        if (!preg_match('/\{([^:}]+)(?::(\d+))?\/(.+)\}(.*)/', $mailbox, $matches)) {
            throw new \InvalidArgumentException('Invalid mailbox format.');
        }

        $host = $matches[1];
        $port = !empty($matches[2]) ? (int)$matches[2] : 143;
        $flagsString = $matches[3] ?? '';
        $folder = $matches[4] ?: 'INBOX';

        // Parse flags by splitting on /
        $flagsArray = explode('/', $flagsString);

        $protocol = 'imap';
        $encryption = false;
        $validate_cert = true;
        $oauth2 = false;

        foreach ($flagsArray as $flag) {
            $flag = trim($flag);
            $flagLower = strtolower($flag);

            // Handle service=protocol format
            if (strpos($flagLower, 'service=') === 0) {
                $parts = explode('=', $flag, 2);
                if (count($parts) === 2 && !empty($parts[1])) {
                    $protocol = strtolower($parts[1]);
                }
                continue;
            }

            // Handle standalone protocol
            if ($flagLower === 'imap' || $flagLower === 'pop3') {
                $protocol = $flagLower;
                continue;
            }

            // Handle SSL/TLS
            if ($flagLower === 'ssl' || $flagLower === 'tls') {
                $encryption = 'ssl';
                continue;
            }

            // Handle notls
            if ($flagLower === 'notls') {
                // notls with port 993 means use SSL anyway (Office365 pattern)
                if ($port === 993 || $port === 995) {
                    $encryption = 'ssl';
                } else {
                    $encryption = false;
                }
                continue;
            }

            // Handle certificate validation
            if ($flagLower === 'novalidate-cert') {
                $validate_cert = false;
                continue;
            }

            if ($flagLower === 'validate-cert') {
                $validate_cert = true;
                continue;
            }

            // Handle OAuth2
            if ($flagLower === 'xoauth2' || $flagLower === 'oauth') {
                $oauth2 = true;
                continue;
            }
        }

        // If port is 993 or 995 (SSL ports) and encryption is still false, enable it
        if (!$encryption && ($port === 993 || $port === 995)) {
            $encryption = 'ssl';
        }

        $result = [
            'host' => $host,
            'port' => $port,
            'protocol' => $protocol,
            'encryption' => $encryption,
            'validate_cert' => $validate_cert,
            'oauth2' => $oauth2,
            'folder' => $folder,
            'flags' => $flagsString,
        ];

        return $result;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function selectFolder(string $folderName, bool $readOnly = false): bool
    {
        try {
            if ($readOnly) {
                // EXAMINE mode (read-only)
                $this->client->getConnection()->examineFolder($folderName);
            } else {
                // SELECT mode (read-write) - default
                // Force select even if already active to refresh folder state
                $this->client->openFolder($folderName, true);
            }
            // Get fresh folder object after selection
            $this->currentFolder = $this->client->getFolderByPath($folderName);
            return true;
        } catch (\Exception $e) {
            ImapPolyfillManager::getInstance()->addError($e->getMessage());
            return false;
        }
    }

    public function getCurrentFolder(): ?Folder
    {
        return $this->currentFolder;
    }

    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
        }
    }

    public function check(): ?\stdClass
    {
        if (!$this->currentFolder) {
            return null;
        }

        try {
            $status = $this->currentFolder->examine();

            $result = new \stdClass();
            $result->Date = date('D, d M Y H:i:s O');
            $result->Driver = 'webklex';
            $result->Mailbox = $this->mailbox;
            $result->Nmsgs = $status['exists'] ?? 0;
            $result->Recent = $status['recent'] ?? 0;

            return $result;
        } catch (\Exception $e) {
            ImapPolyfillManager::getInstance()->addError($e->getMessage());
            return null;
        }
    }

    public function getMailboxes(string $pattern = '*'): array
    {
        try {
            $folders = $this->client->getFolders(false, $pattern);
            $mailboxes = [];

            // Extract server spec from mailbox (everything up to and including '}')
            $serverSpec = preg_match('/^(\{[^}]+\})/', $this->mailbox, $matches) ? $matches[1] : '';

            foreach ($folders as $folder) {
                // Native imap_list returns: {server}FolderPath
                // Folder name from Webklex already includes full path
                $mailboxes[] = $serverSpec . $folder->name;
            }

            return $mailboxes;
        } catch (\Exception $e) {
            ImapPolyfillManager::getInstance()->addError($e->getMessage());
            return [];
        }
    }

    public function search(array $criteria, int $options = SE_UID): array
    {
        if (!$this->currentFolder) {
            return [];
        }

        try {
            // Check if we have ANY criteria other than ALL
            $hasRealCriteria = false;
            foreach ($criteria as $criterion) {
                if (strtoupper($criterion) !== 'ALL') {
                    $hasRealCriteria = true;
                    break;
                }
            }

            if (!$hasRealCriteria) {
                // For "ALL" searches, just return a range of message numbers/UIDs
                // This is much faster than fetching all messages
                $status = $this->currentFolder->examine();
                $total = $status['exists'] ?? 0;

                if ($total === 0) {
                    return [];
                }

                if ($options === SE_UID) {
                    // Get UIDs for all messages using sequence range
                    $query = $this->currentFolder->query();
                    $messages = $query->all()->limit($total)->get();
                    $uids = [];
                    foreach ($messages as $msg) {
                        $uid = $msg->getUid();
                        $uids[] = is_object($uid) ? (int)(string)$uid : (int)$uid;
                    }
                    return $uids;
                } else {
                    // Return message numbers 1 to $total
                    return range(1, $total);
                }
            }

            // Apply specific search criteria
            $query = $this->currentFolder->query();
            ImapSearchHelper::applyCriteria($query, $criteria);
            $messages = $query->get();

            if ($options === SE_UID) {
                $uids = [];
                foreach ($messages as $msg) {
                    $uid = $msg->getUid();
                    $uids[] = is_object($uid) ? (int)(string)$uid : (int)$uid;
                }
                return $uids;
            }

            $msgnos = [];
            foreach ($messages as $msg) {
                $msgno = $msg->getMessageNumber();
                $msgnos[] = is_object($msgno) ? (int)(string)$msgno : (int)$msgno;
            }
            return $msgnos;
        } catch (\Exception $e) {
            ImapPolyfillManager::getInstance()->addError($e->getMessage());
            return [];
        }
    }
}
