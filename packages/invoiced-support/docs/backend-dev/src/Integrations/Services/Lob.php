<?php

namespace App\Integrations\Services;

use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Lob\LobAccount;
use stdClass;

/**
 * Lob integration.
 */
class Lob extends AbstractService
{
    private ?LobAccount $account = null;

    public function isConnected(): bool
    {
        return $this->account || LobAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!$account) {
            return null;
        }

        return 'Lob account';
    }

    public function getExtra(): stdClass
    {
        $extra = new stdClass();
        $account = $this->getAccount();
        $extra->return_envelopes = $account ? $account->return_envelopes : null;
        $extra->use_color = $account ? $account->use_color : null;
        $extra->custom_envelope = $account ? $account->custom_envelope : null;

        return $extra;
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('Lob account is not connected');
        }

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove Lob account');
        }
        $this->account = null;
    }

    /**
     * Gets the connected Lob account.
     */
    public function getAccount(): ?LobAccount
    {
        if (!$this->accountLoaded) {
            $this->account = LobAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }
}
