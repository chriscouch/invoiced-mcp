<?php

namespace App\Integrations\Adyen\EventSubscriber;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\Enums\PaymentEvent;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Libs\AdyenPaymentWebhook;
use App\Integrations\Adyen\Operations\SaveAdyenPayment;
use App\Integrations\Adyen\ValueObjects\AdyenPaymentAuthorizationWebhookEvent;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdyenPaymentSubscriber implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    const int MAX_RETRY_ATTEMPTS = 5;
    const int WAIT_TTL = 30;

    public function __construct(
        private readonly SaveAdyenPayment $saveAdyenPayment,
        private readonly AdyenPaymentWebhook $webhook,
    ) {
    }

    public function process(AdyenPaymentAuthorizationWebhookEvent $event): void
    {
        if (!PaymentEvent::tryFrom($event->data['eventCode'])) {
            return;
        }
        $amount = isset($event->data['amount']) ? new Money(
            $event->data['amount']['currency'],
            $event->data['amount']['value']
        ) : null;
        $charge = null;
        try {
            $charge = $this->saveAdyenPayment->tryReconcile($event->data['pspReference'], $event->data['merchantReference'], $amount);
        } catch (AdyenReconciliationException) {
            // sometimes webhooks comes before payment details call
            // we need to retry webhook then
            $data = $event->data;
            $data['_retry_attempt'] ??= 0;
            if ($data['_retry_attempt'] > self::MAX_RETRY_ATTEMPTS) {
                $this->logger->error('Adyen webhook retry limit reached', [
                    'merchantReference' => $data['merchantReference'],
                ]);

                return;
            }

            ++$data['_retry_attempt'];
            $this->webhook->process($data, $data['_retry_attempt'] * self::WAIT_TTL);
        }

        //check if webhook arrived after the payout report and update the MerchantAccountTransaction
        if($charge){
            $merchantAccountTransaction = MerchantAccountTransaction::where('reference', $event->data['pspReference'])->oneOrNull();
            if($merchantAccountTransaction){
                $merchantAccountTransaction->description = $charge->description ?? 'Payment';
                $merchantAccountTransaction->setSource($charge);
                $merchantAccountTransaction->saveOrFail();
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdyenPaymentAuthorizationWebhookEvent::class => 'process',
        ];
    }
}
