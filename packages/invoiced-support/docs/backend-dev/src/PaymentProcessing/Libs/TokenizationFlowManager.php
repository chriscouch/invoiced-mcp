<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\RandomString;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Command\SignUpFormProcessor;
use App\CustomerPortal\Exceptions\SignUpFormException;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\SignUpForm;
use App\CustomerPortal\Models\SignUpPage;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Enums\TokenizationFlowStatus;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Forms\PaymentInfoFormBuilder;
use App\PaymentProcessing\Forms\PaymentInfoFormProcessor;
use App\PaymentProcessing\Models\FlowFormSubmission;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TokenizationFlowManager
{
    use CustomerPortalViewVariablesTrait;

    public function __construct(
        private AdyenClient $adyen,
        private UrlGeneratorInterface $urlGenerator,
        private PaymentInfoFormProcessor $paymentInfoFormProcessor,
        private SignUpFormProcessor $signUpFormProcessor,
        private SignInCustomer $signIn,
    ) {
    }

    /**
     * Creates a new tokenization flow.
     *
     * @throws ModelException
     */
    public function create(TokenizationFlow $flow): void
    {
        $flow->identifier = RandomString::generate(32, RandomString::CHAR_LOWER.RandomString::CHAR_NUMERIC);
        $flow->status = TokenizationFlowStatus::CollectPaymentDetails;
        $flow->saveOrFail();
    }

    public function setStatus(TokenizationFlow $flow, TokenizationFlowStatus $status): void
    {
        $flow->status = $status;

        if (TokenizationFlowStatus::Canceled == $status) {
            $flow->canceled_at = CarbonImmutable::now();
        }

        if (TokenizationFlowStatus::Succeeded == $status || TokenizationFlowStatus::Failed == $status) {
            $flow->completed_at = CarbonImmutable::now();
        }

        $flow->saveOrFail();
    }

    /**
     * @throws FormException|SignUpFormException
     */
    public function handleCompletePage(CustomerPortal $portal, TokenizationFlow $flow, Request $request): ?Response
    {
        if (TokenizationFlowStatus::Succeeded == $flow->status) {
            return null;
        }

        // Check if further action is needed to complete
        // eg pass the payment details to Adyen.

        // Handle Adyen 3DS redirect response
        $needsReconciliation = false;
        $completeParameters = [];
        if ($request->query->has('redirectResult')) {
            $completeParameters = $this->completeAdyen($flow, $request);
            $needsReconciliation = true;
        }

        // Complete the payment action when needed
        if ($needsReconciliation) {
            return $this->reconcile($portal, $flow, $completeParameters, $request);
        }

        return null;
    }

    /**
     * @throws FormException
     */
    private function completeAdyen(TokenizationFlow $flow, Request $request): array
    {
        try {
            $result = $this->adyen->submitPaymentDetails([
                'details' => [
                    'redirectResult' => $request->query->get('redirectResult'),
                ],
            ]);
        } catch (IntegrationApiException $e) {
            throw new FormException($e->getMessage());
        }

        // If the payment is completed (no action required) then save the result for future reconciliation
        /* @deprecated we are not interested in authorization requests */
        if (isset($result['pspReference'])) {
            $model = new AdyenPaymentResult();
            $model->reference = $flow->identifier;
            $model->result = (string) json_encode($result);
            $model->saveOrFail();
        }

        $flow->payment_method = PaymentMethodType::Card;
        $flow->saveOrFail();

        return [
            'payment_method' => 'card',
            'reference' => $flow->identifier,
            'shopperReference' => $result['additionalData']['tokenization.shopperReference'],
        ];
    }

    /**
     * @throws FormException|SignUpFormException
     */
    private function reconcile(CustomerPortal $portal, TokenizationFlow $flow, array $parameters, Request $request): ?Response
    {
        if ($signUpPage = $flow->sign_up_page) {
            return $this->reconcileSignUpPage($portal, $flow, $signUpPage, $parameters, $request);
        }

        return $this->reconcileSource($portal, $flow, $parameters, $request);
    }

    /**
     * @throws SignUpFormException
     */
    private function reconcileSignUpPage(CustomerPortal $portal, TokenizationFlow $flow, SignUpPage $signUpPage, array $parameters, Request $request): Response
    {
        // Look up the form submission data for this operation
        $formParameters = [];
        $formSubmission = FlowFormSubmission::where('reference', $flow->identifier)->oneOrNull();
        if ($formSubmission) {
            parse_str($formSubmission->data, $formParameters);
        }

        // Sign up page adds the payment source parameters to the array
        $formParameters = array_merge_recursive($formParameters, ['payment_source' => $parameters]);

        $form = new SignUpForm($signUpPage, $portal->company());
        if ($customer = $flow->customer) {
            $form->setCustomer($customer);
        }

        [$customer, $subscription] = $this->signUpFormProcessor->handleSubmit($form, $formParameters, (string) $request->getClientIp(), (string) $request->headers->get('User-Agent'));

        $this->setStatus($flow, TokenizationFlowStatus::Succeeded);

        // sign the newly created customer into the customer portal
        $response = new RedirectResponse($form->getThanksUrl($customer, $subscription));

        // return thanks url
        return $this->signIn->signIn($customer, $response);
    }

    /**
     * @throws FormException
     */
    private function reconcileSource(CustomerPortal $portal, TokenizationFlow $flow, array $parameters, Request $request): ?Response
    {
        // Currently supports reconciling:
        // - Add Payment Method page
        // - Sign Up Pages
        // - TODO: Estimate Approval Form

        // Look up the form submission data for this operation
        $formParameters = [];
        $formSubmission = FlowFormSubmission::where('reference', $flow->identifier)->oneOrNull();
        if ($formSubmission) {
            parse_str($formSubmission->data, $formParameters);
        }

        // Payment info form merges the payment source parameters into the array
        $formParameters = array_merge($formParameters, $parameters);

        $builder = new PaymentInfoFormBuilder($portal->getPaymentFormSettings());
        if ($customer = $flow->customer) {
            $builder->setCustomer($customer);
        }

        if ($methodType = $flow->payment_method) {
            $paymentMethod = PaymentMethod::instance($portal->company(), $methodType->toString());
            $builder->setMethod($paymentMethod);
        }

        // Process the form submission with the newly tokenized payment method
        $source = $this->paymentInfoFormProcessor->handleSubmit($builder->build(), $formParameters);

        // Check if the payment source needs to be verified
        if ($source->needsVerification()) {
            $this->setStatus($flow, TokenizationFlowStatus::ActionRequired);

            return new RedirectResponse(
                $this->generatePortalUrl(
                    $portal,
                    'customer_portal_verify_bank_account_form',
                    [
                        'id' => $flow->customer?->client_id,
                        'bankAccountId' => $source->id(),
                    ]
                ),
            );
        }

        $this->setStatus($flow, TokenizationFlowStatus::Succeeded);

        // redirect to payment form if set in cookie
        // useful for instant bank account verifications
        $session = $request->getSession();
        if ($session->get('payment_form_return')) {
            return new RedirectResponse($this->returnToPaymentForm($portal, $session, $source));
        }

        return null;
    }
}
