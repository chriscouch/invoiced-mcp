<?php

namespace App\EntryPoint\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixResetDisputesCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly Connection $database,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:reset_disputes')
            ->setDescription('Resets Disputes to initial state.')
            ->addArgument(
                'dispute_id',
                InputArgument::REQUIRED,
                'Comma separated dispute ids',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $disputes = explode(",", $input->getArgument('dispute_id'));
        $count = $this->database->executeQuery('UPDATE Disputes Set status = 7, defense_reason = NULL WHERE id IN (?) ', [
            $disputes,
        ], [ArrayParameterType::INTEGER]);

        $output->writeln('Disputes successfully reset: ' . $count->rowCount());

        return 0;
    }
}
