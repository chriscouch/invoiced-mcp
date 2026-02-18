<?php

namespace App\Integrations\Services;

use App\Integrations\Avalara\AvalaraAccount;
use App\Integrations\Exceptions\IntegrationException;

class Avalara extends AbstractService
{
    private ?AvalaraAccount $account = null;

    public function isConnected(): bool
    {
        return $this->account || AvalaraAccount::queryWithTenant($this->company)->count() > 0;
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
        $account = $this->getAccount();

        return (object) [
            'company_code' => $account?->company_code,
            'commit_mode' => $account?->commit_mode,
        ];
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('Avalara account is not connected');
        }

        // remove the Avalara account
        if (!$account->delete()) {
            throw new IntegrationException('Could not remove Avalara account');
        }
        $this->account = null;

        // disable Avalara as the tax calculator
        $this->company->accounts_receivable_settings->tax_calculator = 'invoiced';
        $this->company->accounts_receivable_settings->save();
    }

    /**
     * Gets the connected Avalara account.
     */
    public function getAccount(): ?AvalaraAccount
    {
        if (!$this->accountLoaded) {
            $this->account = AvalaraAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }
}
