<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\ProcessGoCardlessWebhookJob;
use App\Integrations\GoCardless\GoCardlessWebhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class GoCardlessController extends AbstractController
{
    #[Route(path: '/gocardless/webhook', name: 'gocardless_webhook', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function goCardlessWebhook(Request $request, Queue $queue, GoCardlessWebhook $handler): Response
    {
        // Validate the webhook signature
        $payload = $request->getContent();
        $signature = (string) $request->headers->get('Webhook_Signature');

        if (!$handler->validateSignature($signature, $payload)) {
            return new JsonResponse(['status' => 'invalid signature'], 401);
        }

        $data = json_decode($payload, true);
        foreach ($data['events'] as $event) {
            $queue->enqueue(ProcessGoCardlessWebhookJob::class, [
                'event' => $event,
            ]);
        }

        return new Response('');
    }
}
