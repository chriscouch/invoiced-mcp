<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Core\Queue\Queue;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\EntryPoint\QueueJob\ProcessAdyenPlatformWebhookJob;
use App\Integrations\Adyen\Libs\PaymentWebhookFactory;
use App\Integrations\Adyen\Models\AdyenAccount;
use Generator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class AdyenController extends AbstractController implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    #[Route(path: '/adyen/webhook/payments', name: 'adyen_webhook_payments', methods: ['POST'])]
    public function paymentsAuthorizationWebhook(
        Request $request,
        PaymentWebhookFactory $paymentWebhookFactory,
        string $adyenHmacWebhookPayments,
    ): JsonResponse {
        $input = $request->request->all();

        $this->statsd->increment('adyen.webhook.payments');

        $notificationItems = $this->validateNotificationItems((array) $input['notificationItems'], $adyenHmacWebhookPayments);

        foreach ($notificationItems as $notificationItem) {
            $item = $notificationItem['NotificationRequestItem'];

            $webhookFactory = $paymentWebhookFactory->get($item);
            $webhookFactory->process($item);
        }

        return new JsonResponse(['status' => 'queued']);
    }

    #[Route(path: '/adyen/webhook/configuration', name: 'adyen_webhook_configuration', methods: ['POST'])]
    public function configurationWebhook(Request $request, Queue $queue, string $adyenHmacWebhookConfiguration): JsonResponse
    {
        return $this->processPlatformWebhook('configuration', $adyenHmacWebhookConfiguration, $request, $queue);
    }

    #[Route(path: '/adyen/webhook/negative_balance_compensation_warning', name: 'adyen_webhook_negative_balance_compensation_warning', methods: ['POST'])]
    public function negativeBalanceCompensationWarning(Request $request, Queue $queue): JsonResponse
    {
        return $this->processPlatformWebhook('negative_balance_compensation_warning', '', $request, $queue);
    }

    #[Route(path: '/adyen/webhook/report', name: 'adyen_webhook_report', methods: ['POST'])]
    public function reportWebhook(Request $request, string $adyenHmacWebhookReport, Queue $queue): JsonResponse
    {
        return $this->processPlatformWebhook('report', $adyenHmacWebhookReport, $request, $queue);
    }

    #[Route(path: '/adyen/webhook/transaction', name: 'adyen_webhook_transaction', methods: ['POST'])]
    public function transactionWebhook(Request $request, Queue $queue, string $adyenHmacWebhookTransaction): JsonResponse
    {
        return $this->processPlatformWebhook('transaction', $adyenHmacWebhookTransaction, $request, $queue);
    }

    #[Route(path: '/adyen/webhook/transfer', name: 'adyen_webhook_transfer', methods: ['POST'])]
    public function transferWebhook(Request $request, Queue $queue, string $adyenHmacWebhookTransfer): JsonResponse
    {
        return $this->processPlatformWebhook('transfer', $adyenHmacWebhookTransfer, $request, $queue);
    }

    private function getAdyenAccount(array $input): ?AdyenAccount
    {
        // The account holder ID might be in different places depending on the webhook type
        $accountHolderId = $input['data']['accountHolder']['id'] ?? null;
        if (!$accountHolderId) {
            $accountHolderId = $input['data']['balanceAccount']['accountHolderId'] ?? null;
        }

        // Not every webhook type has an account ID. For example, the report webhooks.
        if (!$accountHolderId) {
            return null;
        }

        return AdyenAccount::queryWithoutMultitenancyUnsafe()
            ->where('account_holder_id', $accountHolderId)
            ->oneOrNull();
    }

    /**
     * Verifies HMAC signature.
     */
    private function validateHmacSignature(string $payload, string $hmacKey, string $hmacSignature): bool
    {
        return hash_equals($hmacSignature, base64_encode(hash_hmac('sha256', $payload, hex2bin($hmacKey) ?: '', true)));
    }

    private function processPlatformWebhook(string $type, string $hmacKey, Request $request, Queue $queue): JsonResponse
    {
        $this->statsd->increment('adyen.webhook.'.$type);

        if ($hmacKey && !$this->validateHmacSignature($request->getContent(), $hmacKey, $request->headers->get('HmacSignature') ?? '')) {
            $this->statsd->increment('adyen.webhook.'.$type.'_signature_mismatch');

            return new JsonResponse(['message' => 'Signature mismatch'], 400);
        }

        $input = $request->request->all();
        $account = $this->getAdyenAccount($input);

        $queue->enqueue(ProcessAdyenPlatformWebhookJob::class, [
            'event' => $input,
            'tenant_id' => $account?->tenant_id,
        ]);

        return new JsonResponse(['status' => 'queued']);
    }

    /**
     * Verifies HMAC signature.
     */
    private function validateNotificationItemSignature(array $data, string $hmacKey): bool
    {
        if (!$data['pspReference']) {
            return false;
        }

        $payload = implode(':', [
            $data['pspReference'],
            $data['originalReference'] ?? '',
            $data['merchantAccountCode'] ?? '',
            $data['merchantReference'] ?? '',
            $data['amount']['value'] ?? '',
            $data['amount']['currency'] ?? '',
            $data['eventCode'] ?? '',
            $data['success'] ?? '',
        ]);

        return $this->validateHmacSignature($payload, $hmacKey, $data['additionalData']['hmacSignature'] ?? '');
    }

    private function validateNotificationItems(array $entries, string $hmacKey): Generator
    {
        foreach ($entries as $notification) {
            if (!$this->validateNotificationItemSignature($notification['NotificationRequestItem'], $hmacKey)) {
                $this->statsd->increment('adyen.webhook.chargeback_signature_mismatch');
            }

            yield $notification;
        }
    }
}
