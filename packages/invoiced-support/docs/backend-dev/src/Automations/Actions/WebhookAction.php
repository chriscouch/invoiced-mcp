<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\Interfaces\AutomationActionInterface;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\WebhookActionSettings;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Interfaces\EventObjectInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookAction implements AutomationActionInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $mapping = new WebhookActionSettings($settings->url);
        $object = $context->sourceObject;
        $data = [
            'object' => $object instanceof EventObjectInterface ? $object->getEventObject() : ModelNormalizer::toArray($object),
            'context' => $context->toArray(),
        ];

        try {
            $this->httpClient->request(
                'POST',
                $mapping->url,
                [
                    'timeout' => 10,
                    'max_duration' => 30,
                    'max_redirects' => 0, // do not follow any redirect
                    'headers' => [
                        'User-Agent' => 'Invoiced/1.0',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $data,
                ],
            );
        } catch (ExceptionInterface $e) {
            return new AutomationOutcome(AutomationResult::Failed, $e->getMessage());
        }

        return new AutomationOutcome(AutomationResult::Succeeded);
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        if (!isset($settings->url)) {
            throw new AutomationException('Missing URL');
        }

        $mapping = new WebhookActionSettings($settings->url);
        $mapping->validate($sourceObject);

        return $mapping->serialize();
    }
}
