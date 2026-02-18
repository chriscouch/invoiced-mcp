<?php

namespace App\EntryPoint\Command;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Models\AdyenAccount;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateAdyenAccountCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private AdyenClient $adyenClient,
        private TenantContext $tenant,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('update-adyen-account-holder')
            ->setDescription('Allows for changing the Adyen account holder ID to a different one')
            ->addArgument(
                'currentAccountHolderId',
                InputArgument::REQUIRED,
                'Current account holder ID in our database'
            )
            ->addArgument(
                'newAccountHolderId',
                InputArgument::REQUIRED,
                'New account holder ID in our database'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $currentAccountHolderId = $input->getArgument('currentAccountHolderId');
        $newAccountHolderId = $input->getArgument('newAccountHolderId');
        $adyenAccount = AdyenAccount::queryWithoutMultitenancyUnsafe()
            ->where('account_holder_id', $currentAccountHolderId)
            ->one();

        $company = $adyenAccount->tenant();

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $accountHolder = $this->adyenClient->getAccountHolder($newAccountHolderId);

        $businessLines = $this->adyenClient->getBusinessLines($accountHolder['legalEntityId']);

        // Update our database values
        $adyenAccount->account_holder_id = $newAccountHolderId;
        $adyenAccount->legal_entity_id = $accountHolder['legalEntityId'];
        $adyenAccount->reference = $accountHolder['reference'];
        if (count($businessLines['businessLines']) > 0) {
            $adyenAccount->business_line_id = $businessLines['businessLines'][0]['id'];
            $adyenAccount->industry_code = $businessLines['businessLines'][0]['industryCode'];
        }
        $adyenAccount->saveOrFail();

        return 0;
    }
}
