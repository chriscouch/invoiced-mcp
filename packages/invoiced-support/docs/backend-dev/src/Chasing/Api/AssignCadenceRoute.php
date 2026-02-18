<?php

namespace App\Chasing\Api;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;

class AssignCadenceRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['customers.edit'],
            modelClass: ChasingCadence::class,
            features: ['smart_chasing'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $customers = $context->request->request->all('customers');
        $nextStepId = $context->request->request->getInt('next_step');

        $cadence = parent::buildResponse($context);

        $nextStep = null;
        if ($nextStepId) {
            $nextStep = ChasingCadenceStep::where('id', $nextStepId)
                ->where('chasing_cadence_id', $cadence)
                ->oneOrNull();

            if (!$nextStep) {
                throw new InvalidRequest('No such step: '.$nextStepId);
            }
        }

        foreach ($customers as $customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                throw new InvalidRequest('No such customer: '.$customerId);
            }

            if (!$customer->chase || $customer->chasing_cadence != $cadence->id() || ($nextStepId && $customer->next_chase_step != $nextStepId)) {
                $customer->chase = true;
                $customer->chasing_cadence = $cadence->id();
                $customer->setRelation('chasing_cadence_id', $cadence);

                if ($nextStepId) {
                    $customer->next_chase_step = $nextStepId;
                    $customer->setRelation('next_chase_step_id', $nextStep);
                }

                $customer->skipReconciliation();
                if (!$customer->save()) {
                    throw new InvalidRequest('Could not assign cadence: '.$customer->getErrors());
                }
            }
        }

        return new Response('', 204);
    }
}
