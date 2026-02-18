<?php

namespace App\EntryPoint\Command;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Operations\EnableAdyenPayouts;
use App\Integrations\Exceptions\IntegrationApiException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnableAdyenPayoutsCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private AdyenClient $adyenClient,
        private TenantContext $tenant,
        private EnableAdyenPayouts $enableAdyenPayouts,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('enable-adyen-payouts')
            ->setDescription('Ensures that all accounts have correctly configured Adyen payouts and negative balance collection.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accounts = AdyenAccount::queryWithoutMultitenancyUnsafe()
            ->where('account_holder_id', null, '<>')
            ->all()
            ->toArray();

        foreach ($accounts as $adyenAccount) {
            $company = $adyenAccount->tenant();

            // IMPORTANT: set the current tenant to enable multitenant operations
            $this->tenant->set($company);

            try {
                $accountHolderId = (string) $adyenAccount->account_holder_id;
                $accountHolder = $this->adyenClient->getAccountHolder($accountHolderId);

                // Request the receiveFromTransferInstrument capability if not already requested
                if (!isset($accountHolder['capabilities']['receiveFromTransferInstrument'])) {
                    $this->adyenClient->updateAccountHolder($accountHolderId, [
                        'capabilities' => [
                            'receiveFromTransferInstrument' => [
                                'requested' => true,
                                'requestedLevel' => 'notApplicable',
                            ],
                        ],
                    ]);
                }

                // Enable daily payouts
                if ($this->hasCapability($accountHolder, 'sendToTransferInstrument')) {
                    $this->enableAdyenPayouts->enableDailyPayouts($adyenAccount);
                }
            } catch (IntegrationApiException $e) {
                $this->logger->error('Could not enable Adyen payouts', ['exception' => $e]);
            }
        }

        return 0;
    }

    private function hasCapability(array $accountHolder, string $capability): bool
    {
        $capability = $accountHolder['capabilities'][$capability] ?? ['enabled' => false, 'allowed' => false];

        return $capability['enabled'] && $capability['allowed'];
    }
}
