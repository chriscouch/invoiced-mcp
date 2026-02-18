<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'BlockListEmailAddresses')]
class BlockListEmailAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string')]
    private string $email;
    #[ORM\Column(type: 'smallint')]
    private int $reason;

    public function __toString(): string
    {
        return $this->email;
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

    public function getReason(): int
    {
        return $this->reason;
    }

    public function setReason(int $reason): void
    {
        $this->reason = $reason;
    }

    public function getReasonName(): string
    {
        return match ($this->reason) {
            2 => 'Spam Complaints (3 or more)',
            default => 'Bounce',
        };
    }
}
