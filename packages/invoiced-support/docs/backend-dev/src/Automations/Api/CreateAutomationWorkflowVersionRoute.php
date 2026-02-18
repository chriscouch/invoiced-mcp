<?php

namespace App\Automations\Api;

use App\Automations\Enums\AutomationActionType;
use App\Automations\Exception\AutomationException;
use App\Automations\Libs\SettingsValidator;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowStep;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Automations\Models\AutomationWorkflowVersion;
use App\Automations\Traits\SaveAutomationWorkflowVersionTrait;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractCreateModelApiRoute<AutomationWorkflowVersion>
 */
class CreateAutomationWorkflowVersionRoute extends AbstractCreateModelApiRoute
{
    use SaveAutomationWorkflowVersionTrait;

    public function __construct(private readonly SettingsValidator $settingsValidator)
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
        $requestParameters = $context->requestParameters;

        // Obtain workflow
        $workflow = $this->getModelOrFail(AutomationWorkflow::class, $context->request->attributes->get('workflow_id'));
        $requestParameters['automation_workflow'] = $workflow;

        // Determine next version number
        $latestVersion = (int) AutomationWorkflowVersion::where('automation_workflow_id', $workflow)
            ->max('version');
        $requestParameters['version'] = $latestVersion + 1;

        $triggers = $requestParameters['triggers'];
        unset($requestParameters['triggers']);
        $inputSteps = $requestParameters['steps'];
        unset($requestParameters['steps']);

        // Build steps
        if (0 == count($inputSteps)) {
            throw new InvalidRequest('At least one action must be specified');
        }

        $order = 1;
        $steps = [];
        foreach ($inputSteps as $stepParams) {
            $step = new AutomationWorkflowStep();
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
                $this->settingsValidator->validateSetting($step, $workflow->object_type);
            } catch (AutomationException $e) {
                throw new InvalidRequest('Invalid settings: '.$e->getMessage());
            }

            $steps[] = $step;
        }

        $context = $context->withRequestParameters($requestParameters);

        $workflowVersion = parent::buildResponse($context);

        // Build triggers
        if (0 == count($triggers)) {
            throw new InvalidRequest('At least one trigger must be specified');
        }

        foreach ($triggers as $triggerParams) {
            $trigger = new AutomationWorkflowTrigger();
            $trigger->workflow_version = $workflowVersion;

            $this->saveTrigger($trigger, $triggerParams);
        }

        foreach ($steps as $step) {
            $step->workflow_version = $workflowVersion;
            if (!$step->save()) {
                throw new InvalidRequest('Could not save step: '.$step->getErrors());
            }
        }

        return $workflowVersion;
    }
}
