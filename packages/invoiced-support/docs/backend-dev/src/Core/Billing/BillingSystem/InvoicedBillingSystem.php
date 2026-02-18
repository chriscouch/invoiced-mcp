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
use Carbon\CarbonImmutable;
use Invoiced\Client;
use Invoiced\Customer;
use Invoiced\Error\ErrorBase;
use Invoiced\Item;
use Invoiced\Plan;
use Invoiced\Subscription;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class InvoicedBillingSystem extends AbstractBillingSystem implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    const ID = 'invoiced';

    private Client $invoicedClient;
    private array $invoicedCustomers = [];
    private array $invoicedSubscriptions = [];
    private array $invoicedItems = [];
    private array $invoicedPlans = [];

    public function __construct(
        private string $invoicedClientSecret,
        private string $environment,
    ) {
    }

    //
    // BillingSystemInterface
    //

    public function createOrUpdateCustomer(BillingProfile $billingProfile, array $params): void
    {
        // Retrieve and update an existing customer
        $customer = null;
        if ($billingProfile->invoiced_customer) {
            $customer = $this->getCustomer($billingProfile);

            // Update the customer with the new values
            $newValues = $this->invoicedCustomerData($billingProfile, $params);
            // Do not override sales rep
            if (isset($customer->metadata['sales_rep'])) {
                unset($newValues['metadata']['sales_rep']);
            }
            // Merge existing metadata with new values
            $newValues['metadata'] = (object) array_merge((array) $customer->metadata, $newValues['metadata']);

            foreach ($newValues as $k => $v) {
                $customer->$k = $v;
            }
            $customer->save();
        }

        // Create a new customer otherwise
        if (!$customer) {
            try {
                $customer = $this->getInvoicedClient()->Customer->create($this->invoicedCustomerData($billingProfile, $params));
                $this->invoicedCustomers[$billingProfile->id] = $customer;
            } catch (ErrorBase $e) {
                $this->logger->error('Could not create Invoiced customer', ['exception' => $e]);

                throw new BillingException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // Update the reference on the billing profile
        $billingProfile->billing_system = self::ID;
        $billingProfile->invoiced_customer = (string) $customer->id;
        $billingProfile->save();
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

        // Create the subscription with the new items
        $plan = $newItems[0];
        $addons = array_slice($newItems, 1);

        try {
            $this->getInvoicedClient()->Subscription->create([
                'start_date' => $startDate->getTimestamp(),
                'customer' => $customer->id,
                'plan' => $plan['plan'],
                'quantity' => $plan['quantity'],
                'amount' => $plan['amount'],
                'description' => $plan['description'],
                'addons' => $addons,
            ]);
        } catch (ErrorBase $e) {
            $this->logger->error('Could not update create on Invoiced', ['exception' => $e]);

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

        $plan = $newItems[0];
        $addons = array_slice($newItems, 1);

        // Check if there is any change in the subscription or addons
        $hasChange = count($subscription->addons) != count($addons);
        if ($subscription->plan != $plan['plan'] || $subscription->quantity != $plan['quantity'] || $subscription->amount != $plan['amount']) { /* @phpstan-ignore-line */
            $hasChange = true;
        }

        foreach ($subscription->addons as $index => $addon) {
            if (isset($addons[$index])) {
                $newAddon = $addons[$index];
                if ($addon['plan'] != $newAddon['plan'] || $addon['quantity'] != $newAddon['quantity'] || $addon['amount'] != $newAddon['amount']) {
                    $hasChange = true;
                    break;
                }
            }
        }

        // Do not perform an update if there is no change to the current subscription items
        if (!$hasChange) {
            return;
        }

        // Modify the subscription with the new items. This is not prorated
        // because we are rebuilding the subscription without changing the prices.
        $subscription->plan = $plan['plan'];
        $subscription->quantity = $plan['quantity'];
        $subscription->amount = $plan['amount']; /* @phpstan-ignore-line */
        $subscription->description = $plan['description']; /* @phpstan-ignore-line */
        $subscription->addons = $addons;
        $subscription->prorate = $prorate; /* @phpstan-ignore-line */
        if ($prorate) {
            $subscription->proration_date = $prorationDate->getTimestamp(); /* @phpstan-ignore-line */
        }

        try {
            $subscription->save();
        } catch (ErrorBase $e) {
            $this->logger->error('Could not update subscription on Invoiced', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setDefaultPaymentMethod(BillingProfile $billingProfile, string $token): void
    {
        $customer = $this->getCustomer($billingProfile);
        $customer->payment_source = (object) [
            'method' => 'credit_card',
            'invoiced_token' => $token,
        ];

        try {
            $customer->save();
        } catch (ErrorBase $e) {
            $this->logger->error('Could not set default payment method on Invoiced', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function billLineItem(BillingProfile $billingProfile, BillingOneTimeItem $item, bool $billNow): string
    {
        try {
            // obtain the plan for this billable item
            $planId = null;
            if ($item->usageType && $item->billingInterval) {
                $planId = $this->getPlanForUsageCharge($item->usageType, $item->name, $item->billingInterval);
            }

            // ensure the item exists for this billable item
            if ($item->itemId && $item->description) {
                $this->createItemIfNotExists($item->itemId, $item->description);
            }

            $name = $item->description;
            $description = null;
            if ($item->usageType) {
                $name = $item->usageType->getFriendlyName();
                $description = $item->description;
            }

            // retrieve customer from Invoiced and create the plan
            $customer = $this->getCustomer($billingProfile);
            $lineItem = $customer->lineItems()->create([
                'name' => $name,
                'description' => $description,
                'quantity' => $item->quantity,
                'unit_cost' => $item->price->toDecimal(),
                'plan' => $planId,
                'catalog_item' => $item->itemId,
                'period_start' => $item->periodStart?->getTimestamp(),
                'period_end' => $item->periodEnd?->getTimestamp(),
            ]);

            // invoice immediately when requested
            if ($billNow) {
                $customer->invoice();
            }

            return (string) $lineItem->id;
        } catch (ErrorBase $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function cancel(BillingProfile $billingProfile, bool $atPeriodEnd): void
    {
        try {
            $customer = $this->getCustomer($billingProfile);
            $subscription = $this->getSubscription($billingProfile);

            // bill outstanding items upon cancellation
            try {
                $customer->invoice();
            } catch (ErrorBase $e) {
                // do nothing when this operation fails
            }

            if ($atPeriodEnd) {
                $subscription->cancel_at_period_end = true;
                $subscription->save();
            } else {
                $subscription->delete();
            }
        } catch (ErrorBase $e) {
            $this->logger->error('Could not cancel Invoiced subscription', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function reactivate(BillingProfile $billingProfile): void
    {
        $subscription = $this->getSubscription($billingProfile);

        try {
            $subscription->cancel_at_period_end = false;
            $subscription->save();
        } catch (ErrorBase $e) {
            $this->logger->error('Could not reactivate subscription', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getBillingHistory(BillingProfile $billingProfile): array
    {
        try {
            $customer = $this->getCustomer($billingProfile);
            [$invoices] = $this->getInvoicedClient()->Invoice->all(
                [
                    'filter' => [
                        'customer' => $customer->id,
                    ],
                    'sort' => 'date desc',
                ]
            );
        } catch (ErrorBase $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }

        // take retrieved invoice list and place results in common format
        $result = [];

        foreach ($invoices as $invoice) {
            $error = 'past_due' == $invoice->status
                ? 'Unpaid balance due of $'.number_format($invoice->balance, 2)
                : null;

            $result[] = [
                'date' => $invoice->date,
                'amount' => $invoice->total,
                'invoice_url' => $invoice->pdf_url,
                'payment_url' => $invoice->payment_url,
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
        $source = $customer->payment_source;
        if (!$source) { /* @phpstan-ignore-line */
            return [];
        }

        if ('card' == $source['object']) { /* @phpstan-ignore-line */
            return [
                'last4' => $source['last4'], /* @phpstan-ignore-line */
                'exp_month' => $source['exp_month'], /* @phpstan-ignore-line */
                'exp_year' => $source['exp_year'], /* @phpstan-ignore-line */
                'type' => $source['brand'], /* @phpstan-ignore-line */
                'object' => $source['object'],
            ];
        } elseif ('bank_account' == $source['object']) { /* @phpstan-ignore-line */
            return [
                'last4' => $source['last4'], /* @phpstan-ignore-line */
                'bank_name' => $source['bank_name'], /* @phpstan-ignore-line */
                'object' => $source['object'],
            ];
        }

        // do not throw exceptions when source is absent with Invoiced billing system
        return [];
    }

    public function getDiscount(BillingProfile $billingProfile): ?array
    {
        $subscription = $this->getSubscription($billingProfile);
        if (isset($subscription->discounts[0])) {
            return [
                'coupon' => $subscription->discounts[0],
                'origin' => 'invoiced',
            ];
        }

        return null;
    }

    public function isCanceledAtPeriodEnd(BillingProfile $billingProfile): bool
    {
        $subscription = $this->getSubscription($billingProfile);

        return $subscription->cancel_at_period_end;
    }

    public function getNextChargeAmount(BillingProfile $billingProfile): float
    {
        $customer = $this->getCustomer($billingProfile);
        $client = $this->getInvoicedClient();

        // manually request upcoming invoice, convert it to an object and return total if it exists
        try {
            $response = $client->request('get', $customer->getEndpoint().'/upcoming_invoice');

            return (float) $response['body']['total'];
        } catch (ErrorBase $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function isAutoPay(BillingProfile $billingProfile): bool
    {
        $customer = $this->getCustomer($billingProfile);

        return $customer->autopay;
    }

    /**
     * Retrieves the end date of the current subscription billing period in Invoiced.
     *
     * @throws BillingException
     */
    public function getNextBillDate(BillingProfile $billingProfile): ?CarbonImmutable
    {
        $periodEnd = $this->getSubscription($billingProfile)->period_end;

        return $periodEnd ? CarbonImmutable::createFromTimestamp($periodEnd) : null;
    }

    public function getUpdatePaymentInfoUrl(BillingProfile $billingProfile): ?string
    {
        $customer = $this->getCustomer($billingProfile);

        // Grabs the client ID from the statement PDF URL. This
        // is a cheat but gives us the deep link to the update
        // payment information page. This will break if:
        // 1. payment information URL changes, or
        // 2. statement PDF URL structure changes
        // Example: https://invoiced.com/statements/mjpl0dvplvveqvmzqs5f74l9/iayVK4gvYzhlHp1vkH8wXAC1jUNdmgccFNWiAbB3we7c5pCi/pdf
        $url = $customer->statement_pdf_url;
        if (preg_match('/^(.+)\/statements\/(\w+)\/(\w+)\/pdf$/', $url, $matches)) {
            return 'https://invoicedinc.invoiced.com/paymentInfo/'.$matches[3];
        }

        return null;
    }

    public function getCurrentSubscription(BillingProfile $billingProfile): BillingSystemSubscription
    {
        // Look up subscription on Invoiced
        $subscription = $this->getSubscription($billingProfile);

        // Determine billing interval
        $plan = $this->getPlan($subscription['plan']);
        if ('month' == $plan['interval'] && 1 == $plan['interval_count']) {
            $billingInterval = BillingInterval::Monthly;
        } elseif ('year' == $plan['interval'] && 1 == $plan['interval_count']) {
            $billingInterval = BillingInterval::Yearly;
        } elseif ('month' == $plan['interval'] && 3 == $plan['interval_count']) {
            $billingInterval = BillingInterval::Quarterly;
        } elseif ('month' == $plan['interval'] && 6 == $plan['interval_count']) {
            $billingInterval = BillingInterval::Semiannually;
        } else {
            throw new BillingException('Billing interval not recognized. Interval: '.$plan['interval'].'. Interval Count: '.$plan['interval_count']);
        }

        // Determine recurring total
        $total = $this->getInvoicedPlanTotal($subscription->quantity, $subscription->amount, $plan); /* @phpstan-ignore-line */
        foreach ($subscription->addons as $addon) {
            $addonPlan = $this->getPlan($addon['plan']);
            $addonTotal = $this->getInvoicedPlanTotal($addon['quantity'], $addon['amount'], $addonPlan);
            $total = $total->add($addonTotal);
        }

        return new BillingSystemSubscription(
            billingInterval: $billingInterval,
            total: $total,
            paused: $subscription->paused,
        );
    }

    //
    // Helpers
    //

    public function setClient(Client $client): void
    {
        $this->invoicedClient = $client;
    }

    /**
     * Retrieves Invoiced client, instantiating one if necessary.
     */
    private function getInvoicedClient(): Client
    {
        if (!isset($this->invoicedClient)) {
            $this->invoicedClient = new Client($this->invoicedClientSecret, 'production' != $this->environment);
        }

        return $this->invoicedClient;
    }

    /**
     * @throws ErrorBase
     */
    private function getPlan(string $id): Plan
    {
        if (!isset($this->invoicedPlans[$id])) {
            $this->invoicedPlans[$id] = $this->getInvoicedClient()->Plan->retrieve($id);
        }

        return $this->invoicedPlans[$id];
    }

    /**
     * Attempts to retrieve customer from Invoiced.
     *
     * @throws BillingException
     */
    private function getCustomer(BillingProfile $billingProfile): Customer
    {
        if (!isset($this->invoicedCustomers[$billingProfile->id])) {
            if (!$billingProfile->invoiced_customer) {
                throw new BillingException('Missing Invoiced customer ID');
            }

            try {
                $customer = $this->getInvoicedClient()->Customer->retrieve((string) $billingProfile->invoiced_customer);
                $this->invoicedCustomers[$billingProfile->id] = $customer;
            } catch (ErrorBase $e) {
                $this->logger->debug('Could not retrieve customer from Invoiced: '.$e->getMessage());

                throw new BillingException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->invoicedCustomers[$billingProfile->id];
    }

    /**
     * Returns data for this model to be set when creating Invoiced customers.
     */
    private function invoicedCustomerData(BillingProfile $billingProfile, array $params): array
    {
        $result = [
            'name' => $params['company'] ?? $billingProfile->name,
            'email' => $params['email'] ?? null,
            'phone' => $params['phone'] ?? null,
            'attention_to' => $params['person'] ?? null,
            'address1' => $params['address1'] ?? null,
            'address2' => $params['address2'] ?? null,
            'city' => $params['city'] ?? null,
            'state' => $params['state'] ?? null,
            'postal_code' => $params['postal_code'] ?? null,
            'country' => $params['country'] ?? null,
            'autopay' => $params['autopay'] ?? null,
            'payment_terms' => $params['payment_terms'] ?? null,
            'metadata' => [],
        ];

        if ($referredBy = $billingProfile->referred_by) {
            $result['metadata']['referred_by'] = $referredBy;
        }

        if (isset($params['sales_rep'])) {
            $result['metadata']['sales_rep'] = $params['sales_rep'];
        }

        foreach ($result as $k => &$v) {
            if (null === $v) {
                unset($result[$k]);
            } elseif ('' === $v) {
                $v = null;
            }
        }

        return $result;
    }

    /**
     * Attempts to retrieve subscription related to customer.
     * First retrieves list of all subscriptions associated with customer,
     * then locates active subscription from list, memoizes and returns it.
     *
     * @throws BillingException
     */
    private function getSubscription(BillingProfile $billingProfile): Subscription
    {
        if (!isset($this->invoicedSubscriptions[$billingProfile->id])) {
            if (!$billingProfile->invoiced_customer) {
                throw new BillingException('Missing Invoiced customer ID');
            }

            try {
                [$subscriptions] = $this->getInvoicedClient()->Subscription->all([
                    'filter' => [
                        'customer' => $billingProfile->invoiced_customer,
                    ],
                ]);

                if (0 == count($subscriptions)) {
                    throw new BillingException('Could not find an active subscription in the billing system.');
                }

                if (count($subscriptions) > 1) {
                    throw new BillingException('Invoiced customer has '.count($subscriptions).' active subscriptions.');
                }

                $this->invoicedSubscriptions[$billingProfile->id] = $subscriptions[0];
            } catch (ErrorBase $e) {
                $this->logger->debug('Could not retrieve subscription from Invoiced: '.$e->getMessage());

                throw new BillingException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->invoicedSubscriptions[$billingProfile->id];
    }

    /**
     * Registers a payment source on Invoiced.
     *
     * @throws BillingException
     */
    public function registerPaymentSource(BillingProfile $billingProfile, array $values): void
    {
        try {
            $this->getInvoicedClient()->request('POST', '/customers/'.$billingProfile->invoiced_customer.'/import_payment_source', $values);
        } catch (ErrorBase $e) {
            $this->logger->debug('Could not register payment source on Invoiced: '.$e->getMessage());

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Builds Invoiced subscription items.
     *
     * @param BillingSubscriptionItem[] $billingItems
     */
    private function buildSubscriptionItems(array $billingItems): array
    {
        $items = [];

        foreach ($billingItems as $billingItem) {
            if ($billingItem->product) {
                $plan = $this->getPlanForProduct($billingItem->product, $billingItem);
            } elseif ($billingItem->usageType) {
                $plan = $this->getPlanForUsage($billingItem->usageType, $billingItem);
            } else {
                $plan = $this->getPlanForPAYG($billingItem);
            }

            $items[] = [
                'plan' => $plan,
                'quantity' => $billingItem->quantity,
                'amount' => $billingItem->price->toDecimal(),
                'description' => $billingItem->description ?: null,
            ];
        }

        return $items;
    }

    /**
     * @throws BillingException
     */
    private function getPlanForProduct(Product $product, BillingSubscriptionItem $billingItem): string
    {
        $itemId = 'invoiced-product-'.$product->id;
        $this->createItemIfNotExists($itemId, $product->name);

        return $this->getOrCreatePlan($itemId, $billingItem->name, $billingItem->billingInterval);
    }

    /**
     * @throws BillingException
     */
    private function getPlanForUsage(UsageType $usageType, BillingSubscriptionItem $billingItem): string
    {
        $itemId = 'invoiced-usage-'.$usageType->getName();
        $this->createItemIfNotExists($itemId, $usageType->getFriendlyName());

        return $this->getOrCreatePlan($itemId, $billingItem->name, $billingItem->billingInterval);
    }

    /**
     * @throws BillingException
     */
    private function getPlanForPAYG(BillingSubscriptionItem $billingItem): string
    {
        $itemId = 'invoiced-pay-as-you-go';
        $name = 'Pay-As-You-Go';
        $this->createItemIfNotExists($itemId, $name);

        return $this->getOrCreatePlan($itemId, $billingItem->name, $billingItem->billingInterval);
    }

    /**
     * @throws BillingException
     */
    private function getPlanForUsageCharge(UsageType $usageType, string $name, BillingInterval $billingInterval): string
    {
        $itemId = 'invoiced-usage-'.$usageType->getName();
        $this->createItemIfNotExists($itemId, $usageType->getFriendlyName());

        return $this->getOrCreatePlan($itemId, $name, $billingInterval);
    }

    /**
     * Used for testing.
     */
    public function setItem(Item $item): void
    {
        $this->invoicedItems[$item->id] = $item;
    }

    /**
     * Used for testing.
     */
    public function setPlan(Plan $plan): void
    {
        $this->invoicedPlans[$plan->id] = $plan;
    }

    /**
     * @throws BillingException
     */
    private function createItemIfNotExists(string $id, string $name): void
    {
        if (isset($this->invoicedItems[$id])) {
            return;
        }

        $invoiced = $this->getInvoicedClient();

        // Check if exists on Invoiced first
        try {
            $item = $invoiced->Item->retrieve($id);
            $this->invoicedItems[$id] = $item;

            return;
        } catch (ErrorBase) {
            // do nothing on error
        }

        // Create the item if it could not be retrieved
        try {
            $this->invoicedItems[$id] = $invoiced->Item->create([
                'id' => $id,
                'name' => $name,
                'avalara_tax_code' => self::AVALARA_TAX_CODE,
            ]);
        } catch (ErrorBase $e) {
            $this->logger->error('Could not create Invoiced item', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws BillingException
     */
    private function getOrCreatePlan(string $itemId, string $name, BillingInterval $billingInterval): string
    {
        $planId = $itemId.'-'.$billingInterval->getIdName();

        // Check if exists on Invoiced first
        try {
            $this->getPlan($planId);

            return $planId;
        } catch (ErrorBase) {
            // do nothing on error
        }

        // Create the plan if it could not be retrieved
        $params = [
            'id' => $planId,
            'name' => $name,
            'catalog_item' => $itemId,
            'pricing_mode' => 'custom',
            'currency' => 'usd',
        ];

        // Determine billing interval
        if (BillingInterval::Monthly == $billingInterval) {
            $params['interval_count'] = 1;
            $params['interval'] = 'month';
        } elseif (BillingInterval::Yearly == $billingInterval) {
            $params['interval_count'] = 1;
            $params['interval'] = 'year';
        } elseif (BillingInterval::Quarterly == $billingInterval) {
            $params['interval_count'] = 3;
            $params['interval'] = 'month';
        } elseif (BillingInterval::Semiannually == $billingInterval) {
            $params['interval_count'] = 6;
            $params['interval'] = 'month';
        } else {
            throw new BillingException('Invalid billing interval');
        }

        try {
            $this->invoicedPlans[$planId] = $this->getInvoicedClient()->Plan->create($params);

            return $planId;
        } catch (ErrorBase $e) {
            $this->logger->error('Could not create Invoiced plan', ['exception' => $e]);

            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function getInvoicedPlanTotal(int $quantity, ?float $amount, Plan $plan): Money
    {
        if ('custom' == $plan['pricing_mode']) {
            return Money::fromDecimal('usd', ($amount ?? 0) * $quantity);
        }

        if ('per_unit' == $plan['pricing_mode']) {
            return Money::fromDecimal('usd', $plan['amount'] * $quantity);
        }

        throw new BillingException('Unsupported plan pricing mode: '.$plan['pricing_mode']);
    }
}
