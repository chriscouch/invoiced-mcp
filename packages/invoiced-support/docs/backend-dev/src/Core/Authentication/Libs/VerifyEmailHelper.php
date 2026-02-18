<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Models\User;
use App\Core\Authentication\Models\UserLink;
use App\Core\Mailer\Mailer;
use App\Core\Utils\AppUrl;
use Doctrine\DBAL\Connection;

class VerifyEmailHelper
{
    public function __construct(
        private Connection $database,
        private Mailer $mailer,
    ) {
    }

    /**
     * Sends a verification email to a user.
     */
    public function sendVerificationEmail(User $user): void
    {
        // delete previous verify links
        $this->database->delete('UserLinks', [
            'user_id' => $user->id(),
            'type' => UserLink::VERIFY_EMAIL,
        ]);

        // create new verification link
        $link = new UserLink();
        $link->create([
            'user_id' => $user->id(),
            'type' => UserLink::VERIFY_EMAIL,
        ]);

        // email it
        $this->mailer->sendToUser(
            $user,
            [
                'subject' => 'Please verify your email address',
            ],
            'verify-email',
            [
                'verify_link' => AppUrl::get()->build()."/users/verifyEmail/{$link->link}",
            ],
        );
    }

    /**
     * Processes a verify email token.
     */
    public function verifyEmailWithToken(string $token, bool $sendWelcomeEmail): ?User
    {
        $link = UserLink::where('link', $token)
            ->where('type', UserLink::VERIFY_EMAIL)
            ->oneOrNull();

        if (!$link) {
            return null;
        }

        $user = User::findOrFail($link->user_id);

        // enable the user and delete the verify link
        $user->enable();
        $link->delete();

        // send a welcome email
        if ($sendWelcomeEmail) {
            $this->mailer->sendToUser($user, [
                'subject' => 'Welcome to Invoiced',
            ], 'welcome');
        }

        return $user;
    }
}
