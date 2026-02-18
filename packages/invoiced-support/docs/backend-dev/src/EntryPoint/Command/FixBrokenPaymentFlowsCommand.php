<?php

namespace App\EntryPoint\Command;

use App\PaymentProcessing\Enums\PaymentFlowStatus;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixBrokenPaymentFlowsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:broken_payment_flows')
            ->setDescription('Fixes broken payment flows')
            ->addOption(
                'dry-run',
                '-d',
                InputOption::VALUE_NONE,
                'Do not execute the payment',
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $results = $this->connection->fetchAllAssociative("select payment_flow_id, status from Charges WHERE payment_flow_id IN (select id FROM PaymentFlows where status IN (1,3,4) AND completed_at)");

        $total = 0;
        foreach ($results as $result) {
            $status = null;
            if ($result['status'] === 'failed') {
                $status = PaymentFlowStatus::Failed->value;
            } elseif ($result['status'] === 'succeeded') {
                $status = PaymentFlowStatus::Succeeded->value;
            }

            if (!$status) {
                continue;
            }

            $total++;

            if ($input->getOption('dry-run')) {
                $output->writeln("{$result['payment_flow_id']} - $status");

                continue;
            }

            $this->connection->executeQuery("UPDATE PaymentFlows SET status = ? WHERE id = ?", [$status, $result['payment_flow_id']]);
        }

        $output->writeln("Total $total payment flows updated.");

        return 0;
    }
}
