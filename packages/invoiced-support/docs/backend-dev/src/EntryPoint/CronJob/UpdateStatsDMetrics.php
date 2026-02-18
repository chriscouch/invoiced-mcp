<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\ResqueInitializer;
use App\Core\Queue\ResqueSchedulerWorker;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Aws\CloudWatch\CloudWatchClient;
use Aws\Exception\AwsException;
use Aws\Ses\SesClient;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use App\Core\Utils\InfuseUtility as Utility;
use Resque;
use Resque_Worker;
use ResqueScheduler;

class UpdateStatsDMetrics implements CronJobInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    private static array $accountingIntegrations = [
        'intacct',
        'netsuite',
        'quickbooks_desktop',
        'quickbooks_online',
        'xero',
    ];

    public function __construct(
        private Connection $database,
        private ResqueInitializer $resqueInitializer,
        private SesClient $sesClient,
        private CloudWatchClient $cwClient,
        private string $environment,
    ) {
    }

    public static function getName(): string
    {
        return 'update_statsd_metrics';
    }

    public static function getLockTtl(): int
    {
        return 59;
    }

    public function execute(Run $run): void
    {
        $this->resqueInitializer->initialize();

        $this->newTrials();
        $this->newUserAccounts();
        $this->resque();
        $this->accountingIntegrations();
        $this->unreconciledCharges();
        $this->tableSizes();
        $this->awsSendQuota();

        $run->writeOutput('Updated metrics');
    }

    private function newTrials(): void
    {
        $last24h = Utility::unixToDb(strtotime('-1 day'));
        $newCompanies = $this->database->fetchOne('SELECT COUNT(*) FROM Companies WHERE created_at >= ?', [$last24h]);
        $this->statsd->gauge('bi.new_companies_last_24h', $newCompanies);
    }

    private function newUserAccounts(): void
    {
        $last24h = Utility::unixToDb(strtotime('-1 day'));
        $newUserAccounts = $this->database->fetchOne('SELECT COUNT(*) FROM Users WHERE created_at >= ?', [$last24h]);
        $this->statsd->gauge('bi.new_user_accounts_last_24h', $newUserAccounts);
    }

    private function resque(): void
    {
        $numWorkers = count(Resque_Worker::all());
        $this->statsd->gauge('resque.active_workers', $numWorkers);

        $queueSizes = ['batch' => 0, 'normal' => 0];
        foreach (Resque::queues() as $queue) {
            $queueSize = Resque::size($queue);
            if (!isset($queueSizes[$queue])) {
                $queueSizes[$queue] = 0;
            }
            $queueSizes[$queue] += $queueSize;
            $this->statsd->gauge('resque.queue_size', $queueSize, 1.0, ['queue' => $queue]);
        }

        $numSchedulerWorkers = count(ResqueSchedulerWorker::all());
        $this->statsd->gauge('resque.active_scheduler_workers', $numSchedulerWorkers);
        $this->statsd->gauge('resque.num_delayed_jobs', ResqueScheduler::getDelayedQueueScheduleSize());
        $this->statsd->gauge('resque.num_delayed_jobs', (int) ResqueScheduler::nextDelayedTimestamp());

        $this->setCloudWatchCountMetric('Resque', 'QueueDepth', $queueSizes['normal'], [['Name' => 'Queue', 'Value' => 'normal']]);
        $this->setCloudWatchCountMetric('Resque', 'QueueDepth', $queueSizes['batch'], [['Name' => 'Queue', 'Value' => 'batch']]);
    }

    private function setCloudWatchCountMetric(string $namespace, string $name, int $value, array $dimensions = []): void
    {
        $dimensions[] = [
            'Name' => 'Environment',
            'Value' => $this->environment,
        ];

        try {
            $this->cwClient->putMetricData([
                'Namespace' => $namespace,
                'MetricData' => [
                    [
                        'MetricName' => $name,
                        'Dimensions' => $dimensions,
                        'Unit' => 'Count',
                        'Value' => $value,
                    ],
                ],
            ]);
        } catch (AwsException) {
            // do nothing
        }
    }

    private function accountingIntegrations(): void
    {
        foreach (self::$accountingIntegrations as $integration) {
            $this->statsd->gauge('accounting_sync.health_check', 1, 1.0, ['integration' => $integration]);
        }
    }

    private function tableSizes(): void
    {
        $tables = $this->database->fetchAllAssociative('SHOW TABLE STATUS');
        foreach ($tables as $table) {
            $this->statsd->gauge('database.table.rows', $table['Rows'], 1.0, [
                'table' => $table['Name'],
                'dbinstanceidentifier' => 'invoiced-production',
            ]);
            $this->statsd->gauge('database.table.data_length', $table['Data_length'], 1.0, [
                'table' => $table['Name'],
                'dbinstanceidentifier' => 'invoiced-production',
            ]);
            $this->statsd->gauge('database.table.index_length', $table['Index_length'], 1.0, [
                'table' => $table['Name'],
                'dbinstanceidentifier' => 'invoiced-production',
            ]);
        }
    }

    private function unreconciledCharges(): void
    {
        $date = CarbonImmutable::now()->subMinutes(10);
        $unreconciledCharges = $this->database->fetchOne('SELECT COUNT(*) FROM InitiatedCharges WHERE created_at <= "'.$date->toDateString().'"');
        $this->statsd->gauge('reconciliation.num_unreconciled', $unreconciledCharges);
    }

    private function awsSendQuota(): void
    {
        try {
            $quota = $this->sesClient->getSendQuota();
            $this->statsd->gauge('email.max_24h_send', $quota['Max24HourSend']);
            $this->statsd->gauge('email.sent_last_24h', $quota['SentLast24Hours']);
        } catch (AwsException) {
            // do nothing
        }
    }
}
