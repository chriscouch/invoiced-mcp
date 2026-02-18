<?php

namespace App\Core\Authentication\Storage;

use App\Core\Authentication\Interfaces\StorageInterface;
use App\Core\Authentication\Models\ActiveSession;
use App\Core\Authentication\Models\User;
use App\Core\Utils\InfuseUtility as Utility;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

class SessionStorage implements StorageInterface
{
    private const SESSION_USER_ID_KEY = 'user_id';
    private const SESSION_USER_AGENT_KEY = 'user_agent';
    private const SESSION_2FA_VERIFIED_KEY = '2fa_verified';
    private const SESSION_REMEMBERED_KEY = 'remembered';
    /**
     * These are session variables that are preserved
     * during the sign in process. Any variable not
     * in this list will not be preserved during sign in.
     */
    private const PRESERVE_SESSION_VARS = [
        'oauth_authorization_request',
        'redirect_after_login',
    ];

    public function __construct(private Connection $database)
    {
    }

    public function signIn(User $user, Request $request): void
    {
        $session = $request->getSession();

        // nothing to do if the user ID is already signed in
        $currentUserId = $session->get(self::SESSION_USER_ID_KEY);
        $userId = (int) $user->id();
        if ($currentUserId == $userId) {
            return;
        }

        $preservedAttributes = [];
        foreach (self::PRESERVE_SESSION_VARS as $k) {
            if ($session->has($k)) {
                $preservedAttributes[$k] = $session->get($k);
            }
        }

        // we are going to kill the current session and start a new one
        if ($session->isStarted()) {
            // remove the currently active session, for signed in users
            if ($currentUserId > 0 && $sid = $session->getId()) {
                // delete any active sessions for this session ID
                $this->deleteSession($sid);
            }

            // regenerate session id to prevent session hijacking
            $session->migrate(true);

            // hang on to the new session id
            $sessionId = $session->getId();

            // close the old and new sessions
            $session->save();

            // re-open the new session
            $session->setId($sessionId);
            $session->start();

            // record the active session, for signed in users
            if ($userId > 0) {
                // create an active session for this session ID
                $this->createSession($sessionId, $userId, $request);
            }
        }

        // set the user id
        $session->replace([
            self::SESSION_USER_ID_KEY => $userId,
            self::SESSION_USER_AGENT_KEY => $request->headers->get('User-Agent'),
        ]);

        // add back preserved attributes
        foreach ($preservedAttributes as $k => $v) {
            $session->set($k, $v);
        }

        // mark the user's session as 2fa verified if needed
        if ($user->isTwoFactorVerified()) {
            $this->markTwoFactorVerified($request);
        }
    }

    public function markRemembered(Request $request): void
    {
        // mark this session as remembered
        $request->getSession()->set(self::SESSION_REMEMBERED_KEY, true);
    }

    public function markTwoFactorVerified(Request $request): void
    {
        // mark the session as two factor verified
        $request->getSession()->set(self::SESSION_2FA_VERIFIED_KEY, true);
    }

    public function getAuthenticatedUser(Request $request): ?User
    {
        return $this->getUserSession($request);
    }

    public function signOut(Request $request): void
    {
        $session = $request->getSession();

        if ($session->isStarted()) {
            $sid = $session->getId();

            $session->invalidate();

            if ($sid) {
                // delete active sessions for this session ID
                $this->deleteSession($sid);
            }
        }
    }

    //
    // Private Methods
    //

    /**
     * Tries to get an authenticated user via the current session.
     */
    private function getUserSession(Request $request): ?User
    {
        // check for a session hijacking attempt via the stored user agent
        $session = $request->getSession();
        if ($session->get(self::SESSION_USER_AGENT_KEY) !== $request->headers->get('User-Agent')) {
            return null;
        }

        $userId = $session->get(self::SESSION_USER_ID_KEY);
        if (null === $userId) {
            return null;
        }

        // if this is a guest user then just return it now
        if ($userId <= 0) {
            return new User(['id' => $userId]);
        }

        // look up the registered user
        /** @var User|null $user */
        $user = User::where('id', $userId)->oneOrNull();
        if (!$user) {
            return null;
        }

        // check if the session valid
        $sid = $session->getId();
        if (!$this->sessionIsValid($sid)) {
            return null;
        }

        // refresh the active session
        $this->updateSessionStats($sid);

        // check if the user is 2FA verified
        if ($session->get(self::SESSION_2FA_VERIFIED_KEY)) {
            $user->markTwoFactorVerified();
        }

        return $user->setIsFullySignedIn();
    }

    /**
     * Creates an active session for a user.
     */
    private function createSession(string $sessionId, int $userId, Request $request): void
    {
        $sessionCookie = session_get_cookie_params();
        $expires = time() + $sessionCookie['lifetime'];

        $session = new ActiveSession();
        $session->id = $sessionId;
        $session->user_id = $userId;
        $session->ip = (string) $request->getClientIp();
        $session->user_agent = (string) $request->headers->get('User-Agent');
        $session->expires = $expires;
        $session->save();
    }

    /**
     * Checks if a session has been invalidated.
     */
    private function sessionIsValid(string $sid): bool
    {
        return 0 == ActiveSession::where('id', $sid)
            ->where('valid', false)
            ->count();
    }

    /**
     * Modifies the expiration on an active session.
     */
    private function updateSessionStats(string $sessionId): void
    {
        $sessionCookie = session_get_cookie_params();
        $expires = time() + $sessionCookie['lifetime'];

        $this->database->update('ActiveSessions', [
                'expires' => $expires,
                'updated_at' => Utility::unixToDb(time()),
            ], [
                'id' => $sessionId,
            ]);
    }

    /**
     * Deletes an active session.
     */
    private function deleteSession(string $sessionId): void
    {
        $this->database->delete('ActiveSessions', [
            'id' => $sessionId,
        ]);
    }
}
