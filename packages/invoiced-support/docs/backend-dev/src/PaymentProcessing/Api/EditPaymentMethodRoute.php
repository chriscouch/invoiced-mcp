<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Models\PaymentInstruction;
use App\PaymentProcessing\Models\PaymentMethod;

class EditPaymentMethodRoute extends AbstractEditModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: PaymentMethod::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        // add the company ID to the payment method ID
        $id = $context->request->attributes->get('model_id');
        $country = (string) $context->request->request->get('country');
        $tenant = $this->tenant->get();

        $this->setModelIds([$tenant->id(), $id]);

        if ($country) {
            $this->retrieveModel($context);

            /** @var PaymentMethod $model */
            $model = $this->model;
            $meta = (string) $context->request->request->get('meta');
            $enabled = $context->request->request->getBoolean('enabled');

            $paymentInstruction = PaymentInstruction::where('payment_method_id', $id)
                ->where('country', $country)
                ->oneOrNull();
            if (!$paymentInstruction) {
                $paymentInstruction = new PaymentInstruction();
                $paymentInstruction->payment_method_id = $id;
                $paymentInstruction->country = $country;
            }
            $paymentInstruction->meta = $meta;
            $paymentInstruction->enabled = $enabled;
            $paymentInstruction->saveOrFail();

            $model->meta = $meta;
            $model->enabled = $enabled;

            return $model;
        }

        return parent::buildResponse($context);
    }
}
