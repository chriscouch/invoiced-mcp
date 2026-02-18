<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Core\Queue\Queue;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\EntryPoint\QueueJob\ProcessFlywireRefundWebhookJob;
use App\EntryPoint\QueueJob\ProcessFlywireWebhookJob;
use App\Integrations\Flywire\FlywireHelper;
use App\PaymentProcessing\Models\MerchantAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class FlywireController extends AbstractController implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    #[Route(path: '/flywire/payment_callback/{merchantAccountId}', name: 'flywire_payment_callback', methods: ['POST'])]
    public function callback(
        Request $request,
        Queue $queue,
        int $merchantAccountId
    ): JsonResponse {
        $this->statsd->increment('flywire.webhook.payment');

        $merchantAccount = $this->getMerchantAccount($merchantAccountId);
        if ($merchantAccount instanceof JsonResponse) {
            return $merchantAccount;
        }

        $secret = FlywireHelper::getSecret($merchantAccount);
        if (!$this->validateSignature($secret, $request)) {
            $this->statsd->increment('flywire.webhook.payment_signature_mismatch');

            return new JsonResponse(['message' => 'Access Denied.'], 403);
        }

        $input = $request->request->all();
        $input['merchant_account_id'] = $merchantAccountId;
        $queue->enqueue(ProcessFlywireWebhookJob::class, [
            'event' => $input,
            'tenant_id' => $merchantAccount->tenant_id,
        ]);

        return new JsonResponse(['status' => 'queued']);
    }

    #[Route(path: '/flywire/refund_callback/{merchantAccountId}', name: 'flywire_refund_callback', methods: ['POST'])]
    public function refundCallback(Request $request, Queue $queue, string $flywireSharedSecret, int $merchantAccountId): JsonResponse
    {
        $this->statsd->increment('flywire.webhook.refund');

        $merchantAccount = $this->getMerchantAccount($merchantAccountId);
        if ($merchantAccount instanceof JsonResponse) {
            return $merchantAccount;
        }

        if (!$this->validateSignature($flywireSharedSecret, $request)) {
            $this->statsd->increment('flywire.webhook.refund_signature_mismatch');

            return new JsonResponse(['message' => 'Access Denied.'], 403);
        }

        $input = $request->request->all();
        $input['merchant_account_id'] = $merchantAccountId;
        $queue->enqueue(ProcessFlywireRefundWebhookJob::class, [
            'event' => $input,
            'tenant_id' => $merchantAccount->tenant_id,
        ]);

        return new JsonResponse(['status' => 'queued']);
    }

    private function getMerchantAccount(int $id): MerchantAccount|JsonResponse
    {
        $merchantAccount = MerchantAccount::queryWithoutMultitenancyUnsafe()
            ->where('id', $id)
            ->oneOrNull();
        if (null === $merchantAccount) {
            return new JsonResponse(['status' => 'Merchant account not found'], 404);
        }

        return $merchantAccount;
    }

    private function validateSignature(string $secret, Request $request): bool
    {
        $requestDigest = $request->headers->get('X-Flywire-Digest');

        return hash_equals(bin2hex(base64_decode((string) $requestDigest)), hash_hmac('sha256', $request->getContent(), $secret));
    }
}
