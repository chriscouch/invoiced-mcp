<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\SendInternalNotificationActionSettings;
use App\Core\Queue\Queue;
use App\Core\Templating\TwigRendererFactory;
use App\Core\Utils\Enums\ObjectType;
use App\EntryPoint\QueueJob\NotificationEventJob;
use App\Notifications\Enums\NotificationEventType;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendInternalNotificationAction extends AbstractAutomationAction
{
    public function __construct(
        private readonly Queue $queue,
        private readonly TranslatorInterface $translator,
        private readonly TwigRendererFactory $rendererFactory,
    ) {
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $variables = $context->getVariables();
        $mappings = new SendInternalNotificationActionSettings($settings->message, $settings->members);

        $message = trim($this->rendererFactory->render(
            $mappings->message,
            $variables,
            $context->getTwigContext($this->translator)
        ));

        if (!$message) {
            return new AutomationOutcome(AutomationResult::Failed, 'Invalid message');
        }
        $this->queue->enqueue(NotificationEventJob::class, [
            'tenant_id' => $context->tenantId(),
            'type' => NotificationEventType::AutomationTriggered,
            'objectId' => $context->sourceObjectData['id'],
            'contextId' => $mappings->members,
            'message' => $message,
        ]);

        return new AutomationOutcome(AutomationResult::Succeeded);
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        $this->validate($sourceObject->typeName());

        if (empty($settings->message)) {
            throw new AutomationException('Message is required');
        }
        if (empty($settings->members) || !is_array($settings->members)) {
            throw new AutomationException('At least one recipient is required');
        }

        $mappings = new SendInternalNotificationActionSettings($settings->message, $settings->members);
        $mappings->validate($sourceObject);

        return $mappings->serialize();
    }

    protected function getAction(): string
    {
        return 'SendInternalNotification';
    }
}
