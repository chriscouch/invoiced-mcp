<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Event\ChangedPasswordEvent;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Models\UserLink;
use App\Core\Mailer\Mailer;
use App\Core\Orm\Exception\ModelException;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\AppUrl;
use App\Core\Utils\InfuseUtility as U;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ResetPassword implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private Connection $database,
        private Mailer $mailer,
        private EventDispatcherInterface $eventDispatcher,
        private RateLimiterFactory $resetPasswordLimiter,
    ) {
    }

    /**
     * Looks up a user from a given forgot token.
     *
     * @throws AuthException when the token is invalid
     */
    public function getUserFromToken(string $token): User
    {
        $expiration = U::unixToDb(time() - UserLink::$forgotLinkTimeframe);
        $link = UserLink::where('link', $token)
            ->where('type', UserLink::FORGOT_PASSWORD)
            ->where('created_at', $expiration, '>')
            ->oneOrNull();

        if (!$link) {
            throw new AuthException('This link has expired or is invalid.');
        }

        return User::findOrFail($link->user_id);
    }

    /**
     * The first step in the forgot password sequence.
     *
     * @param string $email     email address
     * @param string $ip        ip address making the request
     * @param string $userAgent user agent used to make the request
     *
     * @throws AuthException when the step cannot be completed
     */
    public function step1(string $email, string $ip, string $userAgent): void
    {
        // Check for a rate limit violation for the ip address
        $limiter = $this->resetPasswordLimiter->create($ip);
        if (!$limiter->consume()->isAccepted()) {
            $this->statsd->increment('security.rate_limit_reset_password');

            throw new AuthException('Too many reset password requests. Please try again later.');
        }

        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Please enter a valid email address.');
        }

        /** @var User|null $user */
        $user = User::where('email', $email)->oneOrNull();

        if (!$user || $user->isTemporary()) {
            $this->statsd->increment('security.failed_reset_password_request');

            throw new AuthException('We could not find a match for that email address.');
        }

        // can only issue a single active forgot token at a time
        $expiration = U::unixToDb(time() - UserLink::$forgotLinkTimeframe);
        $nExisting = UserLink::where('user_id', $user->id())
            ->where('type', UserLink::FORGOT_PASSWORD)
            ->where('created_at', $expiration, '>')
            ->count();

        if ($nExisting > 0) {
            return;
        }

        // generate a reset password link
        $link = $this->buildLink((int) $user->id(), $ip, $userAgent);

        // and send it
        $this->mailer->sendToUser($user, [
            'subject' => 'Reset Password',
        ], 'forgot-password', [
            'username' => $user->name(true),
            'forgot_link' => AppUrl::get()->build()."/users/forgot/{$link->link}",
            'start_forgot_url' => AppUrl::get()->build().'/forgot',
        ]);

        $this->statsd->increment('security.reset_password_request');
    }

    /**
     * Step 2 in the forgot password process. Resets the password
     * given a valid token.
     *
     * @param string $token    token
     * @param array  $password new password
     *
     * @throws AuthException when the step cannot be completed
     */
    public function step2(string $token, array $password, Request $request): void
    {
        $user = $this->getUserFromToken($token);

        // Update the password
        $user->password = $password; /* @phpstan-ignore-line */
        $success = $user->save();

        if ($success) {
            // Emit a changed password event on success
            $event = new ChangedPasswordEvent($user, $request);
            $this->eventDispatcher->dispatch($event);
        } else {
            $msg = (string) $user->getErrors();

            throw new AuthException($msg);
        }

        $this->database->delete('UserLinks', [
            'user_id' => $user->id(),
            'type' => UserLink::FORGOT_PASSWORD,
        ]);
    }

    /**
     * Builds a reset password link.
     *
     * @throws AuthException
     */
    public function buildLink(int $userId, string $ip, string $userAgent): UserLink
    {
        try {
            $link = new UserLink();
            $link->user_id = $userId;
            $link->type = UserLink::FORGOT_PASSWORD;
            $link->saveOrFail();

            // record the reset password request event
            $event = new AccountSecurityEvent();
            $event->user_id = $userId;
            $event->type = AccountSecurityEvent::RESET_PASSWORD_REQUEST;
            $event->ip = $ip;
            $event->user_agent = $userAgent;
            $event->saveOrFail();

            return $link;
        } catch (ModelException) {
            throw new AuthException('Could not create user link');
        }
    }
}
