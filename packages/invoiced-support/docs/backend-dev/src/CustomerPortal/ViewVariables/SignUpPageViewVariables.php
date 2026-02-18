<?php

namespace App\CustomerPortal\ViewVariables;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\Companies\Models\Company;
use App\Core\I18n\Currencies;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\SignUpForm;
use App\CustomerPortal\Models\SignUpPage;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Forms\PaymentInfoFormBuilder;
use App\PaymentProcessing\Libs\PaymentMethodViewFactory;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Libs\TokenizationFlowManager;
use App\PaymentProcessing\Models\TokenizationFlow;
use ICanBoogie\Inflector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SignUpPageViewVariables
{
    use CustomerPortalViewVariablesTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TokenizationFlowManager $tokenizationFlowManager
    ) {
    }

    /**
     * Builds the parameters for rendering a sign up page.
     */
    public function build(CustomerPortal $portal, SignUpForm $view, Request $request, PaymentMethodViewFactory $paymentMethodViewFactory, Customer $customer = null): array
    {
        $signUpPage = $view->getPage();
        $company = $view->getCompany();

        // get prefilled values from query parameters
        // and previous form submissions
        $input = array_replace($request->query->all(), $request->request->all());
        $prefilledValues = $view->getPrefilledValues($input);

        // determine selected plan
        $planId = $prefilledValues->get('plan');
        [$plans, $selectedPlan] = $this->getPlans($signUpPage, $company, $planId);

        $formatter = MoneyFormatter::get();
        $moneyFormat = $customer ? $customer->moneyFormat() : $company->moneyFormat();

        // addons
        $addons = [];
        foreach ($signUpPage->addons() as $addon) {
            if ($item = $addon->item()) {
                $amount = Money::fromDecimal($item->currency ?? $company->currency, $item->unit_cost);

                $addons[] = [
                    'id' => 'catalog_item-'.$item->id,
                    'name' => $item->name,
                    'amount' => $formatter->format($amount, $moneyFormat),
                    '_amount' => $item->unit_cost,
                    'recurring' => $addon->recurring,
                    'required' => $addon->required,
                    'type' => $addon->type,
                    'discountable' => $item->discountable,
                    'taxable' => $item->taxable,
                ];
            } elseif ($plan = $addon->plan()) {
                // float cast is for PHPStan; sign up page requires non-custom plan addons (hence $plan->amount is never null)
                $amount = Money::fromDecimal($plan->currency, (float) $plan->amount);

                $addons[] = [
                    'id' => 'plan-'.$plan->id,
                    'tiered' => $plan->tiers ? true : false,
                    'name' => $plan->getCustomerFacingName(),
                    'amount' => $formatter->format($amount, $moneyFormat),
                    '_amount' => $plan->amount,
                    'recurring' => true,
                    'interval' => $plan->interval,
                    'interval_count' => $plan->interval_count,
                    'interval_str' => $plan->toString(),
                    'required' => $addon->required,
                    'type' => $addon->type,
                    'discountable' => true,
                    'taxable' => true,
                ];
            }
        }

        // build tax rate display
        $taxes = $this->getTaxes($company, $signUpPage->taxes());

        // localization
        $countries = $this->getCountries();
        $selectedCountryBilling = $company->country;
        if ($country = $prefilledValues->get('customer.country')) {
            $selectedCountryBilling = $country;
        }

        $selectedCountryShipping = $company->country;
        if ($country = $prefilledValues->get('shipping.country')) {
            $selectedCountryShipping = $country;
        }

        // payment source forms
        $builder = new PaymentInfoFormBuilder($portal->getPaymentFormSettings());
        if ($customer) {
            $builder->setCustomer($customer);
        }

        $paymentMethods = $view->getPaymentMethods();
        $paymentSourceViews = [];
        $router = new PaymentRouter();
        $disabledMethods = [];
        foreach ($paymentMethods as $method) {
            // NOTE: No documents to pass to `getMerchantAccount` since
            // form is dealing w/ subscriptions, thus invoices
            // haven't been generated yet.
            $merchantAccount = $router->getMerchantAccount($method, $customer);
            $gateway = $merchantAccount?->gateway;
            $view = $paymentMethodViewFactory->getPaymentInfoView($method, $gateway);
            if (!$view->shouldBeShown($company, $method, $merchantAccount, $customer)) {
                $disabledMethods[] = $method->id;
                continue;
            }

            if ($signUpPage->billing_address && method_exists($view, 'disableBillingAddress')) {
                $view->disableBillingAddress();
            }

            $paymentSourceViews[] = [
                'view' => $view,
                'method' => $method,
                'merchantAccount' => $merchantAccount,
            ];
        }

        // renews on date
        $renewsOn = null;
        if ($n = $signUpPage->snap_to_nth_day) {
            $renewsOn = $n.Inflector::get()->ordinal($n);
        }

        // custom fields
        $customFields = [];
        foreach ($signUpPage->customFields() as $customField) {
            $id = $customField->id;
            $customFields[] = [
                'id' => $id,
                'name' => $customField->name,
                'choices' => $customField->choices,
                'prefillKey' => 'metadata.'.$id,
                'external' => $customField->external,
            ];
        }

        // submit URL
        $submitUrl = $this->generatePortalUrl($portal, 'customer_portal_submit_sign_up_page_api', [
            'id' => $signUpPage->client_id,
            'clientId' => $customer?->client_id,
        ]);

        $flow = $this->makeTokenizationFlow($signUpPage, $customer);

        return [
            'disabledMethods' => $disabledMethods,
            'addons' => $addons,
            'companyObj' => $company,
            'countries' => $countries,
            'currencies' => Currencies::all(),
            'customFields' => $customFields,
            'customer' => $customer,
            'hasBillingAddress' => $signUpPage->billing_address,
            'hasCouponCode' => $signUpPage->has_coupon_code,
            'hasQuantity' => $signUpPage->has_quantity,
            'hasShippingAddress' => $signUpPage->shipping_address,
            'hasToS' => (bool) $signUpPage->tos_url,
            'headerText' => $signUpPage->header_text,
            'paymentInfoForm' => $builder->build(),
            'paymentMethods' => $paymentMethods,
            'paymentSource' => $customer ? $customer->payment_source : false,
            'paymentSourceViews' => $paymentSourceViews,
            'plans' => $plans,
            'prefilled' => $prefilledValues,
            'renewsOn' => $renewsOn,
            'selectedCountryBilling' => $selectedCountryBilling,
            'selectedCountryShipping' => $selectedCountryShipping,
            'selectedPlan' => $selectedPlan,
            'submitUrl' => $submitUrl,
            'taxes' => $taxes,
            'tokenizationFlow' => $flow,
            'tosUrl' => $signUpPage->tos_url,
            'trialPeriodDays' => $signUpPage->trial_period_days,
            'type' => $signUpPage->type,
            'url' => $signUpPage->url,
        ];
    }

    /**
     * Returns the formatted tax rates.
     */
    public function getTaxes(Company $company, array $taxes): array
    {
        $formatter = MoneyFormatter::get();
        $moneyFormat = $company->moneyFormat();

        foreach ($taxes as &$tax) {
            $name = $tax['name'];
            if ($tax['is_percent']) {
                $name .= ' ('.$tax['value'].'%)';
            } else {
                $value = Money::fromDecimal($company->currency, $tax['value']);
                $value = $formatter->format($value, $moneyFormat);
                $name .= ' ('.$value.')';
            }

            $tax = [
                'name' => $name,
                'is_percent' => $tax['is_percent'],
                'value' => $tax['value'],
            ];
        }

        return $taxes;
    }

    /**
     * Returns the available plans and selected plan.
     *
     * @return array (plans, selectedPlan)
     */
    public function getPlans(SignUpPage $signUpPage, Company $company, ?string $planId = null): array
    {
        $formatter = MoneyFormatter::get();
        $moneyFormat = $company->moneyFormat();

        $planObjects = $signUpPage->plans();
        $selectedPlan = count($planObjects) > 0 ? $planObjects[0] : null;
        $plans = [];
        foreach ($planObjects as $plan) {
            if ($plan->id == $planId) {
                $selectedPlan = $plan;
            }

            $p = $plan->toArray();
            $p['name'] = $plan->getCustomerFacingName();

            $amount = Money::fromDecimal($p['currency'], $p['amount']);
            $p['amount_formatted'] = $formatter->format($amount, $moneyFormat);
            $p['interval_str'] = $plan->toString();

            $plans[] = $p;
        }

        return [$plans, $selectedPlan];
    }

    /**
     * Returns the inclusive and exclusive tax amounts.
     */
    public function getTaxAmount(Invoice $invoice): float
    {
        $currency = $invoice->currency;
        $amount = Money::fromDecimal($currency, 0);

        foreach ($invoice->taxes as $tax) {
            $amount = $amount->add(Money::fromDecimal($currency, $tax['amount']));
        }

        return $amount->toDecimal();
    }

    /**
     * Generates a formatted coupon result.
     */
    public function getCoupon(Company $company, Coupon $coupon): array
    {
        $result = $coupon->toArray();

        if ($coupon->is_percent) {
            $result['value_formatted'] = $coupon->value.'%';
        } else {
            $formatter = MoneyFormatter::get();
            $moneyFormat = $company->moneyFormat();
            $value = Money::fromDecimal($company->currency, $coupon->value);
            $result['value_formatted'] = $formatter->format($value, $moneyFormat);
        }

        return $result;
    }

    private function makeTokenizationFlow(SignUpPage $signUpPage, ?Customer $customer): TokenizationFlow
    {
        $flow = new TokenizationFlow();
        $flow->sign_up_page = $signUpPage;
        $flow->customer = $customer;
        $flow->initiated_from = PaymentFlowSource::CustomerPortal;
        $this->tokenizationFlowManager->create($flow);

        return $flow;
    }
}
