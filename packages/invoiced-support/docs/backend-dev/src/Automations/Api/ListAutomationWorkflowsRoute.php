<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowEnrollment;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Query;

/**
 * @extends AbstractListModelsApiRoute<AutomationWorkflow>
 */
class ListAutomationWorkflowsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                [
                    'object_id' => new QueryParameter(
                        types: ['numeric', 'null'],
                        default: null,
                    ),
                ],
            ),
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: AutomationWorkflow::class,
            filterableProperties: ['object_type', 'enabled'],
            features: ['automations'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);
        $query->where('deleted', false);

        return $query;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var AutomationWorkflow[] $models */
        $models = parent::buildResponse($context);

        if ($context->queryParameters['object_id'] && $this->isParameterIncluded($context, 'enrollment')) {
            $modelsPrefixed = [];
            foreach ($models as $model) {
                $modelsPrefixed[$model->id] = $model;
            }

            if (0 === count($modelsPrefixed)) {
                return [];
            }

            /** @var AutomationWorkflowEnrollment[] $enrolments */
            $enrolments = AutomationWorkflowEnrollment::where('object_id', $context->queryParameters['object_id'])
                ->where('workflow_id', array_keys($modelsPrefixed))
                ->execute();

            foreach ($enrolments as $enrolment) {
                $modelsPrefixed[$enrolment->workflow_id]->setEnrollment($enrolment);
            }

            $models = array_values($modelsPrefixed);
        }

        return $models;
    }
}
