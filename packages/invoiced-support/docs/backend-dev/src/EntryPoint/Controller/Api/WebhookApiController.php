<?php

namespace App\EntryPoint\Controller\Api;

use App\Webhooks\Api\CreateWebhookRoute;
use App\Webhooks\Api\DeleteWebhookRoute;
use App\Webhooks\Api\EditWebhookRoute;
use App\Webhooks\Api\ListWebhookAttemptsRoute;
use App\Webhooks\Api\ListWebhooksRoute;
use App\Webhooks\Api\RetryWebhookRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class WebhookApiController extends AbstractApiController
{
    #[Route(path: '/webhooks', name: 'list_webhooks', methods: ['GET'])]
    public function listAll(ListWebhooksRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/webhooks', name: 'create_webhook', methods: ['POST'])]
    public function create(CreateWebhookRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/webhooks/{model_id}', name: 'edit_webhook', methods: ['PATCH'])]
    public function edit(EditWebhookRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/webhooks/{model_id}', name: 'delete_webhook', methods: ['DELETE'])]
    public function delete(DeleteWebhookRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/webhook_attempts', name: 'list_webhook_attempt', methods: ['GET'])]
    public function listWebhookAttempts(ListWebhookAttemptsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/webhook_attempts/{model_id}/retries', name: 'retry_webhook_attempt', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function retryWebhookAttempt(RetryWebhookRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
