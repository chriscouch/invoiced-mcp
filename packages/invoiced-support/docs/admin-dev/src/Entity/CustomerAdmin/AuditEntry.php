<?php

namespace App\Entity\CustomerAdmin;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cs_audit_entries')]
class AuditEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $timestamp;
    #[ORM\Column(type: 'string', length: 255)]
    private string $user;
    #[ORM\Column(type: 'string', length: 255)]
    private string $action;
    #[ORM\Column(type: 'string', length: 255)]
    private string $context;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(DateTimeInterface $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }
}
