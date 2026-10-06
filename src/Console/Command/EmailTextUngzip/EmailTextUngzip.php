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

use Sugarcrm\Sugarcrm\Console\CommandRegistry\Mode\InstanceModeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class EmailTextUngzip extends Command implements InstanceModeInterface
{
    private $threads = 8;
    private $action;
    private $batchSize = 5000;
    private $emailTextTable = '';
    private $progressTable = 'email_processing_progress';
    private $connection;

    protected function configure(): void
    {
        $this
            ->setName('email:process')
            ->setDescription('Process emails in parallel')
            ->addOption(
                'action',
                null,
                InputOption::VALUE_REQUIRED,
                'The action to perform on the emails (gzip or ungzip)',
                'ungzip'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $t = microtime(true);

        $this->connection = \DBManagerFactory::getConnection();
        $this->emailTextTable = \BeanFactory::getBean('EmailText')->getTableName();
        $this->action = $input->getOption('action');

        $this->generateProcessEmailsBatch($output);
        $batches = $this->fetchUnprocessedBatches();
        $this->processBatchesInParallel($batches, $output);

        $t2 = microtime(true) - $t;
        $output->writeln("All processes completed. Time: $t2 sec");

        return Command::SUCCESS;
    }

    private function generateProcessEmailsBatch(OutputInterface $output)
    {
        $collation = $this->connection->createSchemaManager()
            ->listTableDetails('emails_text')
            ->getOption('collation');
        $collation = $collation ?: 'utf8mb4_0900_ai_ci';

        $createTableSql = "
        CREATE TABLE IF NOT EXISTS email_processing_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            _offset INT NOT NULL,
            _limit INT NOT NULL,
            _processed BOOLEAN DEFAULT FALSE,
            UNIQUE KEY (_offset))
            DEFAULT CHARSET=utf8mb4 COLLATE=$collation
        ";
        $this->connection->executeStatement($createTableSql);

        $notProcessedCount = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->progressTable)
            ->where('_processed = :processed')
            ->setParameter('processed', 0)
            ->executeQuery()
            ->fetchOne();

        if ($notProcessedCount == 0) {
            $this->connection->executeStatement('TRUNCATE TABLE ' . $this->progressTable);
        }

        $totalRecords = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->emailTextTable)
            ->executeQuery()
            ->fetchOne();

        $batches = ceil($totalRecords / $this->batchSize);

        for ($i = 0; $i < $batches; $i++) {
            $offset = $i * $this->batchSize;
            $queryBuilder = $this->connection->createQueryBuilder();

            $queryBuilder->select('COUNT(*)')
                ->from($this->progressTable)
                ->where('_offset = :offset')
                ->setParameter('offset', $offset);

            $batchExists = $queryBuilder->executeQuery()->fetchOne();

            if ($batchExists > 0) {
                continue;
            }

            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder->insert($this->progressTable)
                ->values([
                    '_offset' => ':offset',
                    '_limit' => ':limit',
                    '_processed' => ':processed',
                ])
                ->setParameters([
                    'offset' => $offset,
                    'limit' => $this->batchSize,
                    'processed' => 0,
                ]);

            try {
                $queryBuilder->executeQuery();
            } catch (\Doctrine\DBAL\Exception $e) {
                $output->writeln("Error while generating batches: " . $e->getMessage());
            }
        }
    }

    private function fetchUnprocessedBatches()
    {
        $builder = $this->connection->createQueryBuilder();
        $builder->select('id, _offset, _limit')
            ->from($this->progressTable)
            ->where('_processed = :processed')
            ->setParameter('processed', false);

        return $builder->executeQuery()->fetchAllAssociative();
    }

    private function processBatchesInParallel(array $batches, OutputInterface $output)
    {
        $processes = [];
        $maxProcesses = $this->threads;
        $output->writeln("Start processes ...");

        foreach ($batches as $batch) {
            $id = $batch['id'];
            $offset = $batch['_offset'];
            $limit = $batch['_limit'];

            if (!empty($_SERVER['_']) && isset($_SERVER['argv'][0]) && $_SERVER['_'] == $_SERVER['argv'][0]) {
                $commandCmd = [$_SERVER['argv'][0]];
            } elseif (!empty($_SERVER['argv'][0]) && strpos($_SERVER['argv'][0], 'bin' . DIRECTORY_SEPARATOR . 'sugarcrm') !== false) {
                if (defined('SHADOW_INSTANCE_DIR')) {
                    // MTS
                    $commandCmd = ['shadowy', $_SERVER['argv'][0]];
                } elseif (!empty($_SERVER['_'])) {
                    // Linux
                    $commandCmd = [$_SERVER['_'], $_SERVER['argv'][0]];
                } else {
                    // other OS, such as Windows
                    $phpBinaryFinder = new PhpExecutableFinder();
                    $phpExe = $phpBinaryFinder->find();
                    $commandCmd = [$phpExe, $_SERVER['argv'][0]];
                }
            } else {
                $commandCmd = ['.' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'sugarcrm'];
            }
            $commandArgs = ['email:process-batch', $id, $offset, $limit, $this->action];
            $command = array_merge($commandCmd, $commandArgs);
            $cwd = null;
            if (defined('SHADOW_INSTANCE_DIR')) {
                $cwd = SHADOW_INSTANCE_DIR;
            }
            $processes[] = new Process($command, $cwd, null, null, 7200);

            if (count($processes) >= $maxProcesses) {
                $this->waitForProcesses($processes);
                $processes = [];
            }
        }

        if (count($processes) > 0) {
            $this->waitForProcesses($processes);
        }
    }

    private function waitForProcesses(array $processes)
    {
        foreach ($processes as $process) {
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
        }
    }
}
