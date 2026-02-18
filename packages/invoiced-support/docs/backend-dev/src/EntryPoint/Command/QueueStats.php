<?php

namespace App\EntryPoint\Command;

use App\Core\Queue\ResqueHelper;
use App\Core\Queue\ResqueInitializer;
use App\Core\Queue\ResqueSchedulerWorker;
use Carbon\CarbonImmutable;
use Resque;
use Resque_Stat;
use Resque_Worker;
use ResqueScheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class QueueStats extends Command
{
    public function __construct(private ResqueInitializer $resqueInitializer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('queue:stats')
            ->setDescription('Prints out Resque stats');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resqueInitializer->initialize();

        $queues = Resque::queues();
        sort($queues);
        $workers = Resque_Worker::all();
        sort($workers);
        $schedulerWorkers = ResqueSchedulerWorker::all();
        sort($schedulerWorkers);

        $io = new SymfonyStyle($input, $output);

        $this->printResqueStats($output, $io);
        $this->printQueueStats($output, $io, $queues);
        $this->printWorkerStats($output, $io, $workers);
        $this->printSchedulerWorkerStats($output, $io, $schedulerWorkers);

        return 0;
    }

    //
    // Output
    //

    private function printResqueStats(OutputInterface $output, SymfonyStyle $io): void
    {
        $io->title('Resque statistics');
        $io->section('Jobs Stats');

        $output->writeln('  '.sprintf('Processed Jobs : %s', number_format(Resque_Stat::get('processed'))));

        $failed = Resque_Stat::get('failed');
        if ($failed > 0) {
            $output->writeln('  <error>'.sprintf('Failed Jobs    : %s</error>', number_format($failed)));
        } else {
            $output->writeln('  '.sprintf('Failed Jobs    : %s', number_format($failed)));
        }

        $output->writeln('  '.sprintf('Scheduled Jobs : %s', number_format(ResqueScheduler::getDelayedQueueScheduleSize())));

        $output->writeln('');
    }

    private function printQueueStats(OutputInterface $output, SymfonyStyle $io, array $queues): void
    {
        $io->section('Queues Stats');

        $output->writeln('  '.sprintf('Queues : %d', count($queues)));
        $output->writeln('');

        foreach ($queues as $queue) {
            $pendingJobs = Resque::size($queue);
            $output->writeln(sprintf('  Queue : <options=bold>%s</>', $queue));
            $output->writeln(sprintf('   - %s pending jobs', number_format($pendingJobs)));
            $output->writeln('');
        }
    }

    /**
     * @param Resque_Worker[] $workers
     */
    private function printWorkerStats(OutputInterface $output, SymfonyStyle $io, array $workers): void
    {
        $io->section('Workers Stats');
        $output->writeln('  Active Workers : '.count($workers));

        foreach ($workers as $worker) {
            $this->printWorkerSummary($output, $worker);
            $output->writeln('   - Processed Jobs : '.$worker->getStat('processed'));
            0 == $worker->getStat('failed')
                ? $output->writeln('   - Failed Jobs    : '.$worker->getStat('failed'))
                : $output->writeln('   <error>- Failed Jobs    : '.$worker->getStat('failed').'</error>');
        }

        $output->writeln('');
    }

    /**
     * @param ResqueSchedulerWorker[] $workers
     */
    private function printSchedulerWorkerStats(OutputInterface $output, SymfonyStyle $io, array $workers): void
    {
        $io->section('Scheduler Workers Stats');
        $output->writeln('  Active Scheduler Workers : '.count($workers));

        if (count($workers) > 0) {
            foreach ($workers as $worker) {
                $this->printWorkerSummary($output, $worker);
                $delayedJobCount = ResqueScheduler::getDelayedQueueScheduleSize();
                $output->writeln('   - Delayed Jobs   : '.number_format($delayedJobCount));

                $next = ResqueScheduler::nextDelayedTimestamp();
                if ($next) {
                    $output->writeln('   - Next Job on    : '.date('D M d H:i:s T Y', $next));
                }
            }
            $output->writeln('');
        } else {
            $io->error('The Scheduler Worker is not running');
        }

        $output->writeln('');
    }

    private function printWorkerSummary(OutputInterface $output, string $worker): void
    {
        $output->writeln('');
        $output->writeln('  Worker : <options=bold>'.$worker.'</>');
        $output->writeln('');
        $startDate = ResqueHelper::getWorkerStartDate($worker);
        $output->writeln(
            '   - Started on     : '.$startDate
        );
        $output->writeln(
            '   - Uptime         : '.
            $this->formatDateDiff(new CarbonImmutable($startDate))
        );
    }

    //
    // Helpers
    //

    /**
     * A sweet interval formatting, will use the two biggest interval parts.
     * On small intervals, you get minutes and seconds.
     * On big intervals, you get months and days.
     * Only the two biggest parts are used.
     *
     * @param CarbonImmutable $start
     *
     * @codeCoverageIgnore
     *
     * @see http://www.php.net/manual/en/dateinterval.format.php
     */
    public function formatDateDiff($start): string
    {
        if (!($start instanceof CarbonImmutable)) {
            $start = new CarbonImmutable($start);
        }
        $end = new CarbonImmutable();
        $interval = $end->diff($start);
        $doPlural = fn ($nb, $str) => $nb > 1 ? $str.'s' : $str;
        $format = [];
        if (0 !== $interval->y) {
            $format[] = '%y '.$doPlural($interval->y, 'year');
        }
        if (0 !== $interval->m) {
            $format[] = '%m '.$doPlural($interval->m, 'month');
        }
        if (0 !== $interval->d) {
            $format[] = '%d '.$doPlural($interval->d, 'day');
        }
        if (0 !== $interval->h) {
            $format[] = '%h '.$doPlural($interval->h, 'hour');
        }
        if (0 !== $interval->i) {
            $format[] = '%i '.$doPlural($interval->i, 'minute');
        }
        if (0 !== $interval->s) {
            if (!count($format)) {
                return 'less than a minute';
            }
            $format[] = '%s '.$doPlural($interval->s, 'second');
        }
        // We use the two biggest parts
        if (count($format) > 1) {
            $format = array_shift($format).' and '.array_shift($format);
        } else {
            $format = array_pop($format);
        }

        // Prepend 'since ' or whatever you like
        return $interval->format((string) $format);
    }
}
