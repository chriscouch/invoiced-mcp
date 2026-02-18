<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Core\Authentication\Models\User;

class MicrosoftLoginStrategy extends AbstractOpenIdLoginStrategy
{
    protected function getUser(string $claimedId, array $attributes): ?User
    {
        // Microsoft does not often provide an email address claim
        // for an OpenID Connect user. Furthermore, Microsoft
        // says not to rely upon the email claim when it is provided.
        // The only reliable way we can identify a user signing in
        // with Microsoft is with the claimed ID.
        return User::where('microsoft_claimed_id', $claimedId)->oneOrNull();
    }

    protected function getNewUserParams(array $attributes): array
    {
        $userInfo = $this->getUserInfo($this->lastToken);

        // Microsoft does not always provide an email address.
        // If we cannot find an email address then we generate one.
        $email = $userInfo['email'] ?? null;
        if (!$email && isset($attributes['preferred_username']) && str_contains('@', $attributes['preferred_username'])) {
            $email = $attributes['preferred_username'];
        } elseif (!$email) {
            $email = $attributes['sub'].'@login.microsoft.com';
        }

        return [
            'first_name' => $userInfo['given_name'],
            'last_name' => $userInfo['family_name'],
            'email' => $email,
            'microsoft_claimed_id' => $attributes['sub'],
        ];
    }
}
