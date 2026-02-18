<?php

namespace App\CustomerPortal\Libs;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\CustomerPortal\Models\SignUpPage;
use App\CustomerPortal\ValueObjects\PrefilledValues;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Models\PaymentMethod;
use App\SubscriptionBilling\Models\Subscription;

class SignUpForm
{
    private static array $defaults = [
        'quantity' => 1,
        'payment_source' => [
            'method' => PaymentMethod::CREDIT_CARD,
        ],
    ];

    private array $prefillKeyAllowed = [
        // customer profile
        'customer.name',
        'customer.email',
        // billing address
        'customer.address1',
        'customer.address2',
        'customer.city',
        'customer.state',
        'customer.postal_code',
        'customer.country',
        'customer.tax_id',
        // shipping address
        'shipping.name',
        'shipping.address1',
        'shipping.address2',
        'shipping.city',
        'shipping.state',
        'shipping.postal_code',
        'shipping.country',
        // selected plan
        'plan',
        'quantity',
        'coupon',
        // addons
        'addons',
        // payment source
        'payment_source.method',
    ];
    private ?Customer $customer = null;

    public function __construct(private SignUpPage $page, private Company $company)
    {
        // add custom fields to prefill key allow list
        foreach ($page->customFields() as $customField) {
            $this->prefillKeyAllowed[] = 'metadata.'.$customField->id;
        }

        // add addons to prefill key allow list
        foreach ($page->addons() as $addon) {
            $key = ($addon->plan) ? 'plan-'.$addon->plan : 'catalog_item-'.$addon->catalog_item;
            $this->prefillKeyAllowed[] = 'addons.'.$key.'.enabled';
            $this->prefillKeyAllowed[] = 'addons.'.$key.'.quantity';
            array_set(self::$defaults, 'addons.'.$key.'.quantity', 1);
        }
    }

    /**
     * Gets the signup page.
     */
    public function getPage(): SignUpPage
    {
        return $this->page;
    }

    /**
     * Gets the company.
     */
    public function getCompany(): Company
    {
        return $this->company;
    }

    /**
     * Checks if sign ups are allowed through this form.
     *
     * In order to enable sign ups a company must have AutoPay
     * and at least one supported payment method enabled.
     */
    public function signUpsAllowed(): bool
    {
        // must support AutoPay and have at least one payment method enabled
        if (!PaymentMethod::acceptsAutoPay($this->company)) {
            return false;
        }

        return count($this->getPaymentMethods()) > 0;
    }

    /**
     * Checks if a customer is allowed to sign up through this form.
     * Depending on the page settings, a customer may only be
     * allowed to purchase 1 subscription.
     */
    public function signUpsAllowedForCustomer(Customer $customer): bool
    {
        if ($this->page->allow_multiple_subscriptions) {
            return true;
        }

        $numSubs = Subscription::where('customer', $customer->id())
            ->where('canceled', false)
            ->where('finished', false)
            ->count();

        return 0 === $numSubs;
    }

    /**
     * Gets the pre-filled, or previously submitted, values for this form.
     * Filters the list to a list of allowed keys.
     */
    public function getPrefilledValues(array $input): PrefilledValues
    {
        // start with the defaults
        $values = self::$defaults;

        // add in customer values, if form has an existing customer
        if ($this->customer) {
            foreach ($this->prefillKeyAllowed as $keyName) {
                if (str_starts_with($keyName, 'customer.')) {
                    [, $property] = explode('.', $keyName);
                    array_set($values, $keyName, $this->customer->$property);
                }
            }
        }

        // flatten a potentially multi-dimensional input into dot notation
        // i.e. a['customer']['name'] -> a['customer.name']
        $input = array_dot($input);

        // build the filtered output
        foreach ($input as $k => $v) {
            if (in_array($k, $this->prefillKeyAllowed)) {
                array_set($values, $k, $v);
            }
        }

        return new PrefilledValues($values);
    }

    /**
     * Gets the payment methods supported by this sign up form.
     */
    public function getPaymentMethods(): array
    {
        $result = [];
        $methods = PaymentMethod::allEnabled($this->company);
        foreach ($methods as $method) {
            // Currently GoCardless does not work with sign up forms
            // because it requires a redirect to a separate page to set up the mandate.
            // TODO: need tokenization flows to make this work
            if (GoCardlessGateway::ID == $method->gateway) {
                continue;
            }

            if ($method->supportsAutoPay()) {
                $result[$method->id] = $method;
            }
        }

        return $result;
    }

    /**
     * Looks up a coupon given its ID.
     */
    public function lookupCoupon(string $id): ?Coupon
    {
        if (!$this->page->has_coupon_code) {
            return null;
        }

        return Coupon::getCurrent($id);
    }

    /**
     * Gets the thank you redirect URL for this sign up page.
     * Should be called after a successful sign up.
     */
    public function getThanksUrl(Customer $customer, ?Subscription $subscription): string
    {
        // get the default thank you URL
        if ($thanks = $this->page->thanks_url) {
            $url = $thanks;
        } else {
            $url = $this->page->url.'/thanks';
        }

        // add newly created customer and subscription IDs
        // as query parameters to the resulting URL
        $query = [
            'invoiced_customer_id' => $customer->id(),
        ];

        if ($subscription) {
            $query['invoiced_subscription_id'] = $subscription->id();
        }

        return CustomerPortalHelper::addQueryParametersToUrl($url, $query);
    }

    /**
     * Sets the customer on the form.
     */
    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }
}
