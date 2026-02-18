<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\PostToSlackActionSettings;
use App\Core\Templating\TwigRendererFactory;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Models\Event;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Slack\SlackClient;
use App\Notifications\Emitters\SlackEmitter;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class PostToSlackAction extends AbstractAutomationAction
{
    public function __construct(
        private readonly SlackClient $slackClient,
        private readonly TranslatorInterface $translator,
        private readonly EventStorageInterface $eventStorage,
        private readonly SlackEmitter $emitter,
        private readonly TwigRendererFactory $rendererFactory,
    ) {
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $variables = $context->getVariables();
        $mapping = new PostToSlackActionSettings($settings->channel, $settings->message);

        if ($context->event instanceof Event) {
            try {
                $this->eventStorage->hydrateEvents([$context->event]);
                $variables['event'] = [
                    'href' => $context->event->href,
                    'text' => strip_tags($context->event->getMessage()->toString(false)),
                    'color' => $this->emitter->getColor($context->event->type),
                ];
            } catch (Throwable) {
                // Ignore
            }
        }

        $messageCandidate = trim($this->rendererFactory->render(
            $mapping->message,
            $variables,
            $context->getTwigContext($this->translator)
        ));

        $message = [
            'channel' => $mapping->channel,
        ];
        $messageCandidateObject = json_decode($messageCandidate, true);
        if (is_array($messageCandidateObject)) {
            $messageCandidateObject = array_intersect_key($messageCandidateObject, array_flip(['attachments', 'blocks', 'text']));
            if ($messageCandidateObject) {
                $message = array_merge($message, array_map('json_encode', $messageCandidateObject));
            }
        }
        if (!$messageCandidateObject) {
            $message['text'] = $messageCandidate;
        }

        try {
            $this->slackClient->postMessage($message);
        } catch (IntegrationException $e) {
            return new AutomationOutcome(AutomationResult::Failed, $e->getMessage());
        }

        return new AutomationOutcome(AutomationResult::Succeeded);
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        if (empty($settings->message)) {
            throw new AutomationException('Missing `message` parameter');
        }
        if (empty($settings->channel)) {
            throw new AutomationException('Missing `channel` parameter');
        }

        try {
            $this->slackClient->joinConversation($settings->channel);
        } catch (IntegrationException $e) {
            throw new AutomationException($e->getMessage());
        }

        $mapping = new PostToSlackActionSettings($settings->channel, $settings->message);
        $mapping->validate($sourceObject);

        return $mapping->serialize();
    }

    protected function getAction(): string
    {
        return 'PostToSlack';
    }
}
