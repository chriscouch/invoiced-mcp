<?php

namespace App\Entity\CustomerAdmin;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cs_new_accounts')]
class NewAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $billingProfileId = 0;
    #[Assert\NotBlank]
    #[Assert\Email]
    #[ORM\Column(type: 'string', length: 255)]
    private string $email = '';
    #[Assert\Country]
    #[ORM\Column(type: 'string', length: 2)]
    private string $country = 'US';
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $first_name = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $last_name = null;
    #[ORM\Column(type: 'text')]
    private string $changeset;
    private Collection $productPricingPlans;
    private Collection $usagePricingPlans;

    public function __construct()
    {
        $this->productPricingPlans = new ArrayCollection();
        $this->usagePricingPlans = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->changeset;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(?string $first_name): void
    {
        $this->first_name = $first_name;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(?string $last_name): void
    {
        $this->last_name = $last_name;
    }

    public function getChangeset(): string
    {
        return $this->changeset;
    }

    public function setChangeset(string $changeset): void
    {
        $this->changeset = $changeset;
    }

    public function getChangesetObject(): object
    {
        return (object) json_decode($this->changeset);
    }

    public function getBillingProfileId(): int
    {
        return $this->billingProfileId;
    }

    public function setBillingProfileId(int $billingProfileId): void
    {
        $this->billingProfileId = $billingProfileId;
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
}
