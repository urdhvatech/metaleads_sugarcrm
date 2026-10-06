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

/**
 * Resource wrapper for IMAP connections to maintain compatibility with native IMAP extension.
 * Native imap_open() returns a resource object, so this class mimics that behavior.
 */
class ImapResource
{
    private int $id;
    private ImapConnection $connection;

    public function __construct(int $id, ImapConnection $connection)
    {
        $this->id = $id;
        $this->connection = $connection;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getConnection(): ImapConnection
    {
        return $this->connection;
    }
}
