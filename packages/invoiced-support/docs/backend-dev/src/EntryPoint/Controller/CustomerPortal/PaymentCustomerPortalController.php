<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Exception\ModelNotFoundException;
use App\Core\Orm\Query;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalHelper;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenPricingEngine;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Libs\AdyenPaymentResultLock;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Models\AdyenAffirmCapture;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\Integrations\Adyen\Operations\SaveAdyenPayment;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentPlans\Libs\ApprovePaymentPlan;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentProcessing\Enums\PaymentAmountOption;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Exceptions\AdyenCardException;
use App\PaymentProcessing\Exceptions\ChargeDeclinedException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Forms\PaymentAmountFormBuilder;
use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Forms\PaymentFormProcessor;
use App\PaymentProcessing\Forms\PaymentItemsFormBuilder;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\PaymentProcessing\Libs\PaymentMethodViewFactory;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\FlowFormSubmission;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ViewVariables\PaymentAmountFormViewVariables;
use App\PaymentProcessing\ViewVariables\PaymentFormViewVariables;
use App\PaymentProcessing\ViewVariables\PaymentItemFormViewVariables;
use Exception;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class PaymentCustomerPortalController extends AbstractCustomerPortalController
{
    #[Route(path: '/payments', name: 'list_payments', methods: ['GET'])]
    public function listPayments(Request $request): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = $portal->getSignedInCustomer();
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        // parse the filter parameters
        $query = $this->buildPaymentsQuery($portal->getAllowCustomerIds(), $request);
        $total = $query->count();
        $filter = $this->parseFilterParams($request, '/payments', $total, 'date DESC');

        // execute the query
        $transactions = $this->getPayments($query, $customer, $filter['perPage'], $filter['page'], $filter['sort'], $request);

        // build the totals
        $currency = $customer->calculatePrimaryCurrency();
        $totals = $this->calculateTotals($transactions, ['amount'], $currency);

        return $this->render('customerPortal/payments/list.twig', [
            'hasEstimates' => $portal->hasEstimates(),
            'total' => $total,
            'results' => $transactions,
            'filter' => $filter,
            'sort' => $filter['sort'],
            'totals' => $totals,
        ]);
    }

    #[Route(path: '/pay/select_items', name: 'payment_select_items_form', methods: ['GET', 'POST'])]
    public function paymentSelectItemsForm(Request $request, TranslatorInterface $translator, PaymentItemFormViewVariables $viewVariables, SignInCustomer $signIn): Response
    {
        $inputBag = $request->isMethod('POST') ? $request->request : $request->query;

        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();

        // Locate the customer that is selecting items to pay
        $customer = $portal->getSignedInCustomer();

        $response = new Response();
        if (!$customer && $inputBag->has('customer')) {
            if ($customer = Customer::findClientId((string) $inputBag->get('customer'))) {
                // Check if the viewer has permission when "Require Authentication" is enabled
                if ($resp = $this->mustLogin($customer, $request)) {
                    return $resp;
                }

                // Sign the customer into the customer portal temporarily
                $response = $signIn->signIn($customer, $response, true);
            }
        }
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        $builder = new PaymentItemsFormBuilder($portal->getPaymentFormSettings(), $customer, $portal->getAllowCustomerIds());

        // Pre-select estimates from estimate #
        foreach ($inputBag->all('Quote') as $number) {
            $builder->selectEstimateByNumber($number);
        }

        // Pre-select invoices from invoice #
        foreach ($inputBag->all('Invoice') as $number) {
            $builder->selectInvoiceByNumber($number);
        }

        // Pre-select credit notes from credit note #
        foreach ($inputBag->all('CreditNote') as $number) {
            $builder->selectCreditNoteByNumber($number);
        }

        // Pre-select advance payment
        if ('1' == $inputBag->get('advance')) {
            $builder->selectAdvancePayment();
        }

        // Pre-select advance payment
        if ('1' == $inputBag->get('CreditBalance')) {
            $builder->selectCreditBalance();
        }

        $form = $builder->build();

        // if not, show a specific error message
        if (!$form->hasNonCreditItems()) {
            return $this->render('customerPortal/payments/message.twig', [
                'message' => $translator->trans('messages.nothing_due', [], 'customer_portal'),
            ], $response);
        }
        // If there is only 1 choice then redirect to payment amount selection page
        if (1 === $form->choicesCount() && !$inputBag->get('force_choice')) {
            $response2 = $this->redirectToRoute('customer_portal_payment_select_amounts_form', [
                'subdomain' => $company->getSubdomainUsername(),
                'Invoice' => $form->getAvailableInvoiceClientIds(),
                'Quote' => $form->getAvailableEstimateClientIds(),
                'CreditNote' => $form->getAvailableCreditNoteClientIds(),
                'CreditBalance' => $form->creditBalance->isPositive() ? '1' : '0',
                'advance' => $form->advancePayment ? '1' : '0',
            ]);

            // in case we did sign in above - we transfer cookies
            foreach ($response->headers->getCookies() as $cookie) {
                $response2->headers->setCookie($cookie);
            }

            return $response2;
        }

        $this->statsd->increment('billing_portal.select_payment_items');

        return $this->render('customerPortal/payments/select-items.twig', $viewVariables->build($form, $request), $response);
    }

    #[Route(path: '/pay/select_amounts', name: 'payment_select_amounts_form', methods: ['GET', 'POST'])]
    public function paymentSelectAmountsForm(Request $request, PaymentAmountFormViewVariables $viewVariables): Response
    {
        $inputBag = $request->isMethod('POST') ? $request->request : $request->query;
        // we clean up session data that might be left over from previous payment attempts
        $request->getSession()->set('payment_form_return', '');
        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();

        // Locate the customer that is selecting items to pay
        $customer = $portal->getSignedInCustomer();
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        $builder = new PaymentAmountFormBuilder($portal->getPaymentFormSettings(), $customer);

        try {
            // Select estimates from estimate #
            foreach ($inputBag->all('Quote') as $clientId) {
                $builder->addEstimateByClientId($clientId);
            }

            // Select invoices from invoice #
            foreach ($inputBag->all('Invoice') as $clientId) {
                $builder->addInvoiceByClientId($clientId);
            }

            // Select credit notes from credit note #
            foreach ($inputBag->all('CreditNote') as $clientId) {
                $builder->addCreditNoteByClientId($clientId);
            }

            // Select credit balance
            if ('1' == $inputBag->get('CreditBalance')) {
                $builder->addCreditBalance();
            }

            // Select advance payment
            if ('1' == $inputBag->get('advance')) {
                $builder->addAdvancePayment();
            }

            $form = $builder->build();
        } catch (FormException $e) {
            return $this->render('customerPortal/payments/message.twig', [
                'message' => $e->getMessage(),
            ]);
        }

        // If there are no amount choices then redirect to payment page
        if (!$form->hasAvailableChoices()) {
            return $this->redirectToRoute('customer_portal_payment_form', [
                'subdomain' => $company->getSubdomainUsername(),
                'invoices' => $form->getAvailableInvoiceClientIds(),
                'estimates' => $form->getAvailableEstimateClientIds(),
                'creditNotes' => $form->getAvailableCreditNoteClientIds(),
            ]);
        }

        $this->statsd->increment('billing_portal.select_payment_amounts');

        return $this->render('customerPortal/payments/select-amounts.twig', $viewVariables->build($form, $request));
    }

    #[Route(path: '/pay', name: 'payment_form', methods: ['GET', 'POST'])]
    public function paymentForm(Request $request, PaymentMethodViewFactory $viewFactory, TranslatorInterface $translator, PaymentFormViewVariables $viewVariables, SignInCustomer $signIn, PaymentFlowManager $paymentFlowManager): Response
    {
        if ($request->isMethod('POST')) {
            $inputBag = $request->request;
        } elseif ($response = $request->getSession()->get('payment_form_return')) {
            $inputBag = new InputBag($response);
        } else {
            $inputBag = $request->query;
        }

        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();

        $response = new Response();
        $customer = $portal->getSignedInCustomer();
        if ($inputBag->has('customer')) {
            if ($customer = Customer::findClientId((string) $inputBag->get('customer'))) {
                // Check if the viewer has permission when "Require Authentication" is enabled
                if ($resp = $this->mustLogin($customer, $request)) {
                    return $resp;
                }

                // Sign the customer into the customer portal temporarily
                $response = $signIn->signIn($customer, $response, true);
            }
        }

        // Go to the select item page when no payment items are selected.
        if (0 == count($inputBag->all())) {
            if (!$customer) {
                throw new NotFoundHttpException();
            }

            $response2 = $this->redirectToRoute('customer_portal_payment_select_items_form', [
                'subdomain' => $company->getSubdomainUsername(),
            ]);

            // in case we did sign in above - we transfer cookies
            foreach ($response->headers->getCookies() as $cookie) {
                $response2->headers->setCookie($cookie);
            }

            return $response2;
        }

        try {
            $form = $this->buildPaymentForm($portal, $inputBag);
        } catch (FormException $e) {
            return $this->render('customerPortal/payments/message.twig', [
                'message' => $e->getMessage(),
            ], $response);
        }

        // If a single invoice is selected then check if it has a payment plan
        // that needs approval. A different view is rendered for this scenario.
        if (1 === count($form->paymentItems)) {
            // check for a payment plan that needs approval
            $invoice = $form->paymentItems[0]->document;
            if ($invoice instanceof Invoice) {
                $paymentPlan = $invoice->paymentPlan();
                if ($paymentPlan && PaymentPlan::STATUS_PENDING_SIGNUP == $paymentPlan->status) {
                    return $this->approvePaymentPlanForm($invoice, $response);
                }
            }
        }

        $pendingAmount = Money::zero($form->currency);
        foreach ($form->paymentItems as $item) {
            if ($item->document instanceof ReceivableDocument) {
                $pendingAmount = $paymentFlowManager->getBlockingAmount($item->document, $item->amount);
            }
        }

        if (!$pendingAmount->isZero()) {
            return $this->render('customerPortal/payments/message.twig', [
                'message' => strip_tags($translator->trans('messages.payment_pending', ['%amount%' => $pendingAmount], 'customer_portal')),
            ], $response);
        }

        $errors = $request->query->all('errors');
        $request->query->remove('errors');
        $request->getSession()->set('payment_form_return', $inputBag->all());

        // Show payment form
        return $this->render('customerPortal/payments/pay.twig', $viewVariables->build($form, $viewFactory, $portal, $errors), $response);
    }

    /**
     * @throws FormException
     */
    private function buildPaymentForm(CustomerPortal $portal, InputBag $inputBag): PaymentForm
    {
        $builder = new PaymentFormBuilder($portal);

        $currency = (string) $inputBag->get('currency');
        $amountOptions = $inputBag->all('amount_type');
        $amounts = $inputBag->all('amount');

        // Add estimates to the form
        $estimateIds = $inputBag->all('estimates');
        if (!$estimateIds) {
            $estimateIds = array_filter(explode(',', (string) $inputBag->get('estimates')));
        }
        foreach ($estimateIds as $clientId) {
            $amountOption = $amountOptions['estimates'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['estimates'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addEstimateFromClientId($clientId, $amountOption, $amount);
        }

        // Add the invoices to the form
        $invoiceIds = $inputBag->all('invoices');
        if (!is_array($invoiceIds)) {
            $invoiceIds = array_filter(explode(',', (string) $inputBag->get('invoices')));
        }
        foreach ($invoiceIds as $clientId) {
            $amountOption = $amountOptions['invoices'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['invoices'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addInvoiceFromClientId($clientId, $amountOption, $amount);
        }

        // Add credit notes to the form
        // NOTE: Credit notes should always be added last but before advance payments
        $creditNoteIds = $inputBag->all('creditNotes');
        if (!is_array($creditNoteIds)) {
            $creditNoteIds = array_filter(explode(',', (string) $inputBag->get('creditNotes')));
        }
        foreach ($creditNoteIds as $clientId) {
            $amountOption = $amountOptions['creditNotes'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['creditNotes'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addCreditNoteFromClientId($clientId, $amountOption, $amount);
        }

        // Add credit balance to the form
        // NOTE: Credit balance should always be added last but before advance payments
        if ($clientId = (string) $inputBag->get('creditBalance')) {
            $amountOption = $amountOptions['creditBalance'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['creditBalance'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addCreditBalanceFromClientId($clientId, $amountOption, $amount);
        }

        // Add advance payment to the form
        // NOTE: Advance payments should always be added last
        if ($clientId = (string) $inputBag->get('advance')) {
            $amountOption = $amountOptions['advance'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['advance'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addAdvancePaymentFromClientId($clientId, $amountOption, $amount);
        }

        // set the payment method
        if ($methodId = (string) $inputBag->get('method')) {
            $builder->setSelectedPaymentMethod($methodId);
        }

        return $builder->build();
    }

    #[Route(path: '/api/pay', name: 'submit_payment_api', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function submitPaymentApi(Request $request, PaymentFormProcessor $processor, SaveAdyenPayment $saveAdyenPayment): Response
    {
        $result = null;
        $portal = $this->customerPortalContext->getOrFail();

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_invoice_payment', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $input = $request->request->all();
        // handle the payment
        try {
            $form = $processor->makePaymentFormPost($portal, $request->request);
            $result = $processor->handleSubmit($form, $input);
        } catch (FormException|ChargeDeclinedException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        } catch (AdyenCardException) {
            try {
                /** @var PaymentFlow $flow */
                $flow = PaymentFlow::where('identifier', $input['reference'])
                    ->one();
                /** @var AdyenPaymentResult $adyenResult */
                $adyenResult = AdyenPaymentResult::where('reference', $input['reference'])
                    ->one();
                $data = json_decode($adyenResult->result, true);
                $result = $saveAdyenPayment->reconcileAdyenResult($flow, $adyenResult, $data, $flow->getAmount());
            } catch (ModelNotFoundException|AdyenReconciliationException) {
                //ignore payment remain null
            }

            if (!$result) {
                return new JsonResponse([
                    'error' => 'Your payment was successfully processed but could not be saved. Please do not retry payment.',
                    400,
                ]);
            }
        }

        if ($result instanceof Payment) {
            $redirectUrl = $this->generatePortalUrl($portal, 'customer_portal_payment_thanks', [
                'id' => $result->client_id,
            ]);
        } else {
            $redirectUrl = $this->generatePortalUrl($portal, 'customer_portal_expected_payment_thanks', [
                'customer' => $form->customer->client_id,
                'method' => $form->method?->id,
            ]);
        }

        return new JsonResponse([
            'url' => $redirectUrl,
        ]);
    }

    #[Route(path: '/payments/expected_payment_thanks', name: 'expected_payment_thanks', methods: ['GET', 'POST'])]
    public function expectedPaymentThanks(Request $request): Response
    {
        $inputBag = $request->isMethod('POST') ? $request->request : $request->query;
        $id = (string) $inputBag->get('customer');
        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        if ('zero_amount' == $request->query->get('method')) {
            return $this->render('customerPortal/payments/thanks.twig', [
                'zeroAmount' => true,
                'customer' => $customer,
            ]);
        }

        return $this->render('customerPortal/payments/thanks.twig', [
            'customer' => $customer,
            'googleAnalyticsEvents' => [['category' => 'Customer Portal', 'action' => 'Promise To Pay', 'label' => $customer->name]],
        ]);
    }

    #[Route(path: '/payments/{id}/thanks', name: 'payment_thanks', methods: ['GET'])]
    public function paymentThanks(Request $request, string $id): Response
    {
        $payment = Payment::findClientId($id);
        if (!$payment) {
            throw new NotFoundHttpException();
        }

        $pending = null == $payment->charge || Charge::PENDING == $payment->charge->status;
        $amount = $payment->getAmount();

        return $this->render('customerPortal/payments/thanks.twig', [
            'receiptUrl' => $payment->pdf_url.'?locale='.$request->getLocale(),
            'pending' => $pending,
            'currency' => $amount->currency,
            'amount' => $amount->toDecimal(),
            'googleAnalyticsEvents' => [['category' => 'Customer Portal', 'action' => 'Submitted Payment', 'label' => $payment->method]],
        ]);
    }

    #[Route(path: '/flows/{id}/complete', name: 'payment_flow_complete', methods: ['GET'])]
    public function paymentFlowComplete(Request $request, TranslatorInterface $translator, PaymentFlowManager $flowManager, string $id): Response
    {
        $paymentFlow = PaymentFlow::where('identifier', $id)->oneOrNull();
        if (!$paymentFlow) {
            throw new NotFoundHttpException();
        }

        // Complete the payment flow, when needed
        try {
            $response = $flowManager->handleCompletePage($paymentFlow, $request);
        } catch (FormException|PaymentLinkException $e) {
            return $this->render('customerPortal/error.twig', [
                'message' => $e->getMessage(),
            ]);
        }

        if ($response) {
            return $response;
        }

        if (PaymentFlowStatus::Failed == $paymentFlow->status || PaymentFlowStatus::Canceled == $paymentFlow->status) {
            return $this->render('customerPortal/payments/message.twig', [
                'message' => $translator->trans('messages.payment_failed', [], 'customer_portal'),
            ]);
        }

        // Look for a completed payment. It might not be reconciled yet.
        $charge = Charge::where('payment_flow_id', $paymentFlow)
            ->sort('id DESC')
            ->oneOrNull();
        $payment = $charge?->payment;
        $receiptUrl = $payment ? $payment->pdf_url.'?locale='.$request->getLocale() : null;

        return $this->render('customerPortal/payments/thanks.twig', [
            'receiptUrl' => $receiptUrl,
            'pending' => PaymentFlowStatus::Processing == $paymentFlow->status,
            'currency' => $paymentFlow->currency,
            'amount' => $paymentFlow->amount,
            'googleAnalyticsEvents' => [['category' => 'Customer Portal', 'action' => 'Submitted Payment', 'label' => $paymentFlow->payment_method?->toString()]],
        ]);
    }

    #[Route(path: '/flows/{id}/canceled', name: 'payment_flow_canceled', methods: ['GET'])]
    public function paymentFlowCanceled(PaymentFlowManager $flowManager, TranslatorInterface $translator, string $id): Response
    {
        /** @var ?PaymentFlow $paymentFlow */
        $paymentFlow = PaymentFlow::where('identifier', $id)->oneOrNull();
        if (!$paymentFlow) {
            throw new NotFoundHttpException();
        }

        // Redirect to the return URL instead of our cancellation page, if known
        if ($paymentFlow->return_url) {
            return $this->redirect($paymentFlow->return_url);
        }


        $paymentFlow->status = PaymentFlowStatus::Canceled;
        $paymentFlow->save();

        return $this->render('customerPortal/payments/message.twig', [
            'message' => $translator->trans('messages.payment_canceled', [], 'customer_portal'),
        ]);
    }

    private function approvePaymentPlanForm(Invoice $invoice, Response $response): Response
    {
        $installments = [];

        $balance = Money::fromDecimal($invoice->currency, $invoice->balance);

        /** @var PaymentPlan $paymentPlan */
        $paymentPlan = $invoice->paymentPlan();
        foreach ($paymentPlan->installments as $installment) {
            // do not display paid installments
            if (!$installment->balance) {
                continue;
            }

            $amount = Money::fromDecimal($invoice->currency, $installment->amount);
            $installments[] = [
                'amount' => $amount->toDecimal(),
                'date' => date($invoice->tenant()->date_format, $installment->date),
            ];
        }

        $needsPaymentSource = $invoice->autopay && !$invoice->customer()->payment_source;

        return $this->render('customerPortal/paymentPlans/approve.twig', [
            'currency' => $balance->currency,
            'balance' => $balance->toDecimal(),
            'installments' => $installments,
            'approved' => PaymentPlan::STATUS_PENDING_SIGNUP != $paymentPlan->status,
            'needsPaymentSource' => $needsPaymentSource,
            'url' => $this->generatePortalContextUrl(
                'customer_portal_approve_payment_plan',
                [
                    'id' => $invoice->client_id,
                ],
            ),
            'invoiceNumber' => $invoice->number,
        ], $response);
    }

    #[Route(path: '/invoices/{id}/payment/payment_plan_signup', name: 'approve_payment_plan', methods: ['POST'])]
    public function approvePaymentPlan(Request $request, SignInCustomer $signIn, ApprovePaymentPlan $approvePaymentPlan, string $id): Response
    {
        if (!$request->request->get('accepted')) {
            throw new UnauthorizedHttpException('');
        }

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_approve_payment_plan', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        // add the invoices
        $invoice = Invoice::findClientId($id);

        if (!$invoice || $invoice->voided) {
            throw new NotFoundHttpException();
        }

        $paymentPlan = $invoice->paymentPlan();
        if (!($paymentPlan instanceof PaymentPlan)) {
            throw new NotFoundHttpException();
        }

        $approvePaymentPlan->approve($paymentPlan, (string) $request->getClientIp(), (string) $request->headers->get('User-Agent'));

        $portal = $this->customerPortalContext->getOrFail();
        $customer = $invoice->customer();
        if ($portal->enabled()) {
            // sign the customer into the customer portal
            // and redirect to my account
            $response = new RedirectResponse(
                $this->generatePortalUrl($portal, 'customer_portal_account')
            );
            $signIn->signIn($customer, $response);
        } else {
            // go back to the invoice
            $response = new RedirectResponse($this->generatePortalUrl($portal, 'customer_portal_view_invoice', [
                'id' => $invoice->client_id,
            ]));
        }

        // check if customer needs to enter payment info and redirect to payment info page
        if ($invoice->autopay && !$customer->payment_source) {
            $response->setTargetUrl($this->generatePortalUrl($portal, 'customer_portal_update_payment_info_form', [
                'id' => $customer->client_id,
            ]));

            return $response;
        }

        return $response;
    }

    /**
     * @param int[] $ids
     */
    private function buildPaymentsQuery(array $ids, Request $request): Query
    {
        $query = Transaction::query()
            ->where('customer', $ids)
            ->where('(type="'.Transaction::TYPE_CHARGE.'" OR type="'.Transaction::TYPE_PAYMENT.'" OR payment_id IS NOT NULL)')
            ->where('parent_transaction IS NULL');

        $this->addDateRangeToQuery($query, $request);

        return $query;
    }

    /**
     * Gets the payments for a customer.
     */
    private function getPayments(Query $query, Customer $customer, int $perPage, int $page, string $sort, Request $request): array
    {
        --$page;

        $query->start($perPage * $page)
            ->sort($sort);

        $transactions = [];
        $dateFormat = $customer->tenant()->date_format;
        /** @var Transaction $transaction */
        foreach ($query->first($perPage) as $transaction) {
            if ($transaction->payment) {
                $amount = $transaction->paymentAmount();
            } else {
                $paid = $transaction->paymentAmount();
                $refunded = $transaction->amountRefunded();
                $amount = $paid->subtract($refunded);
            }

            $method = $transaction->getMethod()->toString();
            $paymentSource = $transaction->payment_source;
            if ($paymentSource) {
                $method = $paymentSource->toString(true);
            }

            $documentUrl = null;
            $documentNumber = null;
            if ($invoice = $transaction->invoice()) {
                $documentUrl = $this->generatePortalContextUrl('customer_portal_view_invoice', [
                    'id' => $invoice->client_id,
                ]);
                $documentNumber = $invoice->number;
            }

            if ($estimate = $transaction->estimate()) {
                $documentUrl = $this->generatePortalContextUrl('customer_portal_view_estimate', [
                    'id' => $estimate->client_id,
                ]);
                $documentNumber = $estimate->number;
            }

            $transactions[] = [
                'currency' => $amount->currency,
                'amount' => $amount->toDecimal(),
                '_amount' => $amount,
                'date' => date($dateFormat, $transaction->date),
                'document' => $documentNumber ? [
                    'number' => $documentNumber,
                    'url' => $documentUrl,
                ] : null,
                'icon' => $paymentSource ? CustomerPortalHelper::getPaymentSourceIcon($paymentSource) : null,
                'method' => $method,
                'status' => $transaction->status,
                'pdf_url' => $transaction->pdf_url.'?locale='.$request->getLocale(),
                'failure_reason' => Transaction::STATUS_FAILED == $transaction->status ? $transaction->failure_reason : null,
            ];
        }

        return $transactions;
    }

    private function getAdyenMerchantAccount(): MerchantAccount
    {
        $merchantAccountQuery = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID);
        //@TODO - store should be specified in scope of INV-114
//        if (isset($params['store'])) {
//            $merchantAccountQuery->where('gateway_id', $params['store']);
//        }
        return $merchantAccountQuery->one();
    }

    #[Route(path: '/api/adyen/payments', name: 'adyen_payment_api', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function adyenPaymentApi(Request $request, AdyenClient $adyen, AdyenGateway $adyenGateway, PaymentFlowManager $paymentFlowManager, SaveAdyenPayment $saveAdyenPayment): Response
    {
        $params = $request->request->all();
        $formData = isset($params['_formData']) && is_string($params['_formData']) ? $params['_formData'] : '';
        unset($params['_formData']);
        $formDataArray = [];
        parse_str($formData, $formDataArray);

        if ($formDataArray['make_default'] ?? $formDataArray['enroll_autopay'] ?? false) {
            $params['recurringProcessingModel'] = 'CardOnFile';
            $params['storePaymentMethod'] = true;
            // https://docs.adyen.com/api-explorer/Checkout/latest/post/payments#request-shopperInteraction
            // or we can use ContAuth here?
            $params['shopperInteraction'] = 'Ecommerce';

            $formData = http_build_query($formDataArray); // re-build the form data for Adyen
        }

        $merchantAccount = $this->getAdyenMerchantAccount();

        /** @var string $reference */
        $reference = $params['reference'];

        if ($merchantAccount->tenant()->features->has('3ds_never')) {
            $params['authenticationData'] = [
                'attemptAuthentication' => 'never',
            ];
        }

        try {
            // we always save form submission together with payment flow (if exists)
            if ($formData) {
                FlowFormSubmission::saveResult($reference, $formData);
            }

            // no payment flow mean tokenization request
            /** @var ?PaymentFlow $flow */
            $flow = PaymentFlow::where('identifier', $reference)->oneOrNull();
            if ($flow) {
                $method = $formDataArray['payment_source']['method'] ?? $formDataArray['method'] ?? PaymentMethod::CREDIT_CARD;

                if ($flow->customer) {
                    $convenienceFee = ConvenienceFeeHelper::calculate(PaymentMethod::instance($flow->tenant(), $method), $flow->customer, $flow->getAmount());
                    $flow->applyConvenienceFee($convenienceFee);
                }

                // payment link ?? regular payment ?? fallback
                $flow->payment_method = PaymentMethodType::fromString($method);
                $flow->gateway = AdyenGateway::ID;
                $email = $formDataArray['receipt_email'] ?? null;
                if (is_string($email) && $email) {
                    $flow->email = $email;
                }
                $flow->merchant_account = $merchantAccount;
                $flow->saveOrFail();
            }

            // Calculate any custom pricing
            $pricingConfiguration = AdyenAccount::one()->pricing_configuration;
            if ($pricingConfiguration) {
                $amount = new Money($params['amount']['currency'], (int) $params['amount']['value']); /* @phpstan-ignore-line */

                // Look up card issuing country from Adyen
                $portal = $this->customerPortalContext->getOrFail();
                $company = $portal->company();
                $cardCountry = $company->country;
                $isAmex = false;
                if (isset($params['paymentMethod']['encryptedCardNumber'])) {
                    try {
                        $result = $adyen->getCardDetails([
                            'encryptedCardNumber' => $params['paymentMethod']['encryptedCardNumber'],
                            'merchantAccount' => $params['merchantAccount'],
                            'countryCode' => $params['countryCode'],
                        ]);

                        $cardCountry = $result['issuingCountryCode'] ?? $company->country;
                        foreach ($result['brands'] ?? [] as $brand) {
                            if ('amex' == $brand['type']) {
                                $isAmex = true;
                                break;
                            }
                        }
                    } catch (IntegrationApiException) {
                        // ignore any exceptions and fallback to company country
                    }
                }

                $fee = AdyenPricingEngine::priceCardTransaction($pricingConfiguration, $company, $amount, $cardCountry, $isAmex);
                if ($fee) {
                    $params['splits'] = $adyenGateway->makeSplits($merchantAccount, $amount, $fee);
                }
            }

            // Add chargeback logic
            $params['platformChargebackLogic'] = $adyenGateway->makeChargebackLogic($merchantAccount);

            $result = $adyen->createPayment($params);
        } catch (Exception $e) {
            $this->logger->error('Adyen Flow Error', ['exception' => $e]);

            return new JsonResponse([
                'error' => 'We could not process your payment at this time. Please try again later or contact support.',
                400,
            ]);
        }

        try {
            $paymentFlowManager->saveResult($reference, $result, $flow);
        } catch (ModelException) {
            return new JsonResponse([
                'error' => 'Your payment was successfully processed but could not be saved. Please do not retry payment.',
                400,
            ]);
        }

        if ($flow) {
            $saveAdyenPayment->reconcileFailedCharge($flow, $result);
        }

        return new JsonResponse($result);
    }


    #[Route(path: '/api/adyen/affirm', name: 'adyen_affirm', defaults: ['no_database_transaction' => true, 'method' => PaymentMethodType::Affirm->value], methods: ['POST'])]
    #[Route(path: '/api/adyen/klarna', name: 'adyen_klarna', defaults: ['no_database_transaction' => true, 'method' => PaymentMethodType::Klarna->value], methods: ['POST'])]
    public function adyenKlarna(Request $request, AdyenClient $adyen, SaveAdyenPayment $saveAdyenPayment, PaymentFlowReconcile $paymentFlowReconcile): Response
    {
        $params = $request->request->all();
        /** @var string $reference */
        $reference = $params['reference'];
        // no payment flow mean tokenization request
        /** @var PaymentFlow $flow */
        $flow = PaymentFlow::where('identifier', $reference)->one();

        $paymentLinkResult = null;
        $paymentLinkParameters = [];
        $amount = $flow->getAmount();
        $params['lineItems'] = GatewayHelper::makeKlarnaLineItems(
            array_filter(
                array_map(fn($application) => $application->invoice ?? $application->estimate ?? null,
                    $paymentFlowReconcile->getFlowApplications($flow, $amount, $paymentLinkResult, $paymentLinkParameters)
                )
            ), $amount);

        $result = $adyen->createPayment($params);

        /** @var ?string $shopperEmail */
        $shopperEmail = $params['shopperEmail'] ?? null;
        try {
            $flow->payment_method = PaymentMethodType::from($request->attributes->get('method'));
            $flow->gateway = AdyenGateway::ID;
            $flow->merchant_account = $this->getAdyenMerchantAccount();
            $flow->email = $shopperEmail;
            $flow->saveOrFail();
        } catch (ModelException) {
            return new JsonResponse([
                'error' => 'Your payment was successfully processed but could not be saved. Please do not retry payment.',
                400,
            ]);
        }

        if ('RedirectShopper' !== $result['resultCode']) {
            $saveAdyenPayment->reconcileFailedCharge($flow, $result);
        }

        $capture = new AdyenAffirmCapture();
        $capture->payment_flow = $flow;
        $capture->line_items = $params['lineItems'];
        $capture->saveOrFail();

        return new JsonResponse($result);
    }


    #[Route(path: '/api/adyen/payments/details', name: 'adyen_payment_details_api', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function adyenPaymentDetailsApi(Request $request, AdyenClient $adyen, PaymentFlowManager $paymentFlowManager, LockFactory $lockFactory): Response
    {
        try {
            $params = $request->request->all();
            unset($params['reference']);
            $reference = $request->request->getString('reference');

            $result = $adyen->submitPaymentDetails($params);

            // we create mutex lock to prevent reconciliation from the webhook happening the same second
            if (isset($result['pspReference'])) {
                $lock = new AdyenPaymentResultLock($result['pspReference'], $lockFactory);
                try {
                    $lock->acquire(AdyenPaymentResultLock::WRITE_ADYEN_TTL);
                } catch (LockAcquiringException $e) {
                    $this->logger->error('Failed to acquire accounting '.AdyenGateway::ID.':'.$result['pspReference'].' lock', ['exception' => $e]);
                }
            }

            // If the payment is completed (no action required) then save the result for future reconciliation
            $flow = PaymentFlow::where('identifier', $reference)->oneOrNull();
            $paymentFlowManager->saveResult($reference, $result, $flow);
        } catch (IntegrationApiException $e) {
            $this->logger->error('Adyen API Error', ['exception' => $e]);

            return new JsonResponse([
                'error' => 'An unknown error occurred',
            ]);
        }

        return new JsonResponse($result);
    }

    //gets current payment flow status
    #[Route(path: '/api/flows/{id}/payable', name: 'flow_payable', defaults: ['no_database_transaction' => true], methods: ['GET'])]
    public function getPaymentFlow(string $id, PaymentFlowManager $paymentFlowManager, TranslatorInterface $translator): Response
    {
        /** @var PaymentFlow $flow */
        $flow = PaymentFlow::where('identifier', $id)->one();

        $paymentAmount = $flow->getAmount();
        $tenant = $flow->tenant();

        if (PaymentFlowStatus::CollectPaymentDetails !== $flow->status) {
            return new JsonResponse(['message' => $translator->trans('messages.payment_pending', [
                '%amount%' => MoneyFormatter::get()->currencyFormat(
                    $paymentAmount->toDecimal(),
                    $flow->currency,
                    $tenant->moneyFormat()
                ),
                $tenant->moneyFormat()
            ], 'customer_portal')], 400);
        }

        /** @var PaymentFlowApplication[] $applications */
        $applications = PaymentFlowApplication::where('payment_flow_id', $flow->id)->all();
        foreach ($applications as $application) {
            if ($document = $application->invoice ?? $application->estimate ?? null) {
                if (($document instanceof Invoice) && !$document->payment_url) {
                    return new JsonResponse(['message' => $translator->trans('messages.document_non_payable', [
                        '%number%' => $document->number
                    ], 'customer_portal')], 400);
                }
                if (!$paymentFlowManager->getBlockingAmount($document, Money::fromDecimal($document->currency, $application->amount))->isZero()) {
                    return new JsonResponse(['message' => $translator->trans('messages.payment_exceeds_amount_due', [], 'customer_portal')], 400);
                }
            }
        }

        return new JsonResponse();
    }
}

