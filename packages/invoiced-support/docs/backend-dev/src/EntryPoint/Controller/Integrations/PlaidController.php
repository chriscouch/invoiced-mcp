<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\ProcessPlaidWebhookJob;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class PlaidController extends AbstractController
{
    #[Route(path: '/plaid/webhook', name: 'plaid_webhook', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function plaidWebhook(Request $request, Queue $queue): JsonResponse
    {
        // queue the webhook for future processing
        $data = $request->request->all();
        $data = array_merge($data, $request->query->all());
        $queue->enqueue(ProcessPlaidWebhookJob::class, [
            'event' => $data,
        ]);

        return new JsonResponse(['status' => 'queued']);
    }
}
