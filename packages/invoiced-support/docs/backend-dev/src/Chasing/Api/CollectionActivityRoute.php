<?php

namespace App\Chasing\Api;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\CompletedChasingStep;
use App\Chasing\Models\Task;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class CollectionActivityRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $customer = parent::buildResponse($context);

        $cadence = $customer->chasingCadence();
        $steps = [];

        if ($cadence) {
            foreach ($cadence->getSteps() as $step) {
                // load to do task
                $task = Task::where('chase_step_id', $step->id())
                    ->where('customer_id', $customer)
                    ->sort('id DESC')
                    ->oneOrNull();

                $steps[$step->id()] = [
                    'last_run' => null,
                    'successful' => null,
                    'message' => null,
                    'to_do_task' => $task ? $task->toArray() : null,
                ];
            }

            // load completed steps
            $completed = CompletedChasingStep::where('customer_id', $customer->id())
                ->where('cadence_id', $cadence)
                ->sort('timestamp DESC')
                ->first(100);

            foreach ($completed as $completedStep) {
                $stepId = $completedStep->chase_step_id;
                if ($steps[$stepId]['last_run']) {
                    continue;
                }

                $steps[$stepId]['last_run'] = $completedStep->timestamp;
                $steps[$stepId]['successful'] = $completedStep->successful;
                $steps[$stepId]['message'] = $completedStep->message;
            }
        }

        return [
            'steps' => $steps,
        ];
    }
}
