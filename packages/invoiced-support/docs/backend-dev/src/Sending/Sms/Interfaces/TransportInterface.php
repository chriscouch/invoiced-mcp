<?php

namespace App\Sending\Sms\Interfaces;

use App\Companies\Models\Company;
use App\Sending\Sms\Exceptions\SendSmsException;

interface TransportInterface
{
    /**
     * Sends a text message through the transport implemented
     * by this class.
     *
     * @throws SendSmsException $e when the message cannot be sent
     *
     * @return array properties to set on the text message model
     */
    public function send(Company $company, string $to, string $message): array;
}
