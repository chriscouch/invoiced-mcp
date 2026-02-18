<?php

namespace App\Core\Authentication\Interfaces;

use App\Core\Authentication\Models\User;
use Symfony\Component\HttpFoundation\Request;

interface StorageInterface
{
    /**
     * Starts a new user session.
     */
    public function signIn(User $user, Request $request): void;

    /**
     * Marks the user's session as authenticated from a remember me cookie.
     */
    public function markRemembered(Request $request): void;

    /**
     * Marks the user's session as two factor verified.
     */
    public function markTwoFactorVerified(Request $request): void;

    /**
     * Gets the authenticated user for the current session. If the
     * user was previously marked as two-factor verified for the
     * current session then the returned user must have this designation.
     */
    public function getAuthenticatedUser(Request $request): ?User;

    /**
     * Signs out the current user session.
     */
    public function signOut(Request $request): void;
}
