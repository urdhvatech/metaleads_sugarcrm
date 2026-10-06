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

namespace Sugarcrm\Sugarcrm\GAI\Exception;

/**
 * Used to throw an exception when the user has insufficient tokens to perform an action
 */
class MaxTokenApiException extends \SugarApiException
{
    public $errorLabel = 'insufficient_tokens';
    public $messageLabel = 'Payment Required';

    public function __construct($messageLabel = null, $msgArgs = null, $moduleName = null, $httpCode = 0, $errorLabel = null)
    {
        $this->httpCode = 402;
        parent::__construct($this->messageLabel, $msgArgs, $moduleName, $this->httpCode, $this->errorLabel);
    }
}
