<?php

namespace App\Core\Billing\BillingSystem;

use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\Billing\ValueObjects\BillingSystemSubscription;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Stripe\HasStripeClientTrait;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use stdClass;
use Stripe\Card;
use Stripe\Customer;
use Stripe\Exception\CardException as StripeCardError;
use Stripe\Exception\ExceptionInterface as StripeError;
use Stripe\Exception\RateLimitException;
use Stripe\Invoice;
use Stripe\Invoice as StripeInvoice;
use Stripe\PaymentMethod;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Subscription;
use Stripe\SubscriptionItem;

class StripeBillingSystem extends AbstractBillingSystem implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use HasStripeClientTrait;

    const ID = 'stripe';

    /** @var Customer[] */
    private array $stripeCustomers = [];
    /** @var Subscription[] */
    private array $stripeSubscriptions = [];
    /** @var StripeProduct[] */
    private array $stripeProducts = [];
    /** @var StripePrice[] */
    private array $stripePrices = [];

    public function __construct(string $stripeBillingSecret)
    {
        $this->stripeSecret = $stripeBillingSecret;
    }

    //
    // BillingSystemInterface
    //

    public function createOrUpdateCustomer(BillingProfile $billingProfile, array $params): void
    {
        // Retrieve and update an existing customer
        $customer = null;
        try {
            if ($billingProfile->stripe_customer) {
                $customer = $this->getCustomer($billingProfile);

                // Update the customer with the new values
                foreach ($this->stripeCustomerData($billingProfile, $params) as $k => $v) {
                    $customer->$k = $v;
                }
                $customer->save();
            }
        } catch (StripeError $e) {
            $this->logger->debug('Could not retrieve customer from Stripe', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }

        // Create a new customer otherwise
        if (!$customer) {
            try {
                $stripe = $this->getStripe();
                $customer = $stripe->customers->create($this->stripeCustomerData($billingProfile, $params));
                $this->stripeCustomers[$billingProfile->id] = $customer;
            } catch (StripeError $e) {
                // log any errors not related to invalid cards
                if (!($e instanceof StripeCardError)) {
                    $this->logger->error('Could not create Stripe customer', ['exception' => $e]);
                }

                throw new BillingException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // Update the reference on the billing profile
        $billingProfile->billing_system = self::ID;
        $billingProfile->stripe_customer = $customer->id;
        $billingProfile->saveOrFail();
    }

    public function createSubscription(BillingProfile $billingProfile, array $subscriptionItems, CarbonImmutable $startDate): void
    {
        // Create or retrieve an Invoiced customer
        $customer = $this->getCustomer($billingProfile);

        // Build the new subscription items
        $newItems = $this->buildSubscriptionItems($subscriptionItems);
        if (0 == count($newItems)) {
            throw new BillingException('Could not create subscription because there are no items');
        }

        try {
            // create the subscription
            $stripe = $this->getStripe();
            $subscription = $stripe->subscriptions->create([
                'customer' => $customer->id,
                'description' => 'Invoiced Subscription',
                'items' => $newItems,
                'trial_end' => 'now',
                'pay_immediately' => false, // This is needed for the Avalara integration to function
            ]);

            if (!in_array($subscription->status, ['active', 'trialing'])) {
                throw new BillingException('Unable to create subscription');
            }
        } catch (StripeError $e) {
            // log any errors not related to invalid cards
            if (!($e instanceof StripeCardError)) {
                $this->logger->error('Could not create Stripe subscription', ['exception' => $e]);
            }

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function updateSubscription(BillingProfile $billingProfile, array $subscriptionItems, bool $prorate, CarbonImmutable $prorationDate): void
    {
        // Retrieve the current subscription
        $subscription = $this->getSubscription($billingProfile);

        // Build the new subscription items
        $newItems = $this->buildSubscriptionItems($subscriptionItems);
        if (0 == count($newItems)) {
            throw new BillingException('Could not update subscription because there are no items');
        }

        // Check if there is any change in the subscription items
        $hasChange = count($subscription->items->data) != count($newItems);
        $items = [];
        foreach ($subscription->items->data as $index => $item) {
            if (isset($newItems[$index])) {
                $newItem = $newItems[$index];
                if ($item->price->id != $newItem['price'] || $item->quantity != $newItem['quantity']) {
                    $hasChange = true;
                }
            }

            // delete current items
            $items[] = [
                'id' => $item->id,
                'deleted' => true,
            ];
        }

        // Do not perform an update if there is no change to the current subscription items
        if (!$hasChange) {
            return;
        }

        $items = array_merge($items, $newItems);

        // Modify the subscription with the new items. This is not prorated
        // because we are rebuilding the subscription without changing the prices.
        $subscription->description = 'Invoiced Subscription';
        $subscription->items = $items; /* @phpstan-ignore-line */
        $subscription->proration_behavior = $prorate ? 'create_prorations' : 'none'; /* @phpstan-ignore-line */
        if ($prorate) {
            $subscription->proration_date = $prorationDate->getTimestamp(); /* @phpstan-ignore-line */
        }

        try {
            $subscription->save();
        } catch (StripeError $e) {
            $this->logger->error('Could not update Stripe subscription', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setDefaultPaymentMethod(BillingProfile $billingProfile, string $token): void
    {
        $customer = $this->getCustomer($billingProfile);

        try {
            $customer->source = $token; /* @phpstan-ignore-line */
            $customer->save();
        } catch (StripeError $e) {
            // log any errors not related to invalid cards
            if (!($e instanceof StripeCardError)) {
                $this->logger->error('Could not set default Stripe payment method', ['exception' => $e]);
            }

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function billLineItem(BillingProfile $billingProfile, BillingOneTimeItem $item, bool $billNow): string
    {
        if ($item->usageType) {
            $description = $item->usageType->getFriendlyName().' - '.$item->description;
        } else {
            $description = $item->description;
        }

        try {
            $stripe = $this->getStripe();
            $invoiceItem = $stripe->invoiceItems->create([
                'customer' => $billingProfile->stripe_customer,
                'currency' => 'usd',
                'quantity' => $item->quantity,
                'unit_amount' => $item->price->amount,
                'description' => $description,
                'period' => [
                    'start' => $item->periodStart?->getTimestamp(),
                    'end' => $item->periodEnd?->getTimestamp(),
                ],
            ]);
        } catch (StripeError $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }

        return $invoiceItem->id;
    }

    public function cancel(BillingProfile $billingProfile, bool $atPeriodEnd): void
    {
        $subscription = $this->getSubscription($billingProfile);

        try {
            if ($atPeriodEnd) {
                $subscription->cancel_at_period_end = true;
                $subscription->save();
            } else {
                $subscription->cancel();
            }
        } catch (StripeError $e) {
            // log any errors not related to invalid cards
            if (!($e instanceof StripeCardError)) {
                $this->logger->error('Could not cancel Stripe subscription', ['exception' => $e]);
            }

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }

        // invoice any un-billed line items after a cancellation
        try {
            $stripe = $this->getStripe();
            $items = $stripe->invoiceItems->all([
                'customer' => $billingProfile->stripe_customer,
                'pending' => true,
            ]);

            if (count($items->data) > 0) {
                $stripe->invoices->create([
                    'customer' => $billingProfile->stripe_customer,
                ]);
            }
        } catch (StripeError $e) {
            // do nothing when this operation fails
            $this->logger->error('Could not bill items when canceling Stripe subscription', ['exception' => $e]);
        }
    }

    public function reactivate(BillingProfile $billingProfile): void
    {
        $subscription = $this->getSubscription($billingProfile);

        try {
            $subscription->cancel_at_period_end = false;
            $subscription->save();
        } catch (StripeError $e) {
            $this->logger->error('Could not reactivate Stripe subscription', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getBillingHistory(BillingProfile $billingProfile): array
    {
        $customer = $this->getCustomer($billingProfile);

        // no explicit sorting is necessary bc Stripe API default order is date descending
        try {
            $stripe = $this->getStripe();
            $invoices = $stripe->invoices->all(
                ['customer' => $customer->id],
            );
        } catch (StripeError $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }

        $result = [];

        /** @var Invoice $invoice */
        foreach ($invoices->data as $invoice) {
            $error = $invoice->amount_remaining > 0 && 'void' != $invoice->status
                ? 'Unpaid balance due of $'.number_format($invoice->amount_remaining / 100, 2)
                : null;

            $result[] = [
                'date' => $invoice->created,
                'amount' => $invoice->amount_due / 100,
                'invoice_url' => $invoice->invoice_pdf,
                'payment_url' => $invoice->hosted_invoice_url,
                'error' => $error,
            ];
        }

        return $result;
    }

    //
    // AbstractBillingSystem
    //

    public function getPaymentSourceInfo(BillingProfile $billingProfile): array
    {
        $customer = $this->getCustomer($billingProfile);

        $paymentMethods = $this->getPaymentMethods($billingProfile);

        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->id == $customer->default_source) {
                /** @var Card $card */
                $card = $paymentMethod->card;

                return [
                    'last4' => $card->last4,
                    'exp_month' => $card->exp_month,
                    'exp_year' => $card->exp_year,
                    'type' => $this->resolveBrand($card->brand),
                    'object' => $paymentMethod->type,
                ];
            }
        }

        return [];
    }

    public function isCanceledAtPeriodEnd(BillingProfile $billingProfile): bool
    {
        try {
            return $this->getSubscription($billingProfile)->cancel_at_period_end;
        } catch (BillingException) {
            // do nothing if subscription does not exist
            return false;
        }
    }

    public function getDiscount(BillingProfile $billingProfile): ?array
    {
        $customer = $this->getCustomer($billingProfile);

        if (!$customer->discount) {
            return null;
        }

        return [
            'coupon' => $customer->discount->coupon->toArray(),
            'start' => $customer->discount->start,
            'end' => $customer->discount->end,
            'origin' => 'stripe',
        ];
    }

    public function getNextChargeAmount(BillingProfile $billingProfile): float
    {
        $customer = $this->getCustomer($billingProfile);

        try {
            $stripe = $this->getStripe();
            /** @var StripeInvoice|null $invoice */
            $invoice = $stripe->invoices->upcoming(['customer' => $customer->id]);

            return $invoice ? $invoice->total / 100 : 0.0;
        } catch (\Exception $e) {
            if (!str_starts_with(strtolower($e->getMessage()), 'no upcoming invoices for customer') && !$e instanceof RateLimitException) {
                $this->logger->error('No upcoming invoices exist for customer in Stripe', ['exception' => $e]);
            }

            return 0.0;
        }
    }

    public function isAutoPay(BillingProfile $billingProfile): bool
    {
        // Stripe customers always have a card on file and will be automatically charged.
        return true;
    }

    public function getNextBillDate(BillingProfile $billingProfile): ?CarbonImmutable
    {
        try {
            $periodEnd = $this->getSubscription($billingProfile)->current_period_end;

            return $periodEnd > time() ? CarbonImmutable::createFromTimestamp($periodEnd) : null;
        } catch (BillingException) {
            // do nothing if subscription does not exist
        }

        return null;
    }

    public function getUpdatePaymentInfoUrl(BillingProfile $billingProfile): ?string
    {
        return null;
    }

    public function getCurrentSubscription(BillingProfile $billingProfile): BillingSystemSubscription
    {
        // Look up subscription on Stripe
        $subscription = $this->getSubscription($billingProfile);

        // Calculate total and billing interval
        $total = Money::zero('usd');
        $billingInterval = null;

        /** @var SubscriptionItem $subscriptionItem */
        foreach ($subscription->items->data as $subscriptionItem) {
            /** @var stdClass|null $recurring */
            $recurring = $subscriptionItem->price->recurring;
            if ($recurring && 'licensed' == $recurring->usage_type) {
                $total = $total->add(new Money('usd', $subscriptionItem->price->unit_amount * $subscriptionItem->quantity));

                if (!$billingInterval) {
                    if ('month' == $recurring->interval && 1 == $recurring->interval_count) {
                        $billingInterval = BillingInterval::Monthly;
                    } elseif ('year' == $recurring->interval && 1 == $recurring->interval_count) {
                        $billingInterval = BillingInterval::Yearly;
                    } elseif ('month' == $recurring->interval && 3 == $recurring->interval_count) {
                        $billingInterval = BillingInterval::Quarterly;
                    } elseif ('month' == $recurring->interval && 6 == $recurring->interval_count) {
                        $billingInterval = BillingInterval::Semiannually;
                    }
                }
            }
        }

        if (!$billingInterval) {
            throw new BillingException('Billing interval not recognized.');
        }

        return new BillingSystemSubscription(
            billingInterval: $billingInterval,
            total: $total,
            paused: 'paused' == $subscription->status,
        );
    }

    //
    // Helpers
    //

    /**
     * Attempts to create or retrieve the Stripe Customer for this model.
     * Memoizes the result.
     *
     * @throws BillingException when the operation fails
     *
     * @return Customer
     */
    public function getCustomer(BillingProfile $billingProfile)
    {
        if (!isset($this->stripeCustomers[$billingProfile->id])) {
            if (!$billingProfile->stripe_customer) {
                throw new BillingException('Missing Stripe customer ID');
            }

            try {
                $stripe = $this->getStripe();
                $this->stripeCustomers[$billingProfile->id] = $stripe->customers->retrieve($billingProfile->stripe_customer);
            } catch (StripeError $e) {
                $this->logger->debug('Could not retrieve customer from Stripe', ['exception' => $e]);

                throw new BillingException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->stripeCustomers[$billingProfile->id];
    }

    /**
     * Returns data for this model to be set when creating Stripe customers.
     */
    public function stripeCustomerData(BillingProfile $billingProfile, array $params): array
    {
        return [
            'description' => $params['company'] ?? $billingProfile->name,
            'email' => $params['email'] ?? null,
            'metadata' => [
                // Marketing properties
                'referred_by' => $billingProfile->referred_by ?: null,
                // Avalara properties
                'Address_Line1' => $params['address1'] ?? null,
                'Address_Line2' => $params['address2'] ?? null,
                'Address_City' => $params['city'] ?? null,
                'Address_State' => $params['state'] ?? null,
                'Address_PostalCode' => $params['postal_code'] ?? null,
                'Address_Country' => $params['country'] ?? null,
                'TaxCode' => self::AVALARA_TAX_CODE,
                'ItemCode' => 'invoiced-subscription',
            ],
        ];
    }

    /**
     * @return PaymentMethod[]
     */
    public function getPaymentMethods(BillingProfile $billingProfile): array
    {
        try {
            $stripe = $this->getStripe();
            $result = $stripe->paymentMethods->all([
                'customer' => $billingProfile->stripe_customer,
                'type' => 'card',
            ]);

            return $result->data;
        } catch (StripeError $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Builds Stripe subscription items.
     *
     * @param BillingSubscriptionItem[] $billingItems
     *
     * @throws BillingException
     */
    private function buildSubscriptionItems(array $billingItems): array
    {
        $items = [];

        foreach ($billingItems as $billingItem) {
            if ($billingItem->product) {
                $price = $this->getPriceForProduct($billingItem->product, $billingItem);
            } elseif ($billingItem->usageType) {
                $price = $this->getPriceForUsage($billingItem->usageType, $billingItem);
            } else {
                $price = $this->getPriceForPAYG($billingItem);
            }

            $items[] = [
                'quantity' => $billingItem->quantity,
                'price' => $price,
            ];
        }

        // Items with the same price ID must be grouped together
        // in order to avoid an error from Stripe.
        $newItems = [];
        foreach ($items as $item) {
            $found = false;
            foreach ($newItems as &$item2) {
                if ($item['price'] == $item2['price']) {
                    $found = true;
                    $item2['quantity'] += $item['quantity'];
                    break;
                }
            }

            if (!$found) {
                $newItems[] = $item;
            }
        }

        return $newItems;
    }

    /**
     * Gets the Stripe subscription for an account.
     *
     * @throws BillingException if the subscription does not exist or cannot be retrieved
     *
     * @return Subscription
     */
    private function getSubscription(BillingProfile $billingProfile): object
    {
        if (!isset($this->stripeSubscriptions[$billingProfile->id])) {
            if (!$billingProfile->stripe_customer) {
                throw new BillingException('Missing Stripe customer ID');
            }

            try {
                $stripe = $this->getStripe();
                $subscriptions = $stripe->subscriptions->all(
                    ['customer' => $billingProfile->stripe_customer],
                );
            } catch (StripeError $e) {
                $this->logger->debug('Could not retrieve subscription from Stripe', ['exception' => $e]);

                throw new BillingException($e->getMessage(), $e->getCode(), $e);
            }

            if (0 == count($subscriptions->data)) {
                throw new BillingException('Could not find an active subscription in the billing system.');
            }

            if (count($subscriptions->data) > 1) {
                throw new BillingException('Stripe customer has '.count($subscriptions->data).' active subscriptions.');
            }

            $this->stripeSubscriptions[$billingProfile->id] = $subscriptions->data[0];
        }

        return $this->stripeSubscriptions[$billingProfile->id];
    }

    /**
     * Resolves brand names to follow Invoiced namings.
     */
    private function resolveBrand(string $brand): string
    {
        return str_replace(
            ['amex', 'diners', 'discover', 'jcb', 'mastercard', 'unionpay', 'visa', 'unknown'],
            ['American Express', 'Diners Club', 'Discover', 'JCB', 'Mastercard', 'UnionPay', 'Visa', 'Unknown'],
            $brand
        );
    }

    /**
     * @throws BillingException
     */
    private function getPriceForProduct(Product $product, BillingSubscriptionItem $billingItem): string
    {
        $productId = 'invoiced-product-'.$product->id;
        $this->createProductIfNotExists($productId, $product->name);

        return $this->getOrCreatePrice($productId, $billingItem);
    }

    /**
     * @throws BillingException
     */
    private function getPriceForUsage(UsageType $usageType, BillingSubscriptionItem $billingItem): string
    {
        $productId = 'invoiced-usage-'.$usageType->getName();
        $this->createProductIfNotExists($productId, $usageType->getFriendlyName());

        return $this->getOrCreatePrice($productId, $billingItem);
    }

    /**
     * @throws BillingException
     */
    private function getPriceForPAYG(BillingSubscriptionItem $billingItem): string
    {
        $productId = 'invoiced-pay-as-you-go';
        $name = 'Pay-As-You-Go';
        $this->createProductIfNotExists($productId, $name);

        return $this->getOrCreatePrice($productId, $billingItem);
    }

    /**
     * This is used for testing.
     */
    public function setProduct(StripeProduct $product): void
    {
        $this->stripeProducts[$product->id] = $product;
    }

    /**
     * This is used for testing.
     */
    public function setPrice(StripePrice $price, string $id): void
    {
        $this->stripePrices[$id] = $price;
    }

    /**
     * @throws BillingException
     */
    private function createProductIfNotExists(string $id, string $name): void
    {
        if (isset($this->stripeProducts[$id])) {
            return;
        }

        // Check if exists on Stripe first
        try {
            $stripe = $this->getStripe();
            $product = $stripe->products->retrieve($id);
            $this->stripeProducts[$id] = $product;

            return;
        } catch (StripeError) {
            // do nothing on error
        }

        // Create the item if it could not be retrieved
        try {
            $this->stripeProducts[$id] = $stripe->products->create([
                'id' => $id,
                'name' => $name,
            ]);
        } catch (StripeError $e) {
            $this->logger->error('Could not create Stripe product', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws BillingException
     */
    private function getOrCreatePrice(string $productId, BillingSubscriptionItem $billingItem): string
    {
        $lookupKey = $productId.'-'.$billingItem->billingInterval->getIdName().'-'.$billingItem->price->amount;
        if (isset($this->stripePrices[$lookupKey])) {
            return $this->stripePrices[$lookupKey]->id;
        }

        // Check if exists on Stripe first
        try {
            $stripe = $this->getStripe();
            $prices = $stripe->prices->all(['lookup_keys' => [$lookupKey]]);

            if (count($prices->data) > 0) {
                $this->stripePrices[$lookupKey] = $prices->data[0];

                return $this->stripePrices[$lookupKey]->id;
            }
        } catch (StripeError) {
            // do nothing on error
        }

        // Create the item if it could not be retrieved
        $params = [
            'lookup_key' => $lookupKey,
            'transfer_lookup_key' => true,
            'unit_amount' => $billingItem->price->amount,
            'currency' => 'usd',
            'product' => $productId,
            'nickname' => $billingItem->name,
            'recurring' => [
                'usage_type' => 'licensed',
            ],
        ];

        // Determine billing interval
        if (BillingInterval::Monthly == $billingItem->billingInterval) {
            $params['recurring']['interval_count'] = 1;
            $params['recurring']['interval'] = 'month';
        } elseif (BillingInterval::Yearly == $billingItem->billingInterval) {
            $params['recurring']['interval_count'] = 1;
            $params['recurring']['interval'] = 'year';
        } elseif (BillingInterval::Quarterly == $billingItem->billingInterval) {
            $params['recurring']['interval_count'] = 3;
            $params['recurring']['interval'] = 'month';
        } elseif (BillingInterval::Semiannually == $billingItem->billingInterval) {
            $params['recurring']['interval_count'] = 6;
            $params['recurring']['interval'] = 'month';
        } else {
            throw new BillingException('Invalid billing interval');
        }

        try {
            $stripe = $this->getStripe();
            $this->stripePrices[$lookupKey] = $stripe->prices->create($params);

            return $this->stripePrices[$lookupKey]->id;
        } catch (StripeError $e) {
            $this->logger->error('Could not create Stripe price', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
