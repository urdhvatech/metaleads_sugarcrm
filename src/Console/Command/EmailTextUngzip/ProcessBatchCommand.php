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

namespace Sugarcrm\Sugarcrm\Console\Command\EmailTextUngzip;

use BeanFactory;
use Sugarcrm\Sugarcrm\Cache\Exception;
use Sugarcrm\Sugarcrm\Console\CommandRegistry\Mode\InstanceModeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessBatchCommand extends Command implements InstanceModeInterface
{
    private $connection;
    private $emailTextTable = '';

    protected function configure()
    {
        $this
            ->setName('email:process-batch')
            ->setDescription('Process a single email batch')
            ->addArgument('id', InputArgument::REQUIRED, 'Batch ID')
            ->addArgument('offset', InputArgument::REQUIRED, 'Offset')
            ->addArgument('limit', InputArgument::REQUIRED, 'Limit')
            ->addArgument('action', InputArgument::REQUIRED, 'Action (gzip or ungzip)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->connection = \DBManagerFactory::getConnection();
        $this->emailTextTable = \BeanFactory::getBean('EmailText')->getTableName();
        $id = $input->getArgument('id');
        $offset = $input->getArgument('offset');
        $limit = $input->getArgument('limit');
        $action = $input->getArgument('action');

        try {
            $this->processBatch($id, $offset, $limit, $action, $output);
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
        }

        return Command::SUCCESS;
    }

    private function processBatch($id, $offset, $limit, $action, $output)
    {
        $emailBean = \BeanFactory::newBean('Emails');

        $builder = $this->connection->createQueryBuilder();
        $results = $builder->select('email_id, description, description_html')
            ->from($this->emailTextTable)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('email_id', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $email) {
            $updateData = $this->processEmail($email, $action, $emailBean);

            if (!empty($updateData)) {
                $this->connection->update(
                    $this->emailTextTable,
                    $updateData,
                    ['email_id' => $email['email_id']]
                );
            }
        }

        $this->connection->update(
            'email_processing_progress',
            ['_processed' => true],
            ['id' => $id]
        );
    }

    private function processEmail($email, $action, $emailBean)
    {
        $updateData = [];
        $descriptionFields = ['description', 'description_html'];

        foreach ($descriptionFields as $field) {
            $content = $email[$field];
            if ($content && trim($content)) {
                $isCompressed = $emailBean->tryUngzipContent($content);

                if ($action === 'gzip' && !$isCompressed) {
                    $updateData[$field] = $emailBean->gzipContent($content);
                } elseif ($action === 'ungzip' && $isCompressed) {
                    $updateData[$field] = $isCompressed;
                }
            }
        }

        return $updateData;
    }
}
