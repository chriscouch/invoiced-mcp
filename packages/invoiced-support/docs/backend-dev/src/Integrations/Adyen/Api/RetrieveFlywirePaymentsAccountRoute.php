<?php

namespace App\Integrations\Adyen\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\FlywirePaymentsOnboarding;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Operations\GetAccountInformation;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class RetrieveFlywirePaymentsAccountRoute extends AbstractApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly FlywirePaymentsOnboarding $onboarding,
        private readonly GetAccountInformation $accountInformation,
        private readonly AdyenClient $adyenClient
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $company = $this->tenant->get();

        $onboardingUrl = $this->onboarding->getOnboardingStartUrl($company);
        $adyenAccount = AdyenAccount::oneOrNull();
        if (!$adyenAccount) {
            throw new InvalidRequest('Account not found');
        }

        $merchantAccount = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->sort('id ASC')
            ->oneOrNull();

        try {
            $balanceAccount = $merchantAccount ? $this->accountInformation->balanceAccount($merchantAccount) : null;
            $accountHolder = $this->accountInformation->accountHolder($adyenAccount);
            $legalEntity = $this->accountInformation->legalEntity($adyenAccount);
        } catch (IntegrationApiException $e) {
            $this->logger->error('Could not retrieve Flywire Payments balance account, account holder, or legal entity', ['exception' => $e]);

            throw new InvalidRequest('We were unable to retrieve your account');
        }

        try {
            $withdrawal = $merchantAccount ? $this->accountInformation->sweep($merchantAccount) : null;
        } catch (IntegrationApiException $e) {
            $this->logger->error('Could not retrieve Flywire Payments sweeps', ['exception' => $e]);
            $withdrawal = null;
        }

        $state = $balanceAccount['status'] ?? null;

        $balances = array_map(function ($balance) {
            $current = new Money($balance['currency'], $balance['balance']);
            $available = new Money($balance['currency'], $balance['available']);
            $pending = new Money($balance['currency'], $balance['pending']);

            return [
                'currency' => $balance['currency'],
                'balance' => $current->toDecimal(),
                'available' => $available->toDecimal(),
                'pending' => $pending->toDecimal(),
            ];
        }, $balanceAccount['balances'] ?? []);

        if ($withdrawal) {
            $withdrawal['next_withdrawal'] = null;
            $withdrawal['outgoing_pending_amount'] = null;
        }

        // Collect all onboarding problems
        $problems = [];
        if ($accountHolder) {
            $alreadySeen = [];
            foreach ($accountHolder['capabilities'] as $capability) {
                if (!isset($capability['problems'])) {
                    continue;
                }

                foreach ($capability['problems'] as $problem) {
                    $entityId = $problem['entity']['id'];
                    $key = $entityId;

                    foreach ($problem['verificationErrors'] as $verificationError) {
                        $key2 = $key.$verificationError['code'];
                        if (isset($alreadySeen[$key2])) {
                            continue;
                        }
                        $alreadySeen[$key2] = true;
                        $verificationError['subErrors'] ??= [];

                        $problems[] = $verificationError;
                    }
                }
            }
        }

        // Collect all bank accounts
        $bankAccounts = [];
        if ($legalEntity) {
            foreach ($legalEntity['transferInstruments'] ?? [] as $transferInstrument) {
                $bankAccounts[] = [
                    'id' => $transferInstrument['id'],
                    'bank_name' => $this->adyenClient->getBankAccountName($transferInstrument['id']),
                ];
            }
        }

        // Update the has onboarding problem to the latest value
        $hasProblems = count($problems) > 0;
        if ($hasProblems != $adyenAccount->has_onboarding_problem) {
            $adyenAccount->has_onboarding_problem = $hasProblems;
            $adyenAccount->saveOrFail();
        }

        return [
            'state' => $state,
            'balances' => $balances,
            'withdrawal' => $withdrawal,
            'bank_accounts' => $bankAccounts,
            'statuses' => [
                'outgoing_payments_status' => $this->getCapabilityStatus($accountHolder, 'sendToTransferInstrument'),
                'receive_payments' => $this->getCapabilityStatus($accountHolder, 'receivePayments'),
            ],
            'disabled_reasons' => $problems,
            'update_uri' => $onboardingUrl,
            'statement_descriptor' => $adyenAccount->getStatementDescriptor(),
        ];
    }

    private function getCapabilityStatus(?array $accountHolder, string $capabilityName): string
    {
        if (!$accountHolder) {
            return 'disabled';
        }

        $capability = $accountHolder['capabilities'][$capabilityName];
        if ($capability['enabled'] && $capability['allowed']) {
            return 'enabled';
        }

        if ('pending' == $capability['verificationStatus']) {
            return 'pending';
        }

        return 'disabled';
    }
}
