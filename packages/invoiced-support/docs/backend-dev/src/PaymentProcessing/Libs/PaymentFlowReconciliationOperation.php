<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\CashApplication\Models\Payment;
use App\Chasing\Models\PromiseToPay;
use App\CustomerPortal\Command\PaymentLinkProcessor;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalContext;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Exceptions\ChargeDeclinedException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Forms\PaymentFormProcessor;
use App\PaymentProcessing\Models\FlowFormSubmission;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentFlowReconciliationOperation
{
    use CustomerPortalViewVariablesTrait;

    public function __construct(
        private readonly PaymentLinkProcessor  $paymentLinkProcessor,
        private readonly PaymentFormProcessor  $paymentFormProcessor,
        private readonly SignInCustomer        $signIn,
        private readonly PaymentFlowReconcile  $paymentFlowReconcile,
        private readonly CustomerPortalContext  $customerPortalContext,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function reconcile(PaymentFlow $flow, array $parameters): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        if (PaymentMethodType::Affirm === $flow->payment_method || PaymentMethodType::Klarna === $flow->payment_method) {
            $result = AdyenPaymentResult::where('reference', $flow->identifier)
                ->sort('created_at DESC')
                ->one();

            $data = PaymentFlowReconcileData::fromAdyenResult($result, $flow->getAmount());
            try {
                $payment = $this->paymentFlowReconcile->doReconcile($flow, $data, $flow->payment_method->toString());

                return $this->buildSuccessResponse($payment, $portal, $flow);
            } catch (ChargeDeclinedException|FormException|PaymentLinkException|ChargeException $e) {

                return $this->buildFailureResponse($flow, $portal, $e);
            }
        }


        if ($paymentLink = $flow->payment_link) {
            try {
                return $this->completePaymentLink($flow, $paymentLink, $parameters);
            } catch (PaymentLinkException $e) {

                return $this->buildFailureResponse($flow, $portal, $e);
            }
        }


        try {
            $payment = $this->completePaymentForm($portal, $flow, $parameters);

            return $this->buildSuccessResponse($payment, $portal, $flow);
        } catch (ChargeDeclinedException|FormException|PaymentLinkException|ChargeException $e) {

            return $this->buildFailureResponse($flow, $portal, $e);
        }

    }

    private function buildFailureResponse(PaymentFlow $flow, CustomerPortal $portal, ChargeDeclinedException|FormException|PaymentLinkException|ChargeException $e): Response
    {
        $redirectUrl = $flow->payment_link ? $flow->payment_link->url . '?errors[]=' . $e->getMessage() : $this->generatePortalUrl($portal, 'customer_portal_payment_form', [
            'errors' => [$e->getMessage()],
        ]);

        return new RedirectResponse($redirectUrl);
    }

    private function buildSuccessResponse(Payment|PromiseToPay|null $payment, CustomerPortal $portal, PaymentFlow $flow): Response
    {
        // Figure out where to redirect
        if ($payment instanceof Payment) {
            $redirectUrl = $this->generatePortalUrl($portal, 'customer_portal_payment_thanks', [
                'id' => $payment->client_id,
            ]);
        } elseif ($payment instanceof PromiseToPay) {
            $redirectUrl = $this->generatePortalUrl($portal, 'customer_portal_expected_payment_thanks', [
                'customer' => $flow->customer?->client_id,
                'method' => $flow->payment_method?->toString(),
            ]);
        } else {
            if (PaymentFlowStatus::Succeeded === $flow->status) {
                throw new FormException('Your payment was successfully processed but could not be saved. Please do not retry payment.');
            } else {
                throw new FormException('Payment Refused.');
            }
        }

        return new RedirectResponse($redirectUrl);
    }


    /**
     * @throws PaymentLinkException
     */
    private function completePaymentLink(PaymentFlow $flow, PaymentLink $paymentLink, array $parameters): Response
    {
        $formParameters = $this->paymentLinkProcessor->buildFormParametersFromFormSubmission($flow, $parameters);

        // Handle the payment link form submission
        $result = $this->paymentLinkProcessor->handleSubmit($paymentLink, $formParameters);

        // Figure out where to redirect
        $response = new RedirectResponse($result->getRedirectUrl());

        // Sign in the customer that completed the payment link
        return $this->signIn->signIn($result->getCustomer(), $response, true);
    }

    /**
     * @throws FormException
     */
    private function completePaymentForm(CustomerPortal $portal, PaymentFlow $flow, array $parameters): Payment|PromiseToPay|null
    {
        // Look up the form submission data for this operation
        $formParameters = [];
        $formSubmission = FlowFormSubmission::where('reference', $flow->identifier)->oneOrNull();
        if ($formSubmission) {
            parse_str($formSubmission->data, $formParameters);

            // no tokenization behind this point
            // TODO: this is for adyen payment only, when implementing other gateways - this should be separated
            unset($formParameters['make_default']);
            unset($formParameters['enroll_autopay']);
        }

        // Payment form merges the payment source parameters into the array
        $formParameters = array_merge($formParameters, $parameters);

        // Handle the payment form submission
        $inputBag = new InputBag($formParameters);
        $form = $this->paymentFormProcessor->makePaymentFormPost($portal, $inputBag);

        return $this->paymentFormProcessor->handleSubmit($form, $formParameters);
    }
}
