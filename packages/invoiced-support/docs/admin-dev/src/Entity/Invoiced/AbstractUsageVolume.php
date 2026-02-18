<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

abstract class AbstractUsageVolume
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected int $id;

    #[ORM\Column(type: 'integer')]
    protected int $tenant_id;

    #[ORM\Column]
    protected string $month;

    #[ORM\Column(type: 'integer')]
    protected int $count;

    #[ORM\Column(type: 'boolean')]
    protected bool $do_not_bill;

    public function getType(): string
    {
        $parts = explode('\\', get_called_class());

        return end($parts);
    }

    public function __toString(): string
    {
        return $this->month.' | '.$this->count;
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

    public function getMonth(): DateTimeInterface
    {
        return CarbonImmutable::createFromFormat('Ym', $this->month); /* @phpstan-ignore-line */
    }

    public function setMonth(DateTimeInterface $month): void
    {
        $this->month = $month->format('Ym');
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    public function isDoNotBill(): bool
    {
        return $this->do_not_bill;
    }

    public function setDoNotBill(bool $do_not_bill): void
    {
        $this->do_not_bill = $do_not_bill;
    }
}
