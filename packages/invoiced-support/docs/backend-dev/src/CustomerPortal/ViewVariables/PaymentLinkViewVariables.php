<?php

namespace App\CustomerPortal\ViewVariables;

use App\AccountsReceivable\Libs\PaymentLinkHelper;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkField;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\Companies\Models\Company;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\PaymentLinkPrefilledValues;
use App\CustomerPortal\ValueObjects\PrefilledValues;
use App\Metadata\Libs\CustomFieldRepository;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\PaymentProcessing\Libs\PaymentMethodViewFactory;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Traits\PaymentFormTrait;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentLinkViewVariables
{
    use CustomerPortalViewVariablesTrait;
    use PaymentFormTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PaymentMethodViewFactory $viewFactory,
        private PaymentFlowManager $paymentFlowManager,
    ) {
    }

    /**
     * Gets the parameters needed to render the payment form.
     */
    public function build(CustomerPortal $portal, PaymentLink $paymentLink, string $defaultLineItemName, Money $amount, array $input = [], array $errors = []): array
    {
        $company = $portal->company();

        // Generate the prefilled values
        $customer = $paymentLink->customer;
        $fields = $paymentLink->getFields();
        $prefilledValues = PaymentLinkPrefilledValues::make($input, $customer, $fields);

        // If there is no customer then generate a temporary one for the payment views
        if (!$customer) {
            // prefill a customer model with values from URL
            $customer = $this->makeTemporaryCustomer($company, $prefilledValues);
        }

        // Determine fields to ask for
        $askForAmount = $amount->isZero();
        $askForCustomerName = !$customer->name;
        $askForEmail = !$customer->email;
        $askForPhone = !$customer->phone && $paymentLink->collect_phone_number;
        $askForBillingAddress = !$customer->address1 && $paymentLink->collect_billing_address;
        $askForShippingAddress = $paymentLink->collect_shipping_address;

        // Create a payment flow
        $flow = $this->makePaymentFlow($paymentLink, $amount);

        // Build the payment options
        $form = $this->makeForm($portal, $company, $customer, $amount);
        $paymentOptions = $this->makePaymentOptions($form, $askForBillingAddress);
        $selectedPaymentMethod = $prefilledValues->get('payment_source.method') ?? $paymentOptions[0]['id'] ?? null;

        // Form submission API URL
        $submitUrl = $this->generatePortalUrl($portal, 'customer_portal_payment_link_api', [
            'id' => $paymentLink->client_id,
        ]);

        return [
            'errors' => $errors,
            'amount' => $amount->toDecimal(),
            'askForAmount' => $askForAmount,
            'askForBillingAddress' => $askForBillingAddress,
            'askForCustomerName' => $askForCustomerName,
            'askForEmail' => $askForEmail,
            'askForPhone' => $askForPhone,
            'askForShippingAddress' => $askForShippingAddress,
            'countries' => $this->getCountries(),
            'currency' => $amount->currency,
            'currencySymbol' => MoneyFormatter::get()->currencySymbol($amount->currency),
            'customFields' => $this->getCustomFields($paymentLink, $fields),
            'customer' => $customer,
            'form' => $form,
            'lineItems' => PaymentLinkHelper::getLineItems($paymentLink, $amount, $defaultLineItemName),
            'paymentFlow' => $flow,
            'paymentOptions' => $paymentOptions,
            'prefilled' => $prefilledValues,
            'selectedPaymentMethod' => $selectedPaymentMethod,
            'submitUrl' => $submitUrl,
            'termsOfServiceUrl' => $paymentLink->terms_of_service_url,
        ];
    }

    private function makeForm(CustomerPortal $portal, Company $company, Customer $customer, Money $amount): PaymentForm
    {
        return new PaymentForm(
            company: $company,
            customer: $customer,
            totalAmount: $amount,
            methods: $this->getDefaultMethods($company, $customer),
            locale: $portal->getLocale(),
        );
    }

    private function makeTemporaryCustomer(Company $company, PrefilledValues $prefilledValues): Customer
    {
        $customer = new Customer();
        $customer->name = $prefilledValues->get('company') ?? '';
        if (!$customer->name) {
            $customer->name = trim(($prefilledValues->get('first_name') ?? '').' '.($prefilledValues->get('last_name') ?? ''));
        }
        $customer->email = $prefilledValues->get('customer.email');
        $customer->phone = $prefilledValues->get('customer.phone');
        $customer->address1 = $prefilledValues->get('customer.address1');
        $customer->address2 = $prefilledValues->get('customer.address2');
        $customer->city = $prefilledValues->get('customer.city');
        $customer->state = $prefilledValues->get('customer.state');
        $customer->postal_code = $prefilledValues->get('customer.postal_code');
        $customer->country = $prefilledValues->get('customer.country') ?? $company->country;

        return $customer;
    }

    private function makePaymentOptions(PaymentForm $form, bool $askForBillingAddress): array
    {
        // Collect saved payment methods to add as payment options for an existing customer
        $savedOptions = [];
        foreach ($form->getSavedPaymentSources() as $paymentSource) {
            $id = 'saved:'.$paymentSource->object.':'.$paymentSource->id();
            $supportsConvenienceFee = $paymentSource->supportsConvenienceFees();
            $method = $paymentSource->getPaymentMethod();
            $options = $this->makePaymentMethodOptions($method, $form->customer, $form->totalAmount, $supportsConvenienceFee);
            $options['paymentSourceType'] = $paymentSource->object;
            $options['paymentSourceId'] = $paymentSource->id;
            $savedOptions[] = [
                'id' => $id,
                'name' => $paymentSource->toString(true),
                'frontendData' => $options,
            ];
        }

        if (count($savedOptions) > 0) {
            $savedOptions[] = ['separator' => true];
        }

        // One-time payment options
        $oneTimeOptions = [];
        $router = new PaymentRouter();
        foreach ($form->methods as $method) {
            $merchantAccount = $router->getMerchantAccount($method);
            $view = $this->viewFactory->getPaymentView($method, $merchantAccount?->gateway);
            if (!$view->shouldBeShown($form, $method, $merchantAccount)) {
                continue;
            }

            // Only payment views that support submitting on the page can be used.
            $capabilities = $view->getPaymentFormCapabilities();
            if (!$capabilities->isSubmittable) {
                continue;
            }

            if ($askForBillingAddress && method_exists($view, 'disableBillingAddress')) {
                $view->disableBillingAddress();
            }

            $oneTimeOptions[] = [
                'id' => $method->id,
                'name' => $method->toString(),
                'frontendData' => $this->makePaymentMethodOptions($method, $form->customer, $form->totalAmount, $capabilities->supportsConvenienceFee),
                'view' => $view,
                'method' => $method,
                'merchantAccount' => $merchantAccount,
            ];
        }

        return array_merge($savedOptions, $oneTimeOptions);
    }

    private function makePaymentMethodOptions(PaymentMethod $method, Customer $customer, Money $amount, bool $supportsConvenienceFee): array
    {
        $convenienceFee = ['percent' => null];
        if ($supportsConvenienceFee) {
            $convenienceFee = ConvenienceFeeHelper::calculate($method, $customer, $amount);
        }

        if ($convenienceFee['percent'] > 0) {
            $formatter = MoneyFormatter::get();
            $moneyFormat = $customer->moneyFormat();
            $convenienceFee['amount'] = $formatter->format($convenienceFee['amount'], $moneyFormat);
            $convenienceFee['total'] = $formatter->format($convenienceFee['total'], $moneyFormat);
        }

        return [
            'convenienceFeePercent' => $convenienceFee['percent'],
            'convenienceFeeAmount' => $convenienceFee['amount'] ?? null,
            'convenienceFeeTotal' => $convenienceFee['total'] ?? null,
        ];
    }

    /**
     * @param PaymentLinkField[] $fields
     */
    private function getCustomFields(PaymentLink $paymentLink, array $fields): array
    {
        $customFields = [];
        $repository = CustomFieldRepository::get($paymentLink->tenant());
        foreach ($fields as $field) {
            $customField = $repository->getCustomField($field->object_type->typeName(), $field->custom_field_id);
            if (!$customField) {
                continue;
            }

            $customFields[] = [
                'id' => $field->getFormId(),
                'name' => $customField->name,
                'type' => $customField->type,
                'required' => $field->required,
                'visible' => $customField->external,
                'choices' => $customField->choices,
            ];
        }

        return $customFields;
    }

    private function makePaymentFlow(PaymentLink $paymentLink, Money $amount): PaymentFlow
    {
        $flow = new PaymentFlow();
        $flow->payment_link = $paymentLink;
        $flow->amount = $amount->toDecimal();
        $flow->currency = $amount->currency;
        $flow->customer = $paymentLink->customer;
        $flow->initiated_from = PaymentFlowSource::CustomerPortal;
        $this->paymentFlowManager->create($flow);

        return $flow;
    }
}
