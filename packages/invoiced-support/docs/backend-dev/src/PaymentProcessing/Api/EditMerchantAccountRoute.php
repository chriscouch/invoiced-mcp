<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * @extends AbstractEditModelApiRoute<MerchantAccount>
 */
class EditMerchantAccountRoute extends AbstractEditModelApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AdyenClient $adyen,
        private readonly bool $adyenLiveMode,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters:[
                'name' => new RequestParameter(),
                'gateway' => new RequestParameter(),
                'gateway_id' => new RequestParameter(),
                'credentials' => new RequestParameter(),
                'settings' => new RequestParameter(),
                'enable_klarna' => new RequestParameter(),
                'enable_affirm' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: MerchantAccount::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $merchantAccount = $this->retrieveModel($context);

        if (AdyenGateway::ID == $merchantAccount->gateway) {
            if ($balanceAccountId = $merchantAccount->toGatewayConfiguration()->credentials->balance_account ?? '') {
                try {
                    // When this is tied to an Adyen balance account then update the description of the balance account
                    $this->adyen->updateBalanceAccount($balanceAccountId, [
                        'description' => $merchantAccount->name,
                    ]);
                } catch (IntegrationApiException) {
                    // ignore exceptions
                }
            }

            $context = $this->addAffirmKlarna($context, $merchantAccount);
        }

        return parent::buildResponse($context);
    }

    private function addAffirmKlarna(ApiCallContext $context, MerchantAccount $merchantAccount): ApiCallContext
    {
        $tenant = $merchantAccount->tenant();
        if (!$tenant->features->has('allow_affirm')) {
            return $context;
        }
        /** @var ?AdyenAccount $adyenAccount */
        $adyenAccount = AdyenAccount::oneOrNull();
        if (!$adyenAccount) {
            return $context;
        }

        $requestParameters = $context->requestParameters;

        if (!isset($requestParameters['settings'])) {
            $requestParameters['settings'] = [];
        }

        //we do not override support email and dispute email
        $merchantAccountSettings = $merchantAccount->settings;
        if ($merchantAccountSettings->supportEmail ?? null) {
            $requestParameters['settings']['supportEmail'] = $merchantAccountSettings->supportEmail;
        }
        if ($merchantAccountSettings->disputeEmail ?? null) {
            $requestParameters['settings']['disputeEmail'] = $merchantAccountSettings->disputeEmail;
        }

        $settings = (array) $requestParameters['settings'];
        if (!$settings['supportEmail']) {
            return $context;
        }

        $country = (string) $tenant->country;
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, $country);

        $alternativeMethodsToEnable = [];
        if (!empty($requestParameters['enable_klarna'])) {
            $alternativeMethodsToEnable[] = PaymentMethod::KLARNA;
        }
        if (!empty($requestParameters['enable_affirm'])) {
            $alternativeMethodsToEnable[] = PaymentMethod::AFFIRM;
        }

        foreach ($alternativeMethodsToEnable as $method) {
            //ignore already created payment methods
            //this can't be changed on Adyen using API
            if ($merchantAccountSettings->{$method}->id ?? null) {
                continue;
            }

            $parameters = [
                'type' => $method,
                'businessLineId' => $adyenAccount->business_line_id,
                $method => [
                    'supportEmail' => $settings['supportEmail'],
                ],
            ];
            if (PaymentMethod::KLARNA === $method) {
                if (!$settings['disputeEmail']) {
                    continue;
                }

                $parameters[$method]['disputeEmail'] = $settings['disputeEmail']; /* @phpstan-ignore-line */
                $parameters[$method]['region'] = AdyenConfiguration::getMerchantRegion($this->adyenLiveMode, $country);

                //we add klarna account on top of klarna
                foreach (['klarna_account', 'klarna_paynow'] as $klarnaMethod) {
                    try {
                        $parameters2 = $parameters;
                        $parameters2['type'] = $klarnaMethod;
                        $requestParameters = $this->createNewPaymentMethodSetting($adyenAccount, $merchantAccount, $requestParameters, $parameters2['type'], $adyenMerchantAccount, $parameters2);
                    } catch (InvalidRequest) {
                        //ignore exception
                    }
                }

            }

            $requestParameters = $this->createNewPaymentMethodSetting($adyenAccount, $merchantAccount, $requestParameters, $method, $adyenMerchantAccount, $parameters);

            $paymentMethod = PaymentMethod::where('id', $method)
                ->oneOrNull() ?? new PaymentMethod();
                $paymentMethod->id = $method;
                $paymentMethod->enabled = true;
                $paymentMethod->gateway = $merchantAccount->gateway;
                $paymentMethod->setMerchantAccount($merchantAccount);
                $paymentMethod->saveOrFail();
        }

        return $context->withRequestParameters($requestParameters);
    }

    private function createNewPaymentMethodSetting(AdyenAccount $adyenAccount, MerchantAccount $merchantAccount, array $requestParameters, string $method, string $adyenMerchantAccount, array $settings): array
    {
        $settings['storeIds'] = [$merchantAccount->credentials->store];

        try {
            $response = $this->adyen->createPaymentMethodSetting($adyenMerchantAccount, $settings);
            $requestParameters['settings'][$method]['id'] = $response['id'];

            return $requestParameters;
        } catch (IntegrationApiException $e) {
            if ('Payment method already configured' === $e->getResponse()?->toArray(false)['detail']) {
                try {
                    $paymentMethods = $this->adyen->getPaymentMethodSettings($adyenMerchantAccount, [
                        'pageSize' => 100,
                        'businessLineId' => $adyenAccount->business_line_id,
                        'storeId' => $adyenAccount->store_id,
                    ]);
                    $existingMethod = array_values(array_filter($paymentMethods['data'], fn($m) => $m['type'] === $method));
                    if ($id = $existingMethod[0]['id'] ?? null) {
                        //actualize existing method
                        $requestParameters['settings'][$method]['id'] = $id;
                        $requestParameters['settings']['supportEmail'] = $existingMethod[0]['supportEmail'] ?? $requestParameters['settings']['supportEmail'];
                        $requestParameters['settings']['disputeEmail'] = $existingMethod[0]['disputeEmail'] ?? $requestParameters['settings']['disputeEmail'];

                        return $requestParameters;
                    }
                } catch (IntegrationApiException $e) {
                }

                $this->logger->error("Could not retrieve existing payment method settings for merchant account {$merchantAccount->id} after conflict.", ['exception' => $e]);
            }

            throw new InvalidRequest('Could not update payment method settings.');
        }
    }
}
