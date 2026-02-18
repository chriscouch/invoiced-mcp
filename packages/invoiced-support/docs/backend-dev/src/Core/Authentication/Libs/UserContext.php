<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\User;
use Symfony\Component\HttpFoundation\RequestStack;

class UserContext
{
    private ?User $currentUser = null;
    private bool $checkedForUser = false;

    public function __construct(
        private RequestStack $requestStack,
        private LoginHelper $loginHelper,
    ) {
    }

    /**
     * Sets the current user.
     */
    public function set(User $user): void
    {
        $this->currentUser = $user;
        $this->checkedForUser = true;
    }

    public function clear(): void
    {
        $this->currentUser = null;
        $this->checkedForUser = true;
    }

    /**
     * Gets the current user.
     *
     * @throws AuthException
     */
    public function get(): ?User
    {
        if ($this->checkedForUser) {
            return $this->currentUser;
        }

        // If there is not a user context yet then we load it from
        // login helper which checks places like the session, remember me cookies, etc
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new AuthException('The current user cannot be retrieved outside of the request context unless it was already set');
        }

        $this->currentUser = $this->loginHelper->getAuthenticatedUser($request);
        $this->checkedForUser = true;

        return $this->currentUser;
    }

    /**
     * Gets the current user and generates an exception if there is not one.
     *
     * @throws AuthException
     */
    public function getOrFail(): User
    {
        $user = $this->get();
        if (!$user) {
            throw new AuthException('No current user has been set in user context');
        }

        return $user;
    }

    /**
     * Checks if there is a current user.
     */
    public function has(): bool
    {
        return null !== $this->currentUser;
    }
}
