<?php

namespace App\EntryPoint\Command;

use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Libs\CreditBalanceHistory;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Models\PaymentMethod;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreditBalanceCommand extends Command
{
    public function __construct(private Connection $database, private TenantContext $tenant)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('audit:credit-balance')
            ->setDescription('Calculates the credit balance for a customer')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Tenant ID that owns the customer'
            )
            ->addArgument(
                'customer',
                InputArgument::OPTIONAL,
                'Customer ID to calculate balance for'
            )
            ->addOption(
                'save',
                null,
                InputOption::VALUE_NONE,
                'Writes the calculated balance history'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $companyId = $input->getArgument('company');
        $customerId = $input->getArgument('customer');
        $save = $input->getOption('save');

        if ('all' === $companyId) {
            foreach (AccountsReceivableSettings::queryWithoutMultitenancyUnsafe()->all() as $setting) {
                $company = $setting->tenant();
                if (!$company->canceled) {
                    $this->runFor($company, $customerId, $output, $save);
                }
            }
        } else {
            $companyIds = explode(',', $companyId);
            foreach ($companyIds as $companyId2) {
                $company = Company::findOrFail($companyId2);
                $this->runFor($company, $customerId, $output, $save);
            }
        }

        return 0;
    }

    private function runFor(Company $company, string $customerId, OutputInterface $output, bool $save): void
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $rows = [];
        $saved = 0;
        if ('all' === $customerId) {
            $customerIds = $this->getCustomerIds();
            foreach ($customerIds as $row) {
                $customer = Customer::findOrFail($row['customer']);

                $this->processCustomer($customer, $row['currency'], $save, $rows, $saved);
            }
        } elseif ($customerId) {
            $customerIds = explode(',', $customerId);
            foreach ($customerIds as $customerId2) {
                $customer = Customer::find($customerId2);
                if (!$customer) {
                    $output->writeln("Customer # $customerId2 not found");

                    return;
                }

                $currencies = $this->getCustomerCurrencies((int) $customerId2);

                foreach ($currencies as $currency) {
                    $this->processCustomer($customer, $currency, $save, $rows, $saved);
                }
            }
        }

        // output a table
        if ($rows) {
            $table = new Table($output);
            $table->setHeaders(['Customer ID', 'Date', 'Transaction ID', 'Currency', 'Balance'])
                ->setRows($rows)
                ->render();
        }

        if ($save) {
            $output->writeln("-- Saved $saved balance entries for company # {$company->id}");
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();
    }

    private function getCustomerIds(): array
    {
        $query = 'SELECT customer,currency FROM Transactions WHERE tenant_id=:tenantId AND method=:method AND type IN (:type1,:type2) GROUP BY customer,currency ORDER BY customer ASC';

        return $this->database->fetchAllAssociative($query, [
            'tenantId' => $this->tenant->get()->id(),
            'method' => PaymentMethod::BALANCE,
            'type1' => Transaction::TYPE_ADJUSTMENT,
            'type2' => Transaction::TYPE_CHARGE,
        ]);
    }

    private function getCustomerCurrencies(int $customerId): array
    {
        $query = 'SELECT currency FROM Transactions WHERE method=:method AND type IN (:type1,:type2) AND customer=:customerId GROUP BY currency';

        return $this->database->fetchFirstColumn($query, [
            'method' => PaymentMethod::BALANCE,
            'type1' => Transaction::TYPE_ADJUSTMENT,
            'type2' => Transaction::TYPE_CHARGE,
            'customerId' => $customerId,
        ]);
    }

    private function processCustomer(Customer $customer, string $currency, bool $save, array &$rows, int &$saved): void
    {
        $history = new CreditBalanceHistory($customer, $currency);

        if ($save) {
            if (!$history->persist()) {
                throw new Exception("Could not write credit balance history for customer # {$customer->id()}!");
            }
        }

        $rows2 = [];
        $balances = $history->getBalances();
        foreach ($balances as $balance) {
            $rows2[] = [
                $customer->id(),
                date('M j, Y g:i:s a', $balance->timestamp),
                $balance->id(),
                $balance->currency,
                $balance->balance,
            ];
        }

        $saved += $save ? count($rows2) : 0;
        $rows = array_merge($rows, $rows2);
    }
}
