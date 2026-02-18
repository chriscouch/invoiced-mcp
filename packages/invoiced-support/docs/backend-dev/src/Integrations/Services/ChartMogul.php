<?php

namespace App\Integrations\Services;

use App\Integrations\ChartMogul\Models\ChartMogulAccount;
use App\Integrations\Exceptions\IntegrationException;
use stdClass;

class ChartMogul extends AbstractService
{
    private ?ChartMogulAccount $account = null;

    public function isConnected(): bool
    {
        return $this->account || ChartMogulAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!$account) {
            return null;
        }

        return $account->token;
    }

    public function getExtra(): stdClass
    {
        $account = $this->getAccount();

        return $account ? (object) $account->toArray() : (object) [];
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('ChartMogul account is not connected');
        }

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove ChartMogul account');
        }

        $this->account = null;
        $this->accountLoaded = true;
    }

    /**
     * Gets the connected ChartMogul account.
     */
    public function getAccount(): ?ChartMogulAccount
    {
        if (!$this->accountLoaded) {
            $this->account = ChartMogulAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }
}
