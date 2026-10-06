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

namespace Sugarcrm\Sugarcrm\GAI\Constants;

abstract class GAIConstants
{
    public const GAI_MODULE_NAME = 'SummarizationGai';
    public const GAI_QUEUE_NOTIFICAITON_NAME = 'NotificationQueueGai';
    public const SUMZ_STATUS_FAILED = 'failed';
    public const SUMZ_STATUS_PROCESSING = 'inProgress';
    public const SUMZ_STATUS_COMPLETED = 'completed';
    public const SUMZ_STATUS_ERROR = 'error';

    // Ingestion status
    public const SUMZ_STATUS_READY_FOR_INGEST = 'readyForIngest';
    public const SUMZ_STATUS_INGEST_SUCCESS = 'ingestSuccess';
    public const SUMZ_STATUS_PENDING = 'pending';
    public const SUMZ_STATUS_ON_HOLD = 'onHold';
    public const SUMZ_STATUS_TIMEOUT = 'timeout';
    public const SUMZ_STATUS_NOT_FOUND = 'notFound';

    // Define the size limit in bytes,  for now 6mb is the limit imposed by AWS on lambda
    public const DATA_MAX_SIZE = 5 * 1024 * 1024;
}
