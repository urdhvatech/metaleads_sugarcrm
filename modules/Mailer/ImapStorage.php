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

namespace Sugarcrm\Sugarcrm\modules\Mailer;

use Laminas\Mail;
use Laminas\Mail\Storage\Exception;

class ImapStorage extends Mail\Storage\Imap
{
    /**
     * Set the \Deleted flag on a message
     *
     * @param int $uid The UID of the message to set the flag on
     */
    public function setDeletedFlag(int $uid)
    {
        if (! $this->protocol->store([Mail\Storage::FLAG_DELETED], $uid, null, '+')) {
            throw new Exception\RuntimeException('cannot set deleted flag');
        }
    }

    /**
     * Send an EXPUNGE command to the server to permanently remove all messages marked for deletion
     *
     * @return void
     */
    public function expunge()
    {
        if (! $this->protocol->expunge()) {
            throw new Exception\RuntimeException('cannot expunge mailbox');
        }
    }
}
