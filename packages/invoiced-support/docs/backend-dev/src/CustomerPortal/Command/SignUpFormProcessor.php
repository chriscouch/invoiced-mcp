<?php

namespace App\CustomerPortal\Command;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\Core\Database\TransactionManager;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Exceptions\SignUpFormException;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\CustomerPortal\Libs\SignUpForm;
use App\CustomerPortal\Models\SignUpPage;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\Exceptions\AutoPayException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\AutoPay;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\ApproveSubscription;
use App\SubscriptionBilling\Operations\CreateSubscription;

class SignUpFormProcessor implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private const USE_EXISTING_PAYMENT_INFO = '__default__';

    public function __construct(
        private AutoPay $autoPay,
        private VaultPaymentInfo $vaultPaymentInfo,
        private CreateSubscription $createSubscriptionOp,
        private TransactionManager $transaction,
        private NotificationSpool $notificationSpool,
        private CustomerPortalEvents $customerPortalEvents,
    ) {
    }

    /**
     * Handles a form submission.
     *
     * @throws SignUpFormException when the submission fails for any reason
     *
     * @return array [Customer, Subscription]
     */
    public function handleSubmit(SignUpForm $form, array $parameters, string $ip, string $userAgent): array
    {
        // verify the ToS were accepted
        $accepted = array_value($parameters, 'tos_accepted');
        $page = $form->getPage();
        if ($page->tos_url && !$accepted) {
            throw new SignUpFormException('Please accept the Terms of Service in order to sign up.');
        }

        [$customer, $subscription] = $this->transaction->perform(function () use ($form, $page, $parameters, $ip, $userAgent) {
            // create a customer or enable AutoPay for existing customers
            $customerParams = (array) array_value($parameters, 'customer');
            $customer = $form->getCustomer();
            if (!$customer) {
                $customer = $this->createCustomer($page, $customerParams);
            } else {
                $this->enableAutoPay($customer, $customerParams);
            }

            // set the payment source
            $sourceParams = array_value($parameters, 'payment_source');
            if (is_array($sourceParams) && self::USE_EXISTING_PAYMENT_INFO != $sourceParams['method']) {
                $this->savePaymentSource($customer, $sourceParams);
            }

            // create a shipping contact
            if ($page->shipping_address) {
                $shipping = (array) array_value($parameters, 'shipping');
                $this->saveShippingAddress($customer, $shipping);
            }

            // handle recurring only sign up
            $subscription = null;
            if (SignUpPage::TYPE_RECURRING == $page->type) {
                $subscription = $this->handleRecurringPurchase($page, $customer, $parameters, $ip, $userAgent);
            }

            // set custom fields for AutoPay only sign ups
            if (SignUpPage::TYPE_AUTOPAY == $page->type) {
                $this->handleAutoPayOnly($customer, $parameters);
            }

            return [$customer, $subscription];
        });


        if (isset($parameters['disabled_methods']) && $parameters['disabled_methods']) {
            $this->notificationSpool->spool(NotificationEventType::DisabledMethodsOnSignUpPageCompleted, $customer->tenant_id, $customer->id, $customer->id);
            $this->customerPortalEvents->track($customer, CustomerPortalEvent::CompleteSignUpPagePaymentMethodDisabled);
            $this->statsd->increment('billing_portal.signup');

            return [$customer, $subscription];
        }

        // send a notification
        $this->notificationSpool->spool(NotificationEventType::SignUpPageCompleted, $customer->tenant_id, $customer->id, $customer->id);

        // track the event
        $this->customerPortalEvents->track($customer, CustomerPortalEvent::CompleteSignUpPage);
        $this->statsd->increment('billing_portal.signup');

        return [$customer, $subscription];
    }

    /**
     * Creates a customer.
     *
     * @throws SignUpFormException if the customer could not be created
     */
    private function createCustomer(SignUpPage $page, array $customerParams): Customer
    {
        $customer = new Customer();
        $customer->autopay = true;
        if ($pageId = $page->id()) {
            $customer->sign_up_page_id = (int) $pageId;
        }

        if (!$customer->create($customerParams)) {
            throw new SignUpFormException('Unable to create customer profile: '.$customer->getErrors());
        }

        return $customer;
    }

    /**
     * Enables AutoPay on an existing customer.
     *
     * @throws SignUpFormException if the customer could not be saved
     */
    private function enableAutoPay(Customer $customer, array $params): void
    {
        foreach ($params as $k => $v) {
            $customer->$k = $v;
        }
        $customer->autopay = true;

        if (!$customer->save()) {
            throw new SignUpFormException('Could not enable AutoPay: '.$customer->getErrors());
        }
    }

    /**
     * Saves the payment source on a customer.
     *
     * @throws SignUpFormException if the payment source could not be saved
     */
    private function savePaymentSource(Customer $customer, array $parameters): void
    {
        // determine the payment method
        $method = PaymentMethod::instance($customer->tenant(), $parameters['method']);

        try {
            $this->vaultPaymentInfo->save($method, $customer, $parameters);
        } catch (PaymentSourceException $e) {
            throw new SignUpFormException('Failed to save payment info: '.$e->getMessage());
        }
    }

    /**
     * Saves the shipping address on a customer.
     *
     * @throws SignUpFormException if the shipping address could not be saved
     */
    private function saveShippingAddress(Customer $customer, array $shipping): Contact
    {
        $contact = new Contact();
        $contact->customer = $customer;

        if (!$contact->create($shipping)) {
            throw new SignUpFormException('Unable to create shipping contact: '.$contact->getErrors());
        }

        return $contact;
    }

    /**
     * Performs the steps needed for a sign up from a recurring page.
     */
    private function handleRecurringPurchase(SignUpPage $page, Customer $customer, array $parameters, string $ip, string $userAgent): Subscription
    {
        // create the addons
        $plan = array_value($parameters, 'plan');
        $mainPlan = Plan::getCurrent($plan);
        [$oneTimeCharges, $recurringAddons] = $this->createAddons($page, $customer, $mainPlan, $parameters);

        // create a subscription
        $quantity = array_value($parameters, 'quantity');
        $coupon = array_value($parameters, 'coupon');
        $metadata = array_value($parameters, 'metadata');
        $subscription = $this->createSubscription($page, $customer, $plan, $quantity, $recurringAddons, $coupon, $metadata);

        // and approve the subscription
        (new ApproveSubscription())->approve($subscription, $ip, $userAgent);

        // charge the one-time items now when there is a free trial
        if ($page->trial_period_days > 0 && count($oneTimeCharges) > 0) {
            $this->chargeOneTimeItems($customer);
        }

        return $subscription;
    }

    /**
     * Performs the steps needed for a sign up from an AutoPay only page.
     */
    private function handleAutoPayOnly(Customer $customer, array $parameters): void
    {
        // set any custom fields on the customer
        $metadata = array_value($parameters, 'metadata');

        if (is_array($metadata)) {
            $customer->metadata = (object) $metadata;

            if (!$customer->save()) {
                throw new SignUpFormException('Could not set custom field: '.$customer->getErrors());
            }
        }
    }

    /**
     * Creates one-time and recurring addons.
     */
    private function createAddons(SignUpPage $page, Customer $customer, ?Plan $mainPlan, array $parameters): array
    {
        $addons = array_value($parameters, 'addons');

        $oneTimeCharges = [];
        $recurringAddons = [];

        foreach ($page->addons() as $addon) {
            $key = ($addon->plan) ? 'plan-'.$addon->plan : 'catalog_item-'.$addon->catalog_item;
            if (!$addon->required && !isset($addons[$key])) {
                continue;
            }

            $line = $addons[$key] ?? [];
            $enabled = array_value($line, 'enabled') || $addon->required;
            if (!$enabled) {
                continue;
            }

            if ($item = $addon->item()) {
                if ($addon->recurring) {
                    $recurringAddons[] = [
                        'catalog_item' => $item->id,
                        'quantity' => array_value($line, 'quantity'),
                    ];
                } else {
                    $quantity = 1;
                    if (isset($line['quantity'])) {
                        $quantity = $line['quantity'];
                    }
                    $oneTimeCharges[] = $this->createPendingLineItem($customer, $item, $quantity);
                }
            } elseif ($plan = $addon->plan()) {
                // make sure the plan interval matches the selected plan
                if ($mainPlan && !$plan->interval()->equals($mainPlan->interval())) {
                    continue;
                }

                $recurringAddons[] = [
                    'plan' => $plan->id,
                    'quantity' => array_value($line, 'quantity'),
                ];
            }
        }

        return [$oneTimeCharges, $recurringAddons];
    }

    /**
     * Creates a pending line item for a one-time charge.
     *
     * @throws SignUpFormException if the customer could not be saved
     */
    private function createPendingLineItem(Customer $customer, Item $catalogItem, int $quantity = 1): PendingLineItem
    {
        $pendingCharge = new PendingLineItem();
        $pendingCharge->setParent($customer);
        $pendingCharge->quantity = $quantity;
        $pendingCharge->catalog_item = $catalogItem->id;

        if (!$pendingCharge->save()) {
            throw new SignUpFormException('Unable to create upfront charge: '.$pendingCharge->getErrors());
        }

        return $pendingCharge;
    }

    /**
     * Creates the subscription for the customer.
     *
     * @throws SignUpFormException if the subscription could not be created
     */
    private function createSubscription(SignUpPage $page, Customer $customer, ?string $plan, ?int $quantity, array $addons, ?string $coupon, ?array $metadata = null): Subscription
    {
        $parameters = [
            'customer' => $customer->id(),
            'plan' => $plan,
            'taxes' => $page->taxes,
            'snap_to_nth_day' => $page->snap_to_nth_day,
            'addons' => $addons,
        ];

        if ($n = $page->trial_period_days) {
            $parameters['start_date'] = strtotime("+$n days");
        }

        if ($page->has_quantity && $quantity) {
            $parameters['quantity'] = $quantity;
        }

        if ($coupon && $couponObj = Coupon::getCurrent($coupon)) {
            $parameters['discounts'] = [$couponObj->id];
        }

        if (is_array($metadata)) {
            $parameters['metadata'] = $metadata;
        }

        try {
            return $this->createSubscriptionOp->create($parameters);
        } catch (OperationException $e) {
            throw new SignUpFormException('Unable to create subscription: '.$e->getMessage());
        }
    }

    /**
     * Charges the non-recurring items.
     *
     * @throws SignUpFormException if the one time items cannot be charged
     */
    private function chargeOneTimeItems(Customer $customer): void
    {
        // invoice the pending items
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->withPending();

        if (!$invoice->save()) {
            throw new SignUpFormException('Unable to bill upfront charge: '.$invoice->getErrors());
        }

        // skip collecting payment if the payment source is unverified
        $paymentSource = $customer->payment_source;
        if ($paymentSource && $paymentSource->needsVerification()) {
            return;
        }

        // now we can collect payment on the invoice
        try {
            $this->autoPay->collect($invoice);
        } catch (AutoPayException $e) {
            throw new SignUpFormException('Unable to collect upfront charge: '.$e->getMessage());
        }
    }
}
