<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Models\User;

abstract class AbstractLoginStrategy
{
    public function __construct(
        protected LoginHelper $loginHelper
    ) {
    }

    /**
     * Gets the ID of this authentication strategy.
     */
    abstract public function getId(): string;

    /**
     * Perform basic user Authentication validation.
     *
     * @throws AuthException
     */
    protected function validateUser(User $user): void
    {
        if ($user->isTemporary()) {
            throw new AuthException('It looks like your account has not been setup yet. Please go to the sign up page to finish creating your account.');
        }

        if (!$user->isEnabled()) {
            throw new AuthException('Sorry, your account has been disabled.');
        }

        if (!$user->isVerified()) {
            throw new AuthException('You must verify your account with the email that was sent to you before you can log in.');
        }
    }
}
