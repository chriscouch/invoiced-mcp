<?php

namespace App\EntryPoint\Command;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\ProcessAdyenReportJob;
use App\Integrations\Adyen\Models\AdyenReport;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessAdyenReports extends Command
{
    public function __construct(
        private Queue $queue,
        private Connection $database,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('process-adyen-reports')
            ->setDescription('Queues any Adyen reports that have not been processed yet for processing.')
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'Report type to filter by, e.g. balanceplatform_accounting_report'
            )
            ->addOption(
                'reprocess',
                null,
                InputOption::VALUE_NONE,
                'Marks previously processed reports as unprocessed'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getOption('type');
        $reprocess = $input->getOption('reprocess');

        $reports = AdyenReport::where('processed', false)
            ->sort('id ASC');
        if ($type) {
            $reports->where('report_type', $type);
        }

        // Reprocess previously processed reports if requested
        if ($reprocess) {
            if (!$type) {
                throw new Exception('Missing type option');
            }

            $this->database->executeQuery('UPDATE AdyenReports SET processed = 0 WHERE processed = 1 AND report_type=?', [$type]);
        }

        foreach ($reports->all() as $report) {
            $this->queue->enqueue(ProcessAdyenReportJob::class, [
                'report' => $report->id,
            ], QueueServiceLevel::Batch);
        }

        return 0;
    }
}
