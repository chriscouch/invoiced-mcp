<?php

namespace App\CustomerPortal\Command;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkSession;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\Core\Database\TransactionManager;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\ModelNormalizer;
use App\CustomerPortal\Command\PaymentLinks\PaymentLinkCustomerHandler;
use App\CustomerPortal\Command\PaymentLinks\PaymentLinkInvoiceHandler;
use App\CustomerPortal\Command\PaymentLinks\PaymentLinkProcessPayment;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\CustomerPortal\Libs\CustomerPortalHelper;
use App\CustomerPortal\ValueObjects\PaymentLinkResult;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\Models\FlowFormSubmission;
use Carbon\CarbonImmutable;
use App\PaymentProcessing\Models\PaymentFlow;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentLinkProcessor implements StatsdAwareInterface
{
    use StatsdAwareTrait;
    use CustomerPortalViewVariablesTrait;

    public function __construct(
        private TransactionManager $transaction,
        private PaymentLinkCustomerHandler $customerHandler,
        private PaymentLinkInvoiceHandler $invoiceHandler,
        private PaymentLinkProcessPayment $processPayment,
        private NotificationSpool $notificationSpool,
        private CustomerPortalEvents $customerPortalEvents,
        private UrlGeneratorInterface $urlGenerator,
        private EventSpool $eventSpool,
    ) {
    }

    /**
     * Handles the submitted payment form.
     *
     * @throws PaymentLinkException
     */
    public function handleSubmit(PaymentLink $paymentLink, array $parameters): PaymentLinkResult
    {
        // Determine the payment amount
        $amount = Money::fromDecimal($paymentLink->currency, (float) ($parameters['amount'] ?? 0));

        // Validate the input before modifying data or processing payment
        $this->validateInput($paymentLink, $amount, $parameters);

        /** @var PaymentLinkResult $result */
        $result = $this->transaction->perform(function () use ($paymentLink, $amount, $parameters) {
            $result = new PaymentLinkResult($paymentLink);

            try {
                // Retrieve the payment flow
                $this->retrievePaymentFlow($result, $parameters, $amount);

                // Create or find the customer
                $this->customerHandler->handle($result, $parameters);

                // Create the invoice
                $this->invoiceHandler->handle($result, $amount, $parameters);
            } catch (ModelException $e) {
                throw new PaymentLinkException($e->getMessage(), $e->getCode(), $e);
            }

            // Process the payment
            $this->processPayment->process($result, $amount, $parameters);

            // Save the payment link session
            $this->createSession($result, $parameters);

            return $result;
        });

        $this->afterTransaction($result);

        return $result;
    }

    //
    // Helper methods
    //

    /**
     * Validates the payment link input before performing any actions.
     *
     * @throws PaymentLinkException
     */
    private function validateInput(PaymentLink $paymentLink, Money $amount, array $parameters): void
    {
        // verify the amount is positive
        if (!$amount->isPositive()) {
            throw new PaymentLinkException('The amount cannot be zero.');
        }

        // verify the ToS were accepted
        $accepted = $parameters['tos_accepted'] ?? false;
        if ($paymentLink->terms_of_service_url && !$accepted) {
            throw new PaymentLinkException('Please accept the Terms of Service in order to proceed.');
        }

        if (!isset($parameters['payment_flow'])) {
            throw new PaymentLinkException('Missing payment flow identifier.');
        }
    }

    /**
     * Retrieves the payment link flow and updates the amount, if needed.
     *
     * @throws PaymentLinkException
     */
    private function retrievePaymentFlow(PaymentLinkResult $paymentLinkResult, array $parameters, Money $amount): void
    {
        /** @var ?PaymentFlow $paymentFlow */
        $paymentFlow = PaymentFlow::where('identifier', $parameters['payment_flow'])->oneOrNull();
        if (!$paymentFlow) {
            throw new PaymentLinkException('The payment flow does not exist.');
        }

        // payment link does not contain convenience fee by default
        $convenienceFee = $paymentFlow->getConvenienceFee();
        $fee = Money::fromDecimal($paymentFlow->currency, $convenienceFee?->amount ?? 0);
        $amount = $amount->add($fee);

        if ($paymentFlow->amount != $amount->toDecimal()) {
            $paymentFlow->amount = $amount->toDecimal();
            $paymentFlow->saveOrFail();
        }

        $paymentLinkResult->setPaymentFlow($paymentFlow);
    }

    /**
     * Creates a payment link session once the payment has been processed.
     */
    public function createSession(PaymentLinkResult $result, array $parameters): void
    {
        $session = new PaymentLinkSession();
        $session->hash = (string) ($parameters['hash'] ?? '');
        $session->payment_link = $result->paymentLink;
        $session->customer = $result->getCustomer();
        $session->invoice = $result->getInvoice();
        $session->payment = $result->getPayment();
        $session->completed_at = CarbonImmutable::now();
        $session->save();
        $result->setSession($session);

        if (!$result->paymentLink->reusable) {
            EventSpool::disablePush(); // do not record an updated event for this
            $result->paymentLink->status = PaymentLinkStatus::Completed;
            $result->paymentLink->save();
            EventSpool::enablePop();
        }
    }

    /**
     * Performs post-payment link actions after the database transaction is committed.
     */
    public function afterTransaction(PaymentLinkResult $result): void
    {
        // Generate the URL to redirect the payer to after completion
        $redirectUrl = $this->getCompleteUrl($result);
        $result->setRedirectUrl($redirectUrl);

        // create a payment_link.completed event
        $event = new PendingEvent(
            object: $result->paymentLink,
            type: EventType::PaymentLinkCompleted,
            extraObjectData: ['session' => ModelNormalizer::toArray($result->getSession())],
        );
        $this->eventSpool->enqueue($event);

        // create a user notification for the completed payment link
        $customer = $result->getCustomer();
        $this->notificationSpool->spool(NotificationEventType::PaymentLinkCompleted, $result->paymentLink->tenant_id, $result->getSession()->id, $customer->id);

        // create a user notification for the payment
        if ($payment = $result->getPayment()) {
            $this->notificationSpool->spool(NotificationEventType::PaymentDone, $result->paymentLink->tenant_id, $payment->id, $customer->id);
        }

        // track the customer portal event
        $this->statsd->increment('billing_portal.complete_payment_link');
        $this->customerPortalEvents->track($customer, CustomerPortalEvent::CompletePaymentLink);
    }

    private function getCompleteUrl(PaymentLinkResult $result): string
    {
        $url = $result->paymentLink->after_completion_url;
        if (!$url) {
            return $this->urlGenerator->generate('customer_portal_payment_link_thanks', [
                'subdomain' => $result->paymentLink->tenant()->getSubdomainUsername(),
                'id' => $result->paymentLink->client_id,
                'payment' => $result->getPayment()?->client_id,
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        // add newly session IDs as query parameters to the redirect URL
        $query = [
            'invoiced_payment_link_session_id' => $result->getSession()->id,
        ];

        return CustomerPortalHelper::addQueryParametersToUrl($url, $query);
    }

    public function buildFormParametersFromFormSubmission(PaymentFlow $flow, array $parameters): array
    {
        // Look up the form submission data for this operation
        $formParameters = [];
        /** @var ?FlowFormSubmission $formSubmission */
        $formSubmission = FlowFormSubmission::where('reference', $flow->identifier)->oneOrNull();
        if ($formSubmission) {
            parse_str($formSubmission->data, $formParameters);
        }

        // Payment link adds the payment source parameters to the array
        return array_merge_recursive($formParameters, ['payment_source' => $parameters]);
    }
}
