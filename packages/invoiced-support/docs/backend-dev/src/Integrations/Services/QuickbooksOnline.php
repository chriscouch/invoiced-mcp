<?php

namespace App\Integrations\Services;

use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;

class QuickbooksOnline extends AbstractService
{
    private ?QuickBooksAccount $account = null;

    public function isAccountingIntegration(): bool
    {
        return true;
    }

    public function isConnected(): bool
    {
        return $this->account || QuickBooksAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!$account) {
            return null;
        }

        return $account->name;
    }

    public function getExtra(): \stdClass
    {
        $usesPayments = MerchantAccount::withoutDeleted()
            ->where('gateway', 'intuit')
            ->count() > 0 &&
            PaymentMethod::where('gateway', 'intuit')
                ->where('enabled', true)
                ->count() > 0;

        $syncProfile = AccountingSyncModelFactory::getSyncProfile(IntegrationType::QuickBooksOnline, $this->company);

        return (object) [
            'uses_payments' => $usesPayments,
            'sync_profile' => $syncProfile ? $syncProfile->toArray() : null,
        ];
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('QuickBooks account is not connected');
        }

        // disable any associated payment methods
        foreach (PaymentMethod::where('gateway', 'intuit')->all() as $method) {
            $method->gateway = null;
            $method->enabled = false;
            $method->merchant_account = null;
            $method->save();
        }

        $token = $account->refresh_token;
        // remove the account from our database
        if (!$account->delete()) {
            throw new IntegrationException('Could not remove QuickBooks account');
        }

        $this->account = null;
    }

    /**
     * Gets the connected QBO account.
     */
    public function getAccount(): ?QuickBooksAccount
    {
        if (!$this->accountLoaded) {
            $this->account = QuickBooksAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }
}
