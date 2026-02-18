<?php

namespace App\Integrations\Services;

use App\Automations\Enums\AutomationActionType;
use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowStep;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Automations\Models\AutomationWorkflowVersion;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Slack\SlackAccount;
use App\Notifications\Models\Notification;
use stdClass;

class Slack extends AbstractService
{
    public function __construct(
        Company $company,
        IntegrationType $integrationType,
    ) {
        parent::__construct($company, $integrationType);
    }

    private ?SlackAccount $account = null;

    public function isConnected(): bool
    {
        return $this->account || SlackAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!$account) {
            return null;
        }

        return $account->name;
    }

    public function getExtra(): stdClass
    {
        $extra = new stdClass();
        $account = $this->getAccount();
        $extra->webhook_channel = $account ? $account->webhook_channel : null;
        $extra->webhook_config_url = $account ? $account->webhook_config_url : null;

        return $extra;
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('Slack account is not connected');
        }

        $this->migrateToSlackV2($account);

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove Slack account');
        }
        $this->account = null;
    }

    /**
     * Gets the connected Slack account.
     */
    public function getAccount(): ?SlackAccount
    {
        if (!$this->accountLoaded) {
            $this->account = SlackAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }

    /**
     * Migrate to slack V2. We migrate on disconnect, while still possible to.
     */
    private function migrateToSlackV2(SlackAccount $account): void
    {
        // already migrated
        if (!$account->webhook_channel) {
            return;
        }
        /** @var Notification[] $notifications */
        $notifications = Notification::where('medium', 'slack')->all();
        foreach ($notifications as $notification) {
            $event = new Event();
            $event->type = $notification->event;

            $object = explode('.', $event->type);
            $objectType = ObjectType::fromTypeName($object[0]);
            $eventName = $event->getTitle();
            $automation = [
                'channel' => $account->webhook_channel,
                'message' => json_encode([
                    'attachments' => [
                        [
                            'title' => "<{{event.href}}|$eventName>",
                            'text' => '{{event.text}}',
                            'color' => '{{event.color}}',
                        ],
                    ],
                ]),
            ];

            $workflow = new AutomationWorkflow();
            $workflow->object_type = $objectType;
            $workflow->name = "Slack Notification for $eventName (migrated) ";
            $workflow->enabled = $notification->enabled;
            $workflow->save();

            $workflowVersion = new AutomationWorkflowVersion();
            $workflowVersion->automation_workflow = $workflow;
            $workflowVersion->version = 1;
            $workflowVersion->save();

            $workflow->current_version = $workflowVersion;
            $workflow->save();

            $trigger = new AutomationWorkflowTrigger();
            $trigger->workflow_version = $workflowVersion;
            $trigger->trigger_type = AutomationTriggerType::Event;
            $trigger->event_type = EventType::tryFrom($event->type)?->toInteger();
            $trigger->save();

            $step = new AutomationWorkflowStep();
            $step->workflow_version = $workflowVersion;
            $step->action_type = AutomationActionType::PostToSlack;
            $step->settings = (object) $automation;
            $step->order = 1;
            $step->save();

            $notification->delete();
        }
    }
}
