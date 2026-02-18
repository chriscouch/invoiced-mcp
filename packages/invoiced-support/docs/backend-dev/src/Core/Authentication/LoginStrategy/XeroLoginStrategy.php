<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Core\Authentication\Models\User;

class XeroLoginStrategy extends AbstractOpenIdLoginStrategy
{
    protected function getUser(string $claimedId, array $attributes): ?User
    {
        // check if xero user is already registered
        $user = User::where('xero_claimed_id', $claimedId)->oneOrNull();
        if ($user) {
            return $user;
        }

        // we can then attempt to connect the xero user to an existing account
        // by matching on email address
        $user = User::where('email', $attributes['email'])->oneOrNull();
        if ($user) {
            $user->xero_claimed_id = $claimedId;
            $user->save();

            return $user;
        }

        return null;
    }

    protected function getNewUserParams(array $attributes): array
    {
        $userInfo = $this->getUserInfo($this->lastToken);

        return [
            'first_name' => $userInfo['given_name'],
            'last_name' => $userInfo['family_name'],
            'email' => $userInfo['email'],
            'xero_claimed_id' => $attributes['sub'],
        ];
    }
}
