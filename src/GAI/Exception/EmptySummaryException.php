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
 * Used to throw an exception when there is not enough data to generate a summary
 */
class EmptySummaryException extends \SugarApiException
{
    public $errorLabel = 'Empty_summary';
    public $messageLabel = 'Insufficient data to generate summary';

    public function __construct($messageLabel = null, $msgArgs = null, $moduleName = null, $httpCode = 0, $errorLabel = null)
    {
        $this->httpCode = 422;
        parent::__construct($this->messageLabel, $msgArgs, $moduleName, $this->httpCode, $this->errorLabel);
    }
}
