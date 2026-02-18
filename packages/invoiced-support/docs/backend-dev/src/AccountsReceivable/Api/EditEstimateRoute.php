<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Estimate;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use Doctrine\DBAL\Connection;

class EditEstimateRoute extends AbstractEditModelApiRoute
{
    private ?array $disabledPaymentMethods = null;

    public function __construct(private Connection $database)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['estimates.edit'],
            modelClass: Estimate::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;

        if (isset($requestParameters['disabled_payment_methods'])) {
            $this->disabledPaymentMethods = (array) $requestParameters['disabled_payment_methods'];
            unset($requestParameters['disabled_payment_methods']);
        }

        $context = $context->withRequestParameters($requestParameters);

        $estimate = parent::buildResponse($context);

        $this->saveDisabledPaymentMethods($estimate);

        return $estimate;
    }

    private function saveDisabledPaymentMethods(Estimate $estimate): void
    {
        if (!is_array($this->disabledPaymentMethods)) {
            return;
        }

        // clear out existing disabled methods
        $this->database->delete('DisabledPaymentMethods', [
            'tenant_id' => $estimate->tenant_id,
            'object_type' => $estimate->object,
            'object_id' => $estimate->id(),
        ]);

        // save any disabled methods
        foreach ($this->disabledPaymentMethods as $method) {
            $disabled = new DisabledPaymentMethod();
            $disabled->method = $method;
            $disabled->object_type = $estimate->object;
            $disabled->object_id = (string) $estimate->id();
            $disabled->saveOrFail();
        }
    }
}
