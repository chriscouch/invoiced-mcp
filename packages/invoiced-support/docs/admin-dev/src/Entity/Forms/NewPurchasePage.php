<?php

namespace App\Entity\Forms;

use App\Entity\Invoiced\BillingProfile;
use App\Entity\Invoiced\Company;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class NewPurchasePage
{
    private string $country = 'US';
    private int $billingInterval = 1;
    private Collection $productPricingPlans;
    private Collection $usagePricingPlans;
    private DateTime $expirationDate;
    private float $activationFee = 0;
    private ?BillingProfile $billingProfile = null;
    private ?Company $tenant = null;
    private ?string $note = null;
    private int $paymentTerms = 1;
    private int $reason = 1;

    public function __construct()
    {
        $this->expirationDate = new DateTime('+7 days');
        $this->productPricingPlans = new ArrayCollection();
        $this->usagePricingPlans = new ArrayCollection();
    }

    public function getBillingProfile(): ?BillingProfile
    {
        return $this->billingProfile;
    }

    public function setBillingProfile(?BillingProfile $billingProfile): void
    {
        $this->billingProfile = $billingProfile;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getBillingInterval(): int
    {
        return $this->billingInterval;
    }

    public function setBillingInterval(int $billingInterval): void
    {
        $this->billingInterval = $billingInterval;
    }

    public function getExpirationDate(): DateTime
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(DateTime $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }

    public function getProductPricingPlans(): Collection
    {
        return $this->productPricingPlans;
    }

    public function setProductPricingPlans(Collection $productPricingPlans): void
    {
        $this->productPricingPlans = $productPricingPlans;
    }

    public function getUsagePricingPlans(): Collection
    {
        return $this->usagePricingPlans;
    }

    public function setUsagePricingPlans(Collection $usagePricingPlans): void
    {
        $this->usagePricingPlans = $usagePricingPlans;
    }

    public function getTenant(): ?Company
    {
        return $this->tenant;
    }

    public function setTenant(?Company $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getActivationFee(): float
    {
        return $this->activationFee;
    }

    public function setActivationFee(float $activationFee): void
    {
        $this->activationFee = $activationFee;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getPaymentTerms(): int
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(int $paymentTerms): void
    {
        $this->paymentTerms = $paymentTerms;
    }

    public function getReason(): int
    {
        return $this->reason;
    }

    public function setReason(int $reason): void
    {
        $this->reason = $reason;
    }
}
