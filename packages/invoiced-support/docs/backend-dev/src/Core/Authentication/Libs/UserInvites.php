<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\User;
use App\Core\Mailer\Mailer;

/**
 * Creates invitations for new users.
 */
class UserInvites
{
    public function __construct(
        private UserRegistration $userRegistration,
        private Mailer $mailer,
    ) {
    }

    /**
     * Invites a user by email address. If the user does
     * not exist then a temporary one will be created. If
     * there is an existing user then it will be returned.
     *
     * @throws AuthException when the invite or user cannot be created
     */
    public function invite(string $email, array $parameters = []): User
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Please enter a valid email address.');
        }

        // check for existing user account
        $user = User::where('email', $email)->oneOrNull();

        // register a new temporary account
        if (!$user) {
            $parameters['email'] = $email;
            $user = $this->userRegistration->createTemporaryUser($parameters);
        }

        $signUpLink = null;
        if ($link = $user->getTemporaryLink()) {
            $signUpLink = $link->url();
        }

        $this->mailer->sendToUser($user, [
            'subject' => 'You have been invited to join Invoiced',
        ], 'invite', [
            'sign_up_link' => $signUpLink,
        ]);

        return $user;
    }
}
