<?php

namespace App\Integrations\Services;

use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Twilio\TwilioAccount;
use stdClass;

class Twilio extends AbstractService
{
    private ?TwilioAccount $account = null;

    public function isConnected(): bool
    {
        return $this->account || TwilioAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!$account) {
            return null;
        }

        return $account->account_sid;
    }

    public function getExtra(): stdClass
    {
        $extra = new stdClass();
        $account = $this->getAccount();
        $extra->from_number = $account ? $account->from_number : null;

        return $extra;
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('Twilio account is not connected');
        }

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove Twilio account');
        }
        $this->account = null;
    }

    /**
     * Gets the connected Twilio account.
     */
    public function getAccount(): ?TwilioAccount
    {
        if (!$this->accountLoaded) {
            $this->account = TwilioAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }
}
