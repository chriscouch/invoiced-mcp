<?php

namespace App\EntryPoint\Command;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\BankAccount;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AdyenTokenizeBankAccountsCommand extends Command
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AdyenGateway $adyen,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('adyen:tokenize-bank-accounts')
            ->setDescription('Tokenize Adyen bank accounts for Adyen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var BankAccount[] $accounts */
        $accounts = BankAccount::queryWithoutMultitenancyUnsafe()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id IS NULL')
            ->where('chargeable = 1')
            ->all();

        foreach ($accounts as $account) {
            $this->tenant->set($account->tenant());

            try {
                $result = $this->adyen->vaultSource($account->customer, $account->getMerchantAccount(), [
                    'payment_method' => 'ach',
                    'account_number' => $account->account_number,
                    'routing_number' => $account->routing_number,
                    'account_holder_name' => $account->account_holder_name,
                    'account_holder_type' => $account->account_holder_type,
                ]);


                $account->gateway_id = $result->gatewayId;
                $account->gateway_customer = $result->gatewayCustomer;
                $account->save();
            } catch (IntegrationApiException $e) {
                $output->writeln( $e->getMessage());
            }
        }


        return 0;
    }
}
