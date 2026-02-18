<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\ProcessStripeConnectWebhookJob;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class StripeController extends AbstractController
{
    #[Route(path: '/stripe/connect/webhook', name: 'stripe_connect_webhook', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function stripeConnectWebhook(Request $request, Queue $queue): JsonResponse
    {
        // queue the webhook for future processing
        $data = $request->request->all();
        $data = array_merge($data, $request->query->all());
        $queue->enqueue(ProcessStripeConnectWebhookJob::class, [
            'event' => $data,
        ]);

        return new JsonResponse(['status' => 'queued']);
    }
}
