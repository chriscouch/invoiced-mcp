<?php

namespace App\Sending\Sms\Transport;

use App\Companies\Models\Company;
use App\Integrations\Twilio\TwilioAccount;
use App\Sending\Sms\Exceptions\SendSmsException;
use App\Sending\Sms\Interfaces\TransportInterface;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Sends text messages via Twilio API.
 */
class TwilioTransport implements TransportInterface
{
    public function send(Company $company, string $to, string $message): array
    {
        $twilioAccount = TwilioAccount::find($company->id());
        if (!$twilioAccount) {
            throw new SendSmsException('You must first configure Twilio in Settings > Integrations to send text messages.');
        }

        $from = $twilioAccount->from_number;
        if (!$from) {
            throw new SendSmsException('You must select a from phone number in Settings > Integrations > Twilio before sending text messages.');
        }

        try {
            $twilio = $this->getClient($twilioAccount);
        } catch (ConfigurationException $e) {
            throw new SendSmsException($e->getMessage(), 0, $e);
        }

        try {
            $twilioMessage = $twilio->messages->create(
                $to,
                [
                    'from' => $from,
                    'body' => $message,
                ]
            );
        } catch (TwilioException $e) {
            throw new SendSmsException($e->getMessage(), 0, $e);
        }

        return [
            'state' => 'sent',
            'twilio_id' => $twilioMessage->sid,
        ];
    }

    /**
     * Builds the Twilio client.
     *
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    private function getClient(TwilioAccount $twilioAccount): Client
    {
        return new Client($twilioAccount->account_sid, $twilioAccount->auth_token);
    }
}
