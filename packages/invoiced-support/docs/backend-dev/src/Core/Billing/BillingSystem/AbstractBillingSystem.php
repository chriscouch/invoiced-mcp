<?php

namespace App\Core\Billing\BillingSystem;

use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingState;
use Carbon\CarbonImmutable;

abstract class AbstractBillingSystem implements BillingSystemInterface
{
    protected const AVALARA_TAX_CODE = 'SW052000'; // ASP - hosted software (SaaS), https://taxcode.avatax.avalara.com/

    //
    // BillingSystemInterface
    //

    public function getBillingState(BillingProfile $billingProfile): BillingState
    {
        return new BillingState(
            $this->getPaymentSourceInfo($billingProfile),
            $this->getDiscount($billingProfile),
            $this->isCanceledAtPeriodEnd($billingProfile),
            $this->getNextBillDate($billingProfile),
            $this->isAutoPay($billingProfile),
            $this->getNextChargeAmount($billingProfile),
        );
    }

    //
    // Abstract methods
    //

    /**
     * Retrieves default card info if it exists.
     *
     * @throws BillingException
     */
    abstract public function getPaymentSourceInfo(BillingProfile $billingProfile): array;

    /**
     * Retrieves discount information if it exists, else null.
     *
     * @throws BillingException
     */
    abstract public function getDiscount(BillingProfile $billingProfile): ?array;

    /**
     * Returns true if subscription will cancel at period end, else false.
     *
     * @throws BillingException
     */
    abstract public function isCanceledAtPeriodEnd(BillingProfile $billingProfile): bool;

    /**
     * Retrieves the next charge amount from the next invoice if it exists, else 0.0.
     *
     * @throws BillingException
     */
    abstract public function getNextChargeAmount(BillingProfile $billingProfile): float;

    /**
     * Returns true if the customer is set up for AutoPay.
     *
     * @throws BillingException
     */
    abstract public function isAutoPay(BillingProfile $billingProfile): bool;

    /**
     * Retrieves next billing date.
     *
     * @throws BillingException
     */
    abstract public function getNextBillDate(BillingProfile $billingProfile): ?CarbonImmutable;
}
