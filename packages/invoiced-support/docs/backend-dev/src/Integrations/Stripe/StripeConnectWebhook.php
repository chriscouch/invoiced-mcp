<?php

namespace App\Integrations\Stripe;

use App\Companies\Models\Company;
use App\Integrations\Interfaces\WebhookHandlerInterface;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * This webhook is for interfacing with events from the Stripe Connect product.
 */
class StripeConnectWebhook implements WebhookHandlerInterface
{
    private const HANDLERS = [
        'setup_intent.succeeded' => 'handleSetupIntentSucceeded',
    ];

    public function __construct(
        private readonly StripeGateway $stripe,
    ) {
    }

    //
    // WebhookHandlerInterface
    //

    public function shouldProcess(array &$event): bool
    {
        return true;
    }

    public function getCompanies(array $event): array
    {
        $merchantAccounts = MerchantAccount::queryWithoutMultitenancyUnsafe()
            ->where('deleted', false)
            ->where('gateway', 'stripe')
            ->where('gateway_id', $event['account'])
            ->first(100);
        $companies = [];
        foreach ($merchantAccounts as $merchantAccount) {
            if (!isset($companies[$merchantAccount->tenant_id])) {
                $companies[$merchantAccount->tenant_id] = $merchantAccount->tenant();
            }
        }

        return $companies;
    }

    public function process(Company $company, array $event): void
    {
        $company->useTimezone();

        $handler = self::HANDLERS[$event['type']] ?? null;
        if ($handler) {
            call_user_func([$this, $handler], $company, $event);
        }
    }

    public function handleSetupIntentSucceeded(Company $company, array $event): void
    {
        $setupIntent = $event['data']['object'];
        $bankAccount = BankAccount::where('gateway', 'stripe')
            ->where('gateway_setup_intent', $setupIntent['id'])
            ->oneOrNull();
        if ($bankAccount) {
            $this->stripe->markBankAccountVerified($bankAccount);
        }
    }
}
