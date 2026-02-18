<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\ProcessIpnJob;
use ForceUTF8\Encoding;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class PayPalController extends AbstractController
{
    #[Route(path: '/paypal/ipn', name: 'paypal_ipn', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function paypalIpn(Request $request, Queue $queue): JsonResponse
    {
        $event = $request->request->all();

        // queue the webhook for future processing
        $event = $this->cleanup($event);
        $queue->enqueue(ProcessIpnJob::class, [
            'event' => $event,
        ]);

        return new JsonResponse(['status' => 'queued']);
    }

    /**
     * Cleans up any encoding issues with PayPal event messages.
     */
    private function cleanup(array $event): array
    {
        foreach ($event as &$value) {
            if (is_string($value)) {
                $value = Encoding::fixUTF8($value);
            }
        }

        return $event;
    }
}
