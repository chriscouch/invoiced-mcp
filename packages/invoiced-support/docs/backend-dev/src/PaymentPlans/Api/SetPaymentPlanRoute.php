<?php

namespace App\PaymentPlans\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;

class SetPaymentPlanRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['invoices.edit'],
            modelClass: Invoice::class,
            features: ['payment_plans'],
        );
    }

    public function buildResponse(ApiCallContext $context): PaymentPlan
    {
        $installments1 = $context->request->request->all('installments');

        $autopay1 = true;
        $autopay = $context->request->request->get('autopay');
        if (is_bool($autopay)) {
            $autopay1 = $autopay;
        }

        $requireApproval1 = false;
        $requireApproval = $context->request->request->get('require_approval');
        if ($autopay1 && is_bool($requireApproval)) {
            $requireApproval1 = $requireApproval;
        }

        $invoice = parent::buildResponse($context);

        $paymentPlan = new PaymentPlan();

        // build from request
        $installments = [];
        foreach ($installments1 as $params) {
            $installment = new PaymentPlanInstallment();
            foreach ($params as $k => $v) {
                $installment->$k = $v;
            }

            $installments[] = $installment;
        }

        $paymentPlan->installments = $installments;

        if ($invoice->attachPaymentPlan($paymentPlan, $autopay1, $requireApproval1)) {
            return $paymentPlan;
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a generic error
        throw new ApiError('There was an error creating the payment plan.');
    }
}
