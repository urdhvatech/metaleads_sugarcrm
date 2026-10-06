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

namespace Sugarcrm\Sugarcrm\Maps;

use RunnableSchedulerJob;
use SchedulersJob;
use Sugarcrm\Sugarcrm\Maps\GCSClient;
use Sugarcrm\Sugarcrm\Maps\SingleBatchResolver;
use Sugarcrm\Sugarcrm\Maps\Engine\Geocode\Container;

/**
 *
 * Persistent scheduler which is responsible to create subsequent jobs based
 * on what needs to be consumed from the database queue.
 *
 */
class Resolver implements RunnableSchedulerJob
{
    /**
     * @var SchedulersJob
     */
    protected $job;

    protected $gcsClient;

    /**
     * @var \Sugarcrm\Sugarcrm\Maps\Engine\Geocode\Container
     */
    protected $container;

    /**
     * {@inheritDoc}
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->gcsClient = new GCSClient();
    }

    /**
     * {@inheritdoc}
     */
    public function setJob(SchedulersJob $job)
    {
        $this->job = $job;
    }

    /**
     * {@inheritdoc}
     */
    public function run($data)
    {
        if (!hasSystemMapsLicense()) {
            return $this->job->failJob(translate('LBL_MAPS_NO_LICENSE_ACCESS'));
        }

        $message = '';
        $batchIds = $this->gcsClient->getBatchIds();

        if (!empty($batchIds)) {
            // Create consumer jobs
            foreach ($batchIds as $batchId) {
                $this->container->queueManager->createConsumer($batchId, SingleBatchResolver::class);
            }

            $message = 'Created consumer for batch id(s): ' . implode(', ', $batchIds);
        } else {
            $message = 'No batch ids currently in queue - nothing to do';
        }
       
        return $this->job->succeedJob($message);
    }
}
