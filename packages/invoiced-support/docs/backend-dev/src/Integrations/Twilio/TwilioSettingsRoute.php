<?php

namespace App\Integrations\Twilio;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\Twilio;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class TwilioSettingsRoute extends AbstractApiRoute
{
    public function __construct(
        private IntegrationFactory $integrations,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Twilio $integration */
        $integration = $this->integrations->get(IntegrationType::Twilio, $this->tenant->get());
        if (!$integration->isConnected()) {
            throw new InvalidRequest('Twilio account is not connected', 404);
        }

        // refresh the twilio access token before starting
        /** @var TwilioAccount $twilioAccount */
        $twilioAccount = $integration->getAccount();
        $client = $this->getClient($twilioAccount);

        try {
            $incomingPhoneNumbers = $client->incomingPhoneNumbers->read();
        } catch (TwilioException $e) {
            throw new InvalidRequest('We had trouble connecting to Twilio: '.$e->getMessage());
        }

        $phoneNumbers = [];
        foreach ($incomingPhoneNumbers as $record) {
            $phoneNumbers[] = [
                'number' => $record->phoneNumber,
                'name' => $record->friendlyName,
            ];
        }

        return [
            'phone_numbers' => $phoneNumbers,
        ];
    }

    /**
     * Builds the Twilio client.
     *
     * @throws ConfigurationException
     */
    private function getClient(TwilioAccount $twilioAccount): Client
    {
        return new Client($twilioAccount->account_sid, $twilioAccount->auth_token);
    }
}
