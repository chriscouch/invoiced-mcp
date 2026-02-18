<?php

namespace App\EntryPoint\Command;

use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\PaymentProcessing\Gateways\AdyenGateway;

class FixPaymentsStuckInPendingStatusCommand extends Command
{
    public function __construct(
        private readonly Connection $database,
        private readonly TenantContext $tenant,
        private UpdateChargeStatus $updateChargeStatus,
    ) {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this
            ->setName('fix:missing_payments_stuck_in_pending_status')
            ->setDescription('Fixes the status of the payments stuck in pending status.')
            ->addArgument(
                'start_date',
                InputArgument::OPTIONAL,
                'Date to start query',
                CarbonImmutable::now()->subDays(7)->startOfDay()->toIso8601String()
            )
            ->addArgument(
                'end_date',
                InputArgument::OPTIONAL,
                'Date to end query',
                CarbonImmutable::now()->endOfDay()->toIso8601String()
            )
            ->addArgument(
                'dry_run',
                InputArgument::OPTIONAL,
                'Show what would be updated without making changes (true/false)',
                'true'
            )
            ->addArgument(
                'batch_size',
                InputArgument::OPTIONAL,
                'Number of transactions to process per batch',
                '1000'
            );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startDate = $input->getArgument('start_date');
        $endDate = $input->getArgument('end_date');
        $batchSize = (int) $input->getArgument('batch_size');
        $dryRun = strtolower($input->getArgument('dry_run')) === 'true';

        $output->writeln("Starting payment status fix process...");
        $output->writeln("Date range: {$startDate} to {$endDate}");
        $output->writeln("Batch size: {$batchSize}");
        $output->writeln("Dry run: " . ($dryRun ? 'YES (no changes will be made)' : 'NO (charges will be updated)'));

        $totalCount = $this->getTotalTransactionCount($startDate, $endDate);
        $output->writeln("Total transactions to process: {$totalCount}");

        if ($totalCount === 0) {
            $output->writeln("No transactions found matching criteria.");
            return 0;
        }

        $processed = 0;
        $updated = 0;
        $errors = 0;
        $offset = 0;

        while ($offset < $totalCount) {
            $output->writeln("Processing batch: " . ($offset + 1) . "-" . min($offset + $batchSize, $totalCount) . " of {$totalCount}");

            $transactions = $this->getTransactionsBatch($startDate, $endDate, $batchSize, $offset);

            foreach ($transactions as $transaction) {
                try {
                    $result = $this->processTransaction($transaction, $output, $dryRun);
                    if ($result) {
                        $updated++;
                    }
                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    $output->writeln("<error>Error processing transaction {$transaction['id']}: " . $e->getMessage() . "</error>");
                }

                // Clear memory after each transaction
                gc_collect_cycles();
            }

            $offset += $batchSize;

            // Progress update
            $output->writeln("Progress: {$processed}/{$totalCount} processed, {$updated} updated, {$errors} errors");

            // Small delay to prevent overwhelming the system
            usleep(100000); // 0.1 seconds
        }

        $output->writeln("Process completed!");
        $output->writeln("Total processed: {$processed}");
        $output->writeln("Total updated: {$updated}");
        $output->writeln("Total errors: {$errors}");

        return 0;
    }

    private function getTotalTransactionCount(string $startDate, string $endDate): int
    {
        $sql = "SELECT COUNT(*) as count
                FROM Transactions as t
                JOIN MerchantAccountTransactions as m ON t.gateway_id = m.reference
                WHERE t.method IN ('credit_card', 'balance')
                    AND t.status = 'pending'
                    AND t.gateway = 'flywire_payments'
                    AND t.created_at >= ?
                    AND t.created_at <= ?";

        return (int) $this->database->executeQuery($sql, [$startDate, $endDate])->fetchOne();
    }

    private function getTransactionsBatch(string $startDate, string $endDate, int $limit, int $offset): array
    {
        $sql = "SELECT t.id,
                       t.tenant_id,
                       t.customer,
                       t.type,
                       t.invoice,
                       t.parent_transaction,
                       t.payment_id,
                       t.status,
                       t.created_at,
                       t.gateway_id
                FROM Transactions as t
                JOIN MerchantAccountTransactions as m ON t.gateway_id = m.reference
                WHERE t.method IN ('credit_card', 'balance')
                    AND t.status = 'pending'
                    AND t.gateway = 'flywire_payments'
                    AND t.created_at >= ?
                    AND t.created_at <= ?
                ORDER BY t.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        return $this->database->executeQuery($sql, [$startDate, $endDate])->fetchAllAssociative();
    }

    private function processTransaction(array $transaction, OutputInterface $output, bool $dryRun): bool
    {
        $charge = Charge::queryWithoutMultitenancyUnsafe()
            ->where('gateway_id', $transaction['gateway_id'])
            ->where('gateway', AdyenGateway::ID)
            ->oneOrNull();

        if (!$charge) {
            $output->writeln("<comment>Charge not found for transaction {$transaction['id']}</comment>");
            return false;
        }

        $company = $charge->tenant();
        $this->tenant->set($company);

        if ($charge->gateway === AdyenGateway::ID) {
            try {
                if ($dryRun) {
                    $output->writeln("<info>[DRY RUN] Would update charge {$charge->gateway_id} (transaction: {$transaction['id']}) from '{$charge->status}' to 'succeeded'</info>");
                    return true;
                } else {
                    $this->updateChargeStatus->saveStatus($charge, Charge::SUCCEEDED);
                    $output->writeln("<info>Transaction status updated for transaction: {$transaction['id']}</info>");
                    return true;
                }
            } catch (ChargeException|FormException|TransactionStatusException $e) {
                throw new \Exception("Failed to update transaction status for transaction: {$transaction['id']} - " . $e->getMessage());
            }
        }

        $this->tenant->clear();

        return false;
    }
}
