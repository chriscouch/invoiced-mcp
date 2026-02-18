<?php

namespace App\Automations\Api;

use App\Automations\Enums\AutomationActionType;
use App\Automations\Exception\AutomationException;
use App\Automations\Libs\SettingsValidator;
use App\Automations\Models\AutomationWorkflowStep;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Automations\Models\AutomationWorkflowVersion;
use App\Automations\Traits\SaveAutomationWorkflowVersionTrait;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use Doctrine\DBAL\Connection;

/**
 * @extends AbstractRetrieveModelApiRoute<AutomationWorkflowVersion>
 */
class EditAutomationWorkflowVersionRoute extends AbstractRetrieveModelApiRoute
{
    use SaveAutomationWorkflowVersionTrait;

    public function __construct(private readonly Connection $database, private readonly SettingsValidator $settingsValidator)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'triggers' => new RequestParameter(
                    types: ['array'],
                    default: [],
                ),
                'steps' => new RequestParameter(
                    types: ['array'],
                    default: [],
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: AutomationWorkflowVersion::class,
            features: ['automations'],
        );
    }

    public function buildResponse(ApiCallContext $context): AutomationWorkflowVersion
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $workflowVersion = $this->retrieveModel($context);
        $workflowId = $context->request->attributes->get('workflow_id');
        if ($workflowVersion->automation_workflow_id != $workflowId) {
            throw $this->modelNotFoundError();
        }

        // Do not permit editing the version if it is the current version of the workflow
        if ($workflowVersion->automation_workflow->current_version_id == $workflowVersion->id) {
            throw new InvalidRequest('This workflow version cannot be edited while it is the current version of the workflow.');
        }

        // Can only edit if this version is the draft version of the workflow
        if ($workflowVersion->automation_workflow->draft_version_id != $workflowVersion->id) {
            throw new InvalidRequest('This workflow version cannot be edited while it is not the draft version of the workflow.');
        }

        // Create or update triggers
        if (0 == count($context->requestParameters['triggers'])) {
            throw new InvalidRequest('At least one trigger must be specified');
        }

        $steps = [];
        $order = 1;
        foreach ($context->requestParameters['steps'] as $stepParams) {
            if (isset($stepParams['id'])) {
                $step = $this->getModelOrFail(AutomationWorkflowStep::class, $stepParams['id']);
            } else {
                $step = new AutomationWorkflowStep();
                $step->workflow_version = $workflowVersion;
            }

            $step->order = $order;
            ++$order;

            if (!isset($stepParams['action_type'])) {
                throw new InvalidRequest('Action type is required');
            }
            $step->action_type = AutomationActionType::{$stepParams['action_type']};

            if (isset($stepParams['settings'])) {
                $step->settings = json_decode((string) json_encode($stepParams['settings']));
            }

            try {
                $this->settingsValidator->validateSetting($step, $workflowVersion->automation_workflow->object_type);
            } catch (AutomationException $e) {
                throw new InvalidRequest('Invalid settings: '.$e->getMessage());
            }

            $steps[] = $step;
        }

        $triggerIds = [];
        foreach ($context->requestParameters['triggers'] as $triggerParams) {
            if (isset($triggerParams['id'])) {
                $trigger = $this->getModelOrFail(AutomationWorkflowTrigger::class, $triggerParams['id']);
            } else {
                $trigger = new AutomationWorkflowTrigger();
                $trigger->workflow_version = $workflowVersion;
            }

            $this->saveTrigger($trigger, $triggerParams);

            $triggerIds[] = $trigger->id;
        }

        // Delete extra triggers
        $query = $this->database->createQueryBuilder()
            ->delete('AutomationWorkflowTriggers')
            ->where('tenant_id = :tenant')
            ->andWhere('workflow_version_id = :version')
            ->setParameter('tenant', $workflowVersion->tenant_id)
            ->setParameter('version', $workflowVersion->id);
        if (count($triggerIds) > 0) {
            $query->andWhere('id NOT IN (:idList)')
                ->setParameter('idList', $triggerIds, Connection::PARAM_STR_ARRAY);
        }
        $query->executeStatement();

        // Create or update steps
        if (0 == count($context->requestParameters['steps'])) {
            throw new InvalidRequest('At least one action must be specified');
        }

        $stepIds = [];
        foreach ($steps as $step) {
            if (!$step->save()) {
                throw new InvalidRequest('Could not save step: '.$step->getErrors());
            }

            $stepIds[] = $step->id;
        }

        // Delete extra steps
        $query = $this->database->createQueryBuilder()
            ->delete('AutomationWorkflowSteps')
            ->where('tenant_id = :tenant')
            ->andWhere('workflow_version_id = :version')
            ->setParameter('tenant', $workflowVersion->tenant_id)
            ->setParameter('version', $workflowVersion->id);
        if (count($stepIds) > 0) {
            $query->andWhere('id NOT IN (:idList)')
                ->setParameter('idList', $stepIds, Connection::PARAM_STR_ARRAY);
        }
        $query->executeStatement();

        return $workflowVersion;
    }
}
