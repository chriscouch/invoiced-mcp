<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'Members')]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $user_id;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $role;
    #[ORM\Column(type: 'integer')]
    private int $expires;
    #[ORM\Column(nullable: true)]
    private ?int $last_accessed;
    #[ORM\Column(type: 'string')]
    private string $restriction_mode = 'none';
    #[ORM\Column(nullable: true)]
    private ?string $restrictions;
    #[ORM\Column(type: 'boolean')]
    private bool $notifications = true;
    #[ORM\Column(type: 'boolean')]
    private bool $subscribe_all = true;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\User', inversedBy: 'companyMembers')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Company', inversedBy: 'companyMembers')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;

    public function __construct()
    {
        $this->setCreatedAt(CarbonImmutable::now());
    }

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenant_id = $tenant->getId();
    }

    /**
     * Determines if member is expired.
     * Used when getting members in Company entity.
     */
    public function isExpired(): bool
    {
        return 0 < $this->expires && $this->expires <= CarbonImmutable::now()->getTimestamp();
    }

    public function __toString(): string
    {
        return $this->tenant->getName().' | '.$this->user->getFullName();
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

    public function setUpdatedAt(?DateTimeInterface $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function getTenantId(): int
    {
        return $this->tenant_id;
    }

    public function setTenantId(int $tenant_id): void
    {
        $this->tenant_id = $tenant_id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getExpires(): ?DateTimeInterface
    {
        if (!$this->expires) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->expires);
    }

    public function setExpires(DateTimeInterface $expires): void
    {
        $this->expires = $expires->getTimestamp();
    }

    public function getLastAccessed(): ?DateTimeInterface
    {
        if (!$this->last_accessed) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->last_accessed);
    }

    public function setLastAccessed(?int $last_accessed): void
    {
        $this->last_accessed = $last_accessed;
    }

    public function setRestrictions(?string $restrictions): void
    {
        $this->restrictions = $restrictions;
    }

    public function getRestrictions(): ?string
    {
        return $this->restrictions;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getRestrictionMode(): string
    {
        return $this->restriction_mode;
    }

    public function setRestrictionMode(string $restriction_mode): void
    {
        $this->restriction_mode = $restriction_mode;
    }

    public function getNotifications(): bool
    {
        return $this->notifications;
    }

    public function setNotifications(bool $notifications): void
    {
        $this->notifications = $notifications;
    }

    public function isSubscribeAll(): bool
    {
        return $this->subscribe_all;
    }

    public function setSubscribeAll(bool $subscribeAll): void
    {
        $this->subscribe_all = $subscribeAll;
    }
}
