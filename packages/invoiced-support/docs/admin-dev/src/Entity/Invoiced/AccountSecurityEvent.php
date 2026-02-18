<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'AccountSecurityEvents')]
class AccountSecurityEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $user_id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $type;
    #[ORM\Column(type: 'string', length: 45)]
    private string $ip;
    #[ORM\Column(type: 'string', length: 255)]
    private string $user_agent;
    #[ORM\Column(type: 'string', length: 255)]
    private string $description;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $auth_strategy;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\User', inversedBy: 'accountSecurityEvents')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function __toString(): string
    {
        return $this->type.' | '.$this->created_at->format('Y-m-d H:i:s');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    public function getUserAgent(): string
    {
        return $this->user_agent;
    }

    public function setUserAgent(string $user_agent): void
    {
        $this->user_agent = $user_agent;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getAuthStrategy(): ?string
    {
        return $this->auth_strategy;
    }

    public function setAuthStrategy(?string $auth_strategy): void
    {
        $this->auth_strategy = $auth_strategy;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        // Fixes parsing of timestamp using database timezone (UTC)
        return new CarbonImmutable($this->created_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
    }

    public function setCreatedAt(DateTimeInterface $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        if ($this->updated_at) {
            // Fixes parsing of timestamp using database timezone (UTC)
            return new CarbonImmutable($this->updated_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
        }

        return null;
    }

    public function setUpdatedAt(DateTimeInterface $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
