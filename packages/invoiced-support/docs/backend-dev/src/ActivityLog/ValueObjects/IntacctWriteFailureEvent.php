<?php

namespace App\ActivityLog\ValueObjects;

use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Models\IntacctAccount;
use Symfony\Contracts\EventDispatcher\Event;

class IntacctWriteFailureEvent extends Event
{
    public function __construct(private IntacctAccount $account, private IntegrationApiException $exception)
    {
    }

    public function getAccount(): IntacctAccount
    {
        return $this->account;
    }

    public function getException(): IntegrationApiException
    {
        return $this->exception;
    }
}
