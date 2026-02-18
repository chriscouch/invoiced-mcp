<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\User;

class GoogleLoginStrategy extends AbstractOpenIdLoginStrategy
{
    public function getAuthorizationUrl(string $state): string
    {
        return parent::getAuthorizationUrl($state).'&access_type=online&approval_prompt=auto';
    }

    protected function getUser(string $claimedId, array $attributes): ?User
    {
        // check if google user is already registered
        $user = User::where('google_claimed_id', $claimedId)->oneOrNull();
        if ($user) {
            return $user;
        }

        // we can only sign in / sign up the google user if the email address was verified by google
        if (!$attributes['email_verified']) {
            throw new AuthException('Could not find a matching user account');
        }

        $user = User::where('email', $attributes['email'])->oneOrNull();
        if ($user) {
            $user->google_claimed_id = $claimedId;
            $user->save();

            return $user;
        }

        return null;
    }

    protected function getNewUserParams(array $attributes): array
    {
        $userInfo = $this->getUserInfo($this->lastToken);

        return [
            'first_name' => $userInfo['given_name'] ?? '',
            'last_name' => $userInfo['family_name'] ?? '',
            'email' => $userInfo['email'],
            'google_claimed_id' => $attributes['sub'],
        ];
    }
}
