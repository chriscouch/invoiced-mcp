<?php

namespace App\Core\Billing\Interfaces;

use App\Companies\Models\Company;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use App\Core\Billing\ValueObjects\BillingState;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\Billing\ValueObjects\BillingSystemSubscription;
use Carbon\CarbonImmutable;

interface BillingSystemInterface
{
    /**
     * Creates or updates a customer in the billing system for the given billing profile.
     * This function must modify the billing profile with the billing system and metadata.
     *
     * @throws BillingException
     */
    public function createOrUpdateCustomer(BillingProfile $billingProfile, array $params): void;

    /**
     * Creates a new subscription on the billing system using the given items.
     * This method should only be called for a new subscriber. If this is an
     * existing subscriber then updateSubscription() should be used instead.
     *
     * @param BillingSubscriptionItem[] $subscriptionItems
     *
     * @throws BillingException
     */
    public function createSubscription(BillingProfile $billingProfile, array $subscriptionItems, CarbonImmutable $startDate): void;

    /**
     * Updates an existing subscription on the billing system using the given items.
     * This method should only be called for an existing subscriber. When performing
     * the update in the billing system, it should be indicated whether the change
     * should be prorated.
     *
     * @param BillingSubscriptionItem[] $subscriptionItems
     *
     * @throws BillingException
     */
    public function updateSubscription(BillingProfile $billingProfile, array $subscriptionItems, bool $prorate, CarbonImmutable $prorationDate): void;

    /**
     * Sets the default payment method on file using a token (representing tokenized payment info).
     *
     * @throws BillingException
     */
    public function setDefaultPaymentMethod(BillingProfile $billingProfile, string $token): void;

    /**
     * Bills an account for a one-time line item.
     *
     * @param bool $billNow indicates whether the line item should be invoiced now or added to the account's next invoice
     *
     * @throws BillingException if the charge is not successfully billed
     */
    public function billLineItem(BillingProfile $billingProfile, BillingOneTimeItem $item, bool $billNow): string;

    /**
     * Cancels the subscription through the billing system.
     *
     * @throws BillingException
     */
    public function cancel(BillingProfile $billingProfile, bool $atPeriodEnd): void;

    /**
     * Reactivates the subscription through the billing system.
     *
     * @throws BillingException
     */
    public function reactivate(BillingProfile $billingProfile): void;

    /**
     * Retrieves the current subscription from the billing system
     * for auditing and syncing purposes.
     *
     * @throws BillingException
     */
    public function getCurrentSubscription(BillingProfile $billingProfile): BillingSystemSubscription;

    /**
     * Retrieves the billing state of the account from the billing system.
     *
     * @throws BillingException
     */
    public function getBillingState(BillingProfile $billingProfile): BillingState;

    /**
     * Gets the billing history for the account through the billing system.
     *
     * @throws BillingException
     */
    public function getBillingHistory(BillingProfile $billingProfile): array;

    /**
     * Gets the URL where a company can add or update their payment information.
     *
     * @throws BillingException
     */
    public function getUpdatePaymentInfoUrl(BillingProfile $billingProfile): ?string;
}
