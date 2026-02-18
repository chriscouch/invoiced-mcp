<?php

namespace App\EntryPoint\Command;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnqueueJob extends Command
{
    public function __construct(private Queue $queue)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('queue:job')
            ->setDescription('Enqueues a job to resque')
            ->addArgument(
                'queue',
                InputArgument::REQUIRED,
                'Job queue'
            )
            ->addArgument(
                'class',
                InputArgument::REQUIRED,
                'Job class'
            )
            ->addArgument(
                'args',
                InputArgument::REQUIRED,
                'JSON-encoded job arguments'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = QueueServiceLevel::from($input->getArgument('queue'));
        $class = $input->getArgument('class');
        $args = $input->getArgument('args');

        if (!class_exists($class)) {
            $output->writeln("Class '$class' does not exist");

            return 1;
        }

        $args = json_decode($args, true);
        if (json_last_error()) {
            $output->writeln('Could not parse job args. Must be valid JSON: '.json_last_error_msg());

            return 1;
        }

        $this->queue->enqueue($class, $args, $queue);

        $output->writeln("Job added to '$queue->value' queue!");

        return 0;
    }
}
