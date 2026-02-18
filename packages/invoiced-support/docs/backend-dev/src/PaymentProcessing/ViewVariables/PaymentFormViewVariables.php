<?php

namespace App\PaymentProcessing\ViewVariables;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\Core\I18n\MoneyFormatter;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Libs\ChargeApplicationBuilder;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\PaymentProcessing\Libs\PaymentMethodViewFactory;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentFormViewVariables
{
    use CustomerPortalViewVariablesTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PaymentFlowManager $paymentFlowManager,
    ) {
    }

    /**
     * Gets the parameters needed to render the payment form.
     */
    public function build(PaymentForm $form, PaymentMethodViewFactory $viewFactory, CustomerPortal $portal, array $errors = []): array
    {
        $amount = $form->totalAmount;
        $customer = $form->customer;
        $currencySymbol = MoneyFormatter::get()->currencySymbol($amount->currency);

        // Payment methods
        $paymentSources = [];
        foreach ($form->getSavedPaymentSources() as $paymentSource) {
            $k = 'saved:'.$paymentSource->object.':'.$paymentSource->id();
            $paymentSources[$k] = $paymentSource;
        }

        // get the views for each payment method
        $paymentViews = [];
        $router = new PaymentRouter();
        $convenienceFeeAmount = null;
        $convenienceFeePercent = null;
        $convenienceFeeTotal = null;

        foreach ($form->methods as $method) {
            $merchantAccount = $router->getMerchantAccount($method, $customer, $form->documents);
            $view = $viewFactory->getPaymentView($method, $merchantAccount?->gateway);
            if (!$view->shouldBeShown($form, $method, $merchantAccount)) {
                continue;
            }

            $capabilities = (array) $view->getPaymentFormCapabilities();
            $capabilities['supportsConvenienceFee'] = $capabilities['supportsConvenienceFee'] && $method->convenience_fee;

            $paymentViews[] = [
                'view' => $view,
                'method' => $method,
                'merchantAccount' => $merchantAccount,
                'gateway' => $merchantAccount?->gateway,
                'capabilities' => $capabilities,
            ];

            // Convenience fees
            $convenienceFee = ConvenienceFeeHelper::calculate($method, $form->customer, $form->totalAmount);
            if ($convenienceFee['percent'] > 0) {
                $convenienceFeePercent = $convenienceFee['percent'];
                $convenienceFeeAmount = $convenienceFee['amount']->toDecimal();
                $convenienceFeeTotal = $convenienceFee['total']->toDecimal();
            }
        }

        // determine the selected payment method
        $selectedPaymentMethod = $form->selectedPaymentMethod;
        if (!$selectedPaymentMethod && count($paymentSources) > 0) {
            $selectedPaymentMethod = array_keys($paymentSources)[0];
        } elseif (!$selectedPaymentMethod && count($paymentViews) > 0) {
            $selectedPaymentMethod = $paymentViews[0]['method']->id;
        }

        $modifySelectionParams = [
            'id' => $form->customer->client_id,
            'force_choice' => '1',
        ];

        // Form display items and payment items
        $formItems = [];
        $displayItems = [];
        foreach ($form->paymentItems as $item) {
            $displayItems[] = [
                'description' => $item->description,
                'amount' => $item->amount->toDecimal(),
            ];

            $hasMultiple = true;
            if ($item->document instanceof Invoice) {
                $type = 'Invoice';
            } elseif ($item->document instanceof CreditNote) {
                $type = 'CreditNote';
            } elseif ($item->document instanceof Estimate) {
                $type = 'Quote';
            } elseif ('Credit Balance' == $item->description) {
                $type = 'CreditBalance';
                $hasMultiple = false;
            } else {
                $type = 'advance';
                $hasMultiple = false;
            }

            if ('advance' == $type) {
                $modifySelectionParams['advance'] = '1';
            } elseif ('CreditBalance' == $type) {
                $modifySelectionParams['CreditBalance'] = '1';
            } else {
                if (!isset($modifySelectionParams[$type])) {
                    $modifySelectionParams[$type] = [];
                }
                $modifySelectionParams[$type][] = $item->document?->number; /* @phpstan-ignore-line */
            }

            // build form item
            $formItems[] = [
                'clientId' => $item->document?->client_id ?? $form->customer->client_id,
                'type' => $type,
                'amount' => $item->amount->toDecimal(),
                'amountType' => $item->amountOption?->value,
                'hasMultiple' => $hasMultiple,
            ];
        }

        // Create a payment flow
        $flow = $this->makePaymentFlow($form);

        // submit URL
        $submitUrl = $this->generatePortalUrl($portal, 'customer_portal_submit_payment_api');

        return [
            'allowAutoPayEnrollment' => $form->allowAutoPayEnrollment,
            'amount' => $amount->toDecimal(),
            'convenienceFeeAmount' => $convenienceFeeAmount,
            'convenienceFeePercent' => $convenienceFeePercent,
            'convenienceFeeTotal' => $convenienceFeeTotal,
            'currency' => $form->currency,
            'currencySymbol' => $currencySymbol,
            'customerEmail' => $customer->email,
            'displayItems' => $displayItems,
            'form' => $form,
            'formItems' => $formItems,
            'makeDefault' => $form->shouldCapturePaymentInfo,
            'modifySelectionParams' => $modifySelectionParams,
            'modifyUrl' => $this->generatePortalUrl($portal, 'customer_portal_payment_select_items_form'),
            'noPaymentMethods' => 0 === count($paymentViews),
            'paymentFlow' => $flow,
            'paymentSources' => $paymentSources,
            'paymentViews' => $paymentViews,
            'receiptEmail' => $paymentSources[$selectedPaymentMethod]->receipt_email ?? null,
            'requireSavedCardCvc' => $form->company->accounts_receivable_settings->saved_cards_require_cvc,
            'selectedPaymentMethod' => $selectedPaymentMethod,
            'submitUrl' => $submitUrl,
            'errors' => $errors,
        ];
    }

    private function makePaymentFlow(PaymentForm $form): PaymentFlow
    {
        $flow = new PaymentFlow();
        $flow->amount = $form->totalAmount->toDecimal();
        $flow->currency = $form->totalAmount->currency;
        $flow->customer = $form->customer;
        $flow->initiated_from = PaymentFlowSource::CustomerPortal;
        $flow->make_payment_source_default = $form->shouldCapturePaymentInfo;

        $applications = [];
        $chargeApplication = $this->buildChargeApplication($form);
        foreach ($chargeApplication->getItems() as $item) {
            $applications[] = $item->buildApplication();
        }

        $this->paymentFlowManager->create($flow, $applications);

        return $flow;
    }

    private function buildChargeApplication(PaymentForm $form): ChargeApplication
    {
        return (new ChargeApplicationBuilder())
            ->addPaymentForm($form)
            ->build();
    }
}
