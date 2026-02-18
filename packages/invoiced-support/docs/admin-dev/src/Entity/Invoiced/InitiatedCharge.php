<?php

namespace App\Entity\Invoiced;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'InitiatedCharges')]
class InitiatedCharge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\OneToOne(targetEntity: '\App\Entity\Invoiced\Company')]
    private Company $tenant;
    #[ORM\Column(type: 'string')]
    private string $correlation_id;
    #[ORM\Column(type: 'string')]
    private string $gateway;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private string $amount;
    #[ORM\Column(type: 'string')]
    private string $currency;
    #[ORM\Column(type: 'string')]
    private string $charge;
    #[ORM\Column(type: 'string')]
    private string $parameters;
    #[ORM\Column(type: 'string')]
    private string $application_source;
    #[ORM\Column(type: 'integer')]
    private int $source_id;
    #[ORM\Column(type: 'integer')]
    private int $customer_id;
    #[ORM\Column(type: 'integer')]
    private int $merchant_account_id;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;

    #[ORM\OneToMany(mappedBy: 'initiated_charge', targetEntity: InitiatedChargeDocument::class, orphanRemoval: true)]
    private Collection $initiatedChargeDocuments;

    public function __construct()
    {
        $this->initiatedChargeDocuments = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTenantId(): int
    {
        return $this->tenant_id;
    }

    public function setTenantId(int $tenant_id): void
    {
        $this->tenant_id = $tenant_id;
    }

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getCorrelationId(): string
    {
        return $this->correlation_id;
    }

    public function setCorrelationId(string $correlation_id): void
    {
        $this->correlation_id = $correlation_id;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getCharge(): string
    {
        return $this->charge;
    }

    public function setCharge(string $charge): void
    {
        $this->charge = $charge;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(DateTimeImmutable $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?DateTimeInterface $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function setGateway(string $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function getInitiatedChargeDocuments(): Collection
    {
        return $this->initiatedChargeDocuments;
    }

    public function setInitiatedChargeDocuments(Collection $initiatedChargeDocuments): void
    {
        $this->initiatedChargeDocuments = $initiatedChargeDocuments;
    }

    // virtual fields
    private string $request;
    private string $response;

    public function getResponse(): string
    {
        return $this->response;
    }

    public function setResponse(string $response): void
    {
        $this->response = $response;
    }

    public function getRequest(): string
    {
        return $this->request;
    }

    public function setRequest(string $request): void
    {
        $this->request = $request;
    }

    public function getDocuments(): string
    {
        return implode("\r\n", $this->initiatedChargeDocuments->map(fn (InitiatedChargeDocument $item) => $item->__toString())->toArray());
    }

    public function getParameters(): string
    {
        return $this->parameters;
    }

    public function setParameters(string $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getApplicationSource(): string
    {
        return $this->application_source;
    }

    public function setApplicationSource(string $application_source): void
    {
        $this->application_source = $application_source;
    }

    public function getSourceId(): int
    {
        return $this->source_id;
    }

    public function setSourceId(int $source_id): void
    {
        $this->source_id = $source_id;
    }

    public function getCustomerId(): int
    {
        return $this->customer_id;
    }

    public function setCustomerId(int $customer_id): void
    {
        $this->customer_id = $customer_id;
    }

    public function getMerchantAccountId(): int
    {
        return $this->merchant_account_id;
    }

    public function setMerchantAccountId(int $merchant_account_id): void
    {
        $this->merchant_account_id = $merchant_account_id;
    }
}
