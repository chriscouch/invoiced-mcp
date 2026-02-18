<?php

namespace App\Core\Authentication\Storage;

use App\Core\Authentication\Interfaces\StorageInterface;
use App\Core\Authentication\Models\User;
use Symfony\Component\HttpFoundation\Request;

class InMemoryStorage implements StorageInterface
{
    private ?User $user = null;

    public function signIn(User $user, Request $request): void
    {
        $this->user = $user;
    }

    public function markRemembered(Request $request): void
    {
    }

    public function markTwoFactorVerified(Request $request): void
    {
    }

    public function getAuthenticatedUser(Request $request): ?User
    {
        return $this->user;
    }

    public function signOut(Request $request): void
    {
        $this->user = null;
    }
}
