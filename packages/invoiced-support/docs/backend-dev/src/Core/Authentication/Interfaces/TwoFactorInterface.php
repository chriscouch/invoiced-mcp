<?php

namespace App\Core\Authentication\Interfaces;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\User;

interface TwoFactorInterface
{
    /**
     * Verifies a user's 2FA token.
     *
     * @throws AuthException when the token cannot be verified
     */
    public function verify(User $user, string $token): void;
}
