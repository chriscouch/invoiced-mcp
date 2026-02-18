<?php

namespace App\EntryPoint\Command;

use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Ledger\ValueObjects\AccountingVendor;
use App\Core\Ledger\ValueObjects\Document;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixAdyenLedgerEntriesCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private MerchantAccountLedger $merchantAccountLedger,
        private Connection $database,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix-adyen-ledger-entries')
            ->setDescription('Fixes orphaned Adyen ledger entries.')
            ->addArgument(
                'clean-transactions',
                InputOption::VALUE_REQUIRED,
                'Report type to filter by, e.g. balanceplatform_accounting_report',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getArgument('clean-transactions')) {
            $this->database->executeQuery('DELETE FROM MerchantAccountTransactions WHERE amount = fee');
        }


        $accounts = MerchantAccount::queryWithoutMultitenancyUnsafe()
            ->where('deleted', false)
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->all()
            ->toArray();

        $n = 0;
        foreach ($accounts as $merchantAccount) {
            $company = $merchantAccount->tenant();

            // IMPORTANT: set the current tenant to enable multitenant operations
            $this->tenant->set($company);

            try {
                $ledger = $this->merchantAccountLedger->getLedger($merchantAccount);

                // Void any documents which reference a merchant account transaction that does not
                // exist due to inconsistent data.
                $documentIds = $this->database->fetchAllAssociative('SELECT t.name AS document_type,d.reference FROM Documents d JOIN DocumentTypes t ON t.id=d.document_type_id where ledger_id=? AND NOT EXISTS (SELECT 1 FROM MerchantAccountTransactions WHERE id=d.reference)', [$ledger->id]);

                foreach ($documentIds as $row) {
                    $document = new Document(
                        type: DocumentType::{$row['document_type']},
                        reference: $row['reference'],
                        party: new AccountingVendor(1), // does not matter
                        date: CarbonImmutable::now(), // does not matter
                    );
                    $ledger->voidDocument($document);
                    ++$n;
                }
            } catch (LedgerException $e) {
                $this->logger->error('Could not void invalid ledger entry', ['exception' => $e]);
            }
        }

        $output->writeln("Fixed $n orphaned ledger entries");

        return 0;
    }
}
