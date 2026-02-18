<?php

namespace App\Entity\CustomerAdmin;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cs_users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $first_name;
    #[ORM\Column(type: 'string', length: 255)]
    private string $last_name;
    #[ORM\Column(type: 'string', length: 255)]
    private string $email;
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(groups: ['registration'])]
    #[Assert\Length(min: 12, groups: ['registration'])]
    #[Assert\Regex(pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])/', message: 'Password must contain a lowercase letter, an uppercase letter, a number and a special character.', groups: ['registration'])]
    private string $password;
    #[ORM\Column(type: 'string', length: 255)]
    private string $role;
    #[ORM\Column(type: 'string', length: 255)]
    private string $time_zone = 'America/Chicago';

    public function getId(): int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        $name = trim($this->first_name.' '.$this->last_name);
        if (!$name) {
            $name = $this->email;
        }

        if (!$name) {
            $name = (string) $this->id;
        }

        return $name;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getTimeZone(): string
    {
        return $this->time_zone;
    }

    public function setTimeZone(string $time_zone): void
    {
        $this->time_zone = $time_zone;
    }

    // ======================================================================
    // USER SECURITY FUNCTIONS
    // ======================================================================
    public function getUsername(): string
    {
        return $this->email;
    }

    public function getSalt(): ?string
    {
        // you *may* need a real salt depending on your encoder
        // see section on salt below
        return null;
    }

    public function getRoles(): array
    {
        if ('administrator' == $this->role) {
            return ['ROLE_SUPER_ADMIN'];
        } elseif ('cs' == $this->role) {
            return ['ROLE_CUSTOMER_SUPPORT'];
        } elseif ('marketing' == $this->role) {
            return ['ROLE_MARKETING'];
        } elseif ('sales' == $this->role) {
            return ['ROLE_SALES'];
        }

        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        return [
            $this->id,
            $this->email,
            $this->password,
            // see section on salt below
            // $this->salt,
        ];
    }

    public function __unserialize(array $serialized): void
    {
        [
            $this->id,
            $this->email,
            $this->password,
            // see section on salt below
            // $this->salt
        ] = $serialized;
    }
}
