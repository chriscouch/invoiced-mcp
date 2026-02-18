<?php

namespace App\Core\Authentication\TwoFactor;

use App\Core\Authentication\Exception\TwoFactorException;
use App\Core\Authentication\Interfaces\TwoFactorInterface;
use App\Core\Authentication\Models\User;
use Authy\AuthyApi;
use stdClass;

class AuthyVerification implements TwoFactorInterface
{
    public function __construct(private AuthyApi $authy)
    {
    }

    /**
     * Registers a user for 2FA on Authy.
     *
     * @throws TwoFactorException when the user cannot be registered
     */
    public function register(User $user, string $phone, int $countryCode): void
    {
        $authyUser = $this->authy->registerUser($user->email(), $phone, $countryCode);

        if (!$authyUser->ok()) {
            $msg = $this->stringifyReason($authyUser->errors()); /* @phpstan-ignore-line */

            throw new TwoFactorException("Could not enroll in two-factor authentication: $msg");
        }

        $user->authy_id = (string) $authyUser->id();
        $user->verified_2fa = false;
        if (!$user->save()) {
            throw new TwoFactorException('Could not enroll in two-factor authentication: '.$user->getErrors());
        }
    }

    /**
     * Deregisters a user for 2FA on Authy.
     *
     * @throws TwoFactorException when the user cannot be registered
     */
    public function deregister(User $user): void
    {
        // remove on Authy
        $result = $this->authy->deleteUser((string) $user->authy_id);

        if (!$result->ok()) {
            $msg = $result->errors()->message; /* @phpstan-ignore-line */

            throw new TwoFactorException("Could not send two-factor token over SMS: $msg");
        }

        // remove 2fa from the user's account
        $user->authy_id = null;
        $user->verified_2fa = false;
        if (!$user->save()) {
            throw new TwoFactorException('Could not remove two-factor authentication: '.$user->getErrors());
        }
    }

    /**
     * Request SMS verification (less secure than using Authy app).
     *
     * @throws TwoFactorException when the SMS cannot be sent
     */
    public function requestSMS(User $user): void
    {
        $result = $this->authy->requestSms((string) $user->authy_id, ['force' => true]);

        if (!$result->ok()) {
            $msg = $result->errors()->message; /* @phpstan-ignore-line */

            throw new TwoFactorException("Could not send two-factor token over SMS: $msg");
        }
    }

    public function verify(User $user, string $token): void
    {
        $verification = $this->authy->verifyToken((string) $user->authy_id, $token);

        if (!$verification->ok()) {
            $msg = $verification->errors()->message; /* @phpstan-ignore-line */

            throw new TwoFactorException("Could not verify two-factor token: $msg");
        }

        // mark user as 2fa verified
        $user->verified_2fa = true;
        $user->save();
    }

    private function stringifyReason(stdClass $errors): string
    {
        $str = [];
        foreach ((array) $errors as $field => $message) {
            $str[] = "$field $message";
        }

        return implode(', ', $str);
    }
}
