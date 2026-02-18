<?php

namespace App\Integrations\Adyen\EventSubscriber;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Integrations\Adyen\ValueObjects\AdyenTokenizationWebhookEvent;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\FlowFormSubmission;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdyenTokenizationSubscriber implements EventSubscriberInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;
    use LoggerAwareTrait;

    public function __construct(
        private readonly CustomerPortalEvents $customerPortalEvents,
        private readonly PaymentSourceReconciler $paymentSourceReconciler,
        private readonly DeletePaymentInfo $deletePaymentInfo
    ) {
    }

    public function process(AdyenTokenizationWebhookEvent $event): void
    {
        $event = $event->data;

        if (!$event['success'] || 'false' === $event['success']) {
            $this->statsd->increment('adyen_tokenization_subscriber.success_false');

            return;
        }

        /** @var ?FlowFormSubmission $submission */
        $submission = FlowFormSubmission::where('reference', $event['merchantReference'])
            ->oneOrNull();
        if (!$submission) {
            $this->statsd->increment('adyen_tokenization_subscriber.no_submission');

            return;
        }

        parse_str($submission->data, $data);

        if (!($data['make_default'] ?? $data['enroll_autopay'] ?? false)) {
            return;
        }

        /** @var ?PaymentFlow $flow */
        $flow = PaymentFlow::queryWithoutMultitenancyUnsafe()
            ->where('identifier', $event['merchantReference'])
            ->oneOrNull();
        if (!$flow) {
            $this->statsd->increment('adyen_tokenization_subscriber.no_flow');

            return;
        }

        if (!$flow->customer || !$flow->merchant_account) {
            $this->statsd->increment('adyen_tokenization_subscriber.no_customer_or_merchant');

            return;
        }
        $customer = $flow->customer;

        $card = new CardValueObject(
            customer: $customer,
            gateway: AdyenGateway::ID,
            gatewayId: $event['additionalData']['recurring.recurringDetailReference'],
            gatewayCustomer: $event['additionalData']['shopperReference'],
            merchantAccount: $flow->merchant_account,
            chargeable: true,
            receiptEmail: $flow->email,
            brand: $event['paymentMethod'] ?? 'Unknown',
            funding: $flow->funding ?? 'unknown',
            last4: $flow->last4 ?? '0000',
            expMonth: $flow->expMonth ?? 12,
            expYear: $flow->expYear ?? (int) date('Y'),
            country: $flow->country,
        );
        $paymentSource = $this->paymentSourceReconciler->reconcile($card);
        $customer->setDefaultPaymentSource($paymentSource, $this->deletePaymentInfo);

        if (!($data['enroll_autopay'] ?? false)) {
            return;
        }

        // enroll in autopay
        $customer->autopay = true;
        if (!$customer->save()) {
            return;
        }

        // track the customer portal event
        $this->customerPortalEvents->track($customer, CustomerPortalEvent::AutoPayEnrollment);
        $this->statsd->increment('billing_portal.autopay_enrollment');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdyenTokenizationWebhookEvent::class => 'process',
        ];
    }
}
