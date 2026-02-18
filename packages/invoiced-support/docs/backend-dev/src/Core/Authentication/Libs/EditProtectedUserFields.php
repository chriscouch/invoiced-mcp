<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Event\ChangedPasswordEvent;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\LoginStrategy\UsernamePasswordLoginStrategy;
use App\Core\Authentication\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EditProtectedUserFields
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Modifies one or more "protected" fields on the user
     * that first require verifying the current password.
     * For example, a user cannot change their password
     * without confirming their current password.
     *
     * @throws AuthException
     */
    public function change(User $user, Request $request, string $currentPassword, array $parameters): void
    {
        // verify the user supplied the correct current password
        if (!$currentPassword || !UsernamePasswordLoginStrategy::verifyPassword($user, $currentPassword)) {
            throw new AuthException('The given password is not correct.');
        }

        foreach ($parameters as $property => $value) {
            if ($value) {
                $user->$property = $value;
            }
        }

        if (!$user->save()) {
            throw new AuthException('We were unable to update your account: '.$user->getErrors());
        }

        if (isset($parameters['password'])) {
            $event = new ChangedPasswordEvent($user, $request);
            $this->eventDispatcher->dispatch($event);
        }
    }
}
