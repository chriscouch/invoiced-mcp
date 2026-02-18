<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationRun;
use App\Automations\Models\AutomationWorkflow;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Query;

/**
 * @extends AbstractListModelsApiRoute<AutomationRun>
 */
class AutomationRunRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        $params = $this->getBaseQueryParameters();
        $params['workflow_id'] = new QueryParameter(
            types: ['numeric', 'null'],
            default: null,
        );

        return new ApiRouteDefinition(
            queryParameters: $params,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: AutomationRun::class,
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);
        if (isset($context->requestParameters['workflow_id'])) {
            // we show only 100 last version for performance reasons
            $query->where('workflow_version_id IN (SELECT id FROM automation_workflow_versions WHERE automation_workflow_id = '.$context->requestParameters['workflow_id'].' LIMIT 100)');
        }

        $query->sort('created_at DESC');

        $query->with('workflow_version');

        return $query;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $response = parent::buildResponse($context);

        if (0 === count($response)) {
            return $response;
        }

        $workflowIds = array_map(fn (AutomationRun $run) => $run->workflow_version->automation_workflow_id, $response);

        /** @var AutomationWorkflow[] $workflows */
        $workflows = AutomationWorkflow::where('id', $workflowIds)->execute();
        $workflowsPrefixed = [];
        foreach ($workflows as $workflow) {
            $workflowsPrefixed[$workflow->id] = $workflow;
        }

        return array_map(function (AutomationRun $run) use ($workflowsPrefixed) {
            $runArray = $run->toArray();
            $runArray['workflow_version'] = $run->workflow_version->toArray();
            $runArray['workflow_version']['automation_workflow'] = $workflowsPrefixed[$run->workflow_version->automation_workflow_id]->toArray();

            return $runArray;
        }, $response);
    }
}
