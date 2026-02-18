<?php

namespace App\EntryPoint\Command;

use App\Core\Cron\Libs\JobSchedule;
use App\Core\Utils\DebugContext;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CronRunCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private JobSchedule $jobSchedule, private DebugContext $debugContext)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cron:run')
            ->setDescription('Runs any scheduled jobs')
            ->addArgument('job_id', InputArgument::OPTIONAL, 'specify a job id to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->comment('Correlation ID: '.$this->debugContext->getCorrelationId());

        if ($jobId = $input->getArgument('job_id')) {
            return $this->jobSchedule->runSingleJob($jobId, $io) ? 0 : 1;
        }

        return $this->jobSchedule->runScheduled($io) ? 0 : 1;
    }
}
