<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'Users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $email;
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(groups: ['registration'])]
    #[Assert\Length(min: 12, groups: ['registration'])]
    #[Assert\Regex(pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])/', message: 'Password must contain a lowercase letter, an uppercase letter, a number and a special character.', groups: ['registration'])]
    private string $password;
    #[ORM\Column(type: 'string', length: 255)]
    private string $first_name;
    #[ORM\Column(type: 'string', length: 255)]
    private string $last_name;
    #[ORM\Column(type: 'string', length: 45)]
    private string $ip;
    #[ORM\Column(type: 'boolean')]
    private bool $enabled;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $default_company_id;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $google_claimed_id;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $intuit_claimed_id;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $microsoft_claimed_id;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $xero_claimed_id;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $authy_id;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $support_pin;
    #[ORM\Column(type: 'boolean')]
    private bool $verified_2fa;
    #[ORM\Column(type: 'boolean')]
    private bool $disable_ip_check;
    #[ORM\OneToOne(targetEntity: '\App\Entity\Invoiced\Company', cascade: ['persist', 'remove'])]
    private ?Company $default_company;
    #[ORM\OneToMany(targetEntity: 'App\Entity\Invoiced\Member', mappedBy: 'user', orphanRemoval: true, cascade: ['persist'])]
    private Collection $companyMembers;
    #[ORM\OneToMany(targetEntity: 'App\Entity\Invoiced\CanceledCompany', mappedBy: 'creator', orphanRemoval: true)]
    private Collection $canceledCompanies;
    #[ORM\OneToMany(targetEntity: 'App\Entity\Invoiced\AccountSecurityEvent', mappedBy: 'user', orphanRemoval: true)]
    private Collection $accountSecurityEvents;

    public function __construct()
    {
        $this->companyMembers = new ArrayCollection();
        $this->canceledCompanies = new ArrayCollection();
        $this->accountSecurityEvents = new ArrayCollection();
    }

    public function getAvatarEmail(): ?string
    {
        return $this->email;
    }

    public function getFullName(): string
    {
        $name = trim($this->first_name.' '.$this->last_name);
        if ($name) {
            return $name;
        }

        if ($this->email) {
            return $this->email;
        }

        return '#'.$this->id;
    }

    public function getAvatar(int $size = 40, string $default = 'mp'): string
    {
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($this->email)));
        $url .= "?s=$size&d=$default";

        return $url;
    }

    public function twoFactorEnabled(): bool
    {
        return $this->authy_id > 0 && $this->verified_2fa;
    }

    /**
     * @return Collection|Member[]
     */
    public function getCompanyMembers(): Collection
    {
        $currentMembers = new ArrayCollection();
        foreach ($this->companyMembers as $companyMember) {
            if (!$companyMember->isExpired() && $companyMember->getTenant()->getName()) {
                $currentMembers->add($companyMember);
            }
        }

        return $currentMembers;
    }

    /**
     * Edited to reverse and show no more than 5.
     *
     * @return Collection|AccountSecurityEvent[]
     */
    public function getAccountSecurityEvents(): Collection
    {
        $count = $this->accountSecurityEvents->count();
        $reversedCollection = new ArrayCollection();
        for ($i = $count - 1; $i >= 0 && $i >= $count - 5; --$i) {
            $reversedCollection->add($this->accountSecurityEvents->get($i));
        }

        return $reversedCollection;
    }

    /**
     * Edited to only return members that aren't expired.
     *
     * @return Collection|CanceledCompany[]
     */
    public function getCanceledCompanies(): Collection
    {
        return $this->canceledCompanies;
    }

    public function __toString(): string
    {
        return $this->getFullName();
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

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function setFirstName(string $first_name): void
    {
        $this->first_name = $first_name;
    }

    public function getLastName(): string
    {
        return $this->last_name;
    }

    public function setLastName(string $last_name): void
    {
        $this->last_name = $last_name;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
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

    public function getDefaultCompanyId(): ?int
    {
        return $this->default_company_id;
    }

    public function setDefaultCompanyId(?int $default_company_id): void
    {
        $this->default_company_id = $default_company_id;
    }

    public function getGoogleClaimedId(): ?string
    {
        return $this->google_claimed_id;
    }

    public function setGoogleClaimedId(?string $google_claimed_id): void
    {
        $this->google_claimed_id = $google_claimed_id;
    }

    public function getIntuitClaimedId(): ?string
    {
        return $this->intuit_claimed_id;
    }

    public function setIntuitClaimedId(?string $intuit_claimed_id): void
    {
        $this->intuit_claimed_id = $intuit_claimed_id;
    }

    public function getMicrosoftClaimedId(): ?string
    {
        return $this->microsoft_claimed_id;
    }

    public function setMicrosoftClaimedId(?string $microsoft_claimed_id): void
    {
        $this->microsoft_claimed_id = $microsoft_claimed_id;
    }

    public function getXeroClaimedId(): ?string
    {
        return $this->xero_claimed_id;
    }

    public function setXeroClaimedId(?string $xero_claimed_id): void
    {
        $this->xero_claimed_id = $xero_claimed_id;
    }

    public function getAuthyId(): ?int
    {
        return $this->authy_id;
    }

    public function setAuthyId(?int $authy_id): void
    {
        $this->authy_id = $authy_id;
    }

    public function getSupportPin(): ?int
    {
        return $this->support_pin;
    }

    public function setSupportPin(?int $support_pin): void
    {
        $this->support_pin = $support_pin;
    }

    public function isVerified2fa(): bool
    {
        return $this->verified_2fa;
    }

    public function setVerified2fa(bool $verified_2fa): void
    {
        $this->verified_2fa = $verified_2fa;
    }

    public function getDefaultCompany(): ?Company
    {
        return $this->default_company;
    }

    public function setDefaultCompany(?Company $default_company): void
    {
        $this->default_company = $default_company;
    }

    public function isDisableIpCheck(): bool
    {
        return $this->disable_ip_check;
    }

    public function setDisableIpCheck(bool $disable_ip_check): void
    {
        $this->disable_ip_check = $disable_ip_check;
    }
}
