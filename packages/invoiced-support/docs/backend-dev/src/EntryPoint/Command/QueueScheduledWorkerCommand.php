<?php

namespace App\EntryPoint\Command;

use App\Core\Queue\ResqueInitializer;
use App\Core\Queue\ResqueSchedulerWorker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

class QueueScheduledWorkerCommand extends Command
{
    const LOCK_TTL = 180; // seconds
    const SLEEP_INTERVAL = 1; // seconds

    public function __construct(private ResqueInitializer $resqueInitializer, private LockFactory $lockFactory, private string $environment)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('queue:work-delayed')
            ->setDescription('Starts a queue scheduled worker daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resqueInitializer->initialize();
        $this->startScheduler($output);

        return 0;
    }

    private function startScheduler(OutputInterface $output): void
    {
        $output->writeln('*** Starting scheduler worker');
        $verbose = 'dev' == $this->environment;
        $worker = new ResqueSchedulerWorker();
        $worker->work(self::SLEEP_INTERVAL, $this->lockFactory, self::LOCK_TTL, $verbose);
    }
}
