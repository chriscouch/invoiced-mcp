<?php

namespace App\Integrations\Services;

use App\Integrations\EarthClassMail\Models\EarthClassMailAccount;
use App\Integrations\Exceptions\IntegrationException;
use stdClass;

class EarthClassMail extends AbstractService
{
    private ?EarthClassMailAccount $account = null;

    public function isConnected(): bool
    {
        return $this->account || EarthClassMailAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!$account) {
            return null;
        }

        return (string) $account->inbox_id;
    }

    public function getExtra(): stdClass
    {
        $account = $this->getAccount();

        return (object) [
            'account_id' => $account ? $account->id() : null,
            'inbox_id' => $account ? $account->inbox_id : null,
            'last_retrieved_data_at' => $account ? $account->last_retrieved_data_at : null,
        ];
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('Earth Class Mail account is not connected');
        }

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove Earth Class Mail account');
        }
        $this->account = null;
    }

    /**
     * Gets the connected EarthClassMail account.
     */
    public function getAccount(): ?EarthClassMailAccount
    {
        if (!$this->accountLoaded) {
            $this->account = EarthClassMailAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }
}
