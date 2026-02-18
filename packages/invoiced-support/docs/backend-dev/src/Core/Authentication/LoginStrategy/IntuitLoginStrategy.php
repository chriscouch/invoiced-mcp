<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\User;

class IntuitLoginStrategy extends AbstractOpenIdLoginStrategy
{
    private array $userInfo;

    protected function getUser(string $claimedId, array $attributes): ?User
    {
        // check if intuit user is already registered
        $user = User::where('intuit_claimed_id', $claimedId)->oneOrNull();
        if ($user) {
            return $user;
        }

        // we can only sign in / sign up the intuit user if the email address was verified by intuit
        $this->userInfo = $this->getUserInfo($this->lastToken);
        if (!$this->userInfo['emailVerified']) {
            throw new AuthException('Could not find a matching user account');
        }

        $user = User::where('email', $this->userInfo['email'])->oneOrNull();
        if ($user) {
            $user->intuit_claimed_id = $claimedId;
            $user->save();

            return $user;
        }

        return null;
    }

    protected function getNewUserParams(array $attributes): array
    {
        if (!isset($this->userInfo)) {
            $this->userInfo = $this->getUserInfo($this->lastToken);
        }

        return [
            'first_name' => $this->userInfo['givenName'],
            'last_name' => $this->userInfo['familyName'],
            'email' => $this->userInfo['email'],
            'intuit_claimed_id' => $attributes['sub'],
        ];
    }
}
