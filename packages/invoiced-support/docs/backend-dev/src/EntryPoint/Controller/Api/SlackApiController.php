<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Slack\SlackClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'api_', host: '%app.api_domain%')]
class SlackApiController extends AbstractApiController
{
    #[Route(path: '/slack/channels', name: 'slack_channels', methods: ['GET'])]
    public function slackChannels(SlackClient $client): JsonResponse
    {
        try {
            return new JsonResponse($client->listConversations());
        } catch (IntegrationException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
