<?php

namespace App\Entity\Forms;

class NewCustomer
{
    private string $name;
    private ?string $address1 = null;
    private ?string $address2 = null;
    private ?string $city = null;
    private ?string $state = null;
    private ?string $postal_code = null;
    private ?string $country = null;
    private ?string $billingEmail = null;
    private ?string $billingPhone = null;
    private string $salesRep;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAddress1(): ?string
    {
        return $this->address1;
    }

    public function setAddress1(?string $address1): void
    {
        $this->address1 = $address1;
    }

    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    public function setAddress2(?string $address2): void
    {
        $this->address2 = $address2;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    public function getPostalCode(): ?string
    {
        return $this->postal_code;
    }

    public function setPostalCode(?string $postal_code): void
    {
        $this->postal_code = $postal_code;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }

    public function getBillingEmail(): ?string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(?string $billingEmail): void
    {
        $this->billingEmail = $billingEmail;
    }

    public function getBillingPhone(): ?string
    {
        return $this->billingPhone;
    }

    public function setBillingPhone(?string $billingPhone): void
    {
        $this->billingPhone = $billingPhone;
    }

    public function getSalesRep(): string
    {
        return $this->salesRep;
    }

    public function setSalesRep(string $salesRep): void
    {
        $this->salesRep = $salesRep;
    }
}
