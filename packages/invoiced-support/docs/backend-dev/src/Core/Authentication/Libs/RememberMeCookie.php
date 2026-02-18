<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Models\PersistentSession;
use App\Core\Authentication\Models\User;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\InfuseUtility as U;
use App\Core\Utils\RandomString;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

class RememberMeCookie
{
    private string $series;
    private string $token;

    public function __construct(
        private string $email,
        private string $userAgent,
        ?string $series = null,
        ?string $token = null
    ) {
        $this->series = $series ?? $this->generateToken();
        $this->token = $token ?? $this->generateToken();
    }

    /**
     * Gets the email address.
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Gets the user agent.
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Gets the series.
     */
    public function getSeries(): string
    {
        return $this->series;
    }

    /**
     * Gets the token.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Gets the expiration timestamp for the cookie.
     */
    public function getExpires(int $t = 0): int
    {
        return $t + PersistentSession::$sessionLength;
    }

    /**
     * Decodes an encoded remember me cookie string.
     */
    public static function decode(string $cookie): self
    {
        $params = (array) json_decode(base64_decode($cookie), true);

        return new self(
            (string) array_value($params, 'user_email'),
            (string) array_value($params, 'agent'),
            (string) array_value($params, 'series'),
            (string) array_value($params, 'token')
        );
    }

    /**
     * Encodes a remember me cookie.
     */
    public function encode(): string
    {
        $json = (string) json_encode([
            'user_email' => $this->email,
            'agent' => $this->userAgent,
            'series' => $this->series,
            'token' => $this->token,
        ]);

        return base64_encode($json);
    }

    /**
     * Checks if the cookie contains valid values.
     */
    public function isValid(): bool
    {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (empty($this->userAgent)) {
            return false;
        }

        if (empty($this->series)) {
            return false;
        }

        if (empty($this->token)) {
            return false;
        }

        return true;
    }

    /**
     * Looks for a remembered user using this cookie
     * from an incoming request.
     */
    public function verify(Request $request, Connection $database): ?User
    {
        if (!$this->isValid()) {
            return null;
        }

        // verify the user agent matches the one in the request
        if ($this->userAgent != $request->headers->get('User-Agent')) {
            return null;
        }

        // look up the user with a matching email address
        try {
            $user = User::where('email', $this->email)
                ->oneOrNull();
        } catch (ModelException) {
            // fail open when unable to look up the user
            return null;
        }

        if (!$user) {
            return null;
        }

        // hash series for matching with the db
        $seriesHash = $this->hash($this->series);

        // First, make sure all of the parameters match, except the token.
        // We match the token separately to detect if an older session is
        // being used, in which case we cowardly run away.
        $expiration = time() - $this->getExpires();
        $persistentSession = $database->createQueryBuilder()
            ->select('token,two_factor_verified')
            ->from('PersistentSessions')
            ->where('email = :email')
            ->setParameter('email', $this->email)
            ->where('created_at > "'.U::unixToDb($expiration).'"')
            ->where('series = :series')
            ->setParameter('series', $seriesHash)
            ->fetchAssociative();

        if (!$persistentSession) {
            return null;
        }

        // if there is a match, sign the user in
        $tokenHash = $this->hash($this->token);

        // Same series, but different token, meaning the user is trying
        // to use an older token. It's most likely an attack, so flush
        // all sessions.
        if (!hash_equals($persistentSession['token'], $tokenHash)) {
            $database->delete('PersistentSessions', ['email' => $this->email]);

            return null;
        }

        // remove the token once used
        $database->delete('PersistentSessions', [
            'email' => $this->email,
            'series' => $seriesHash,
            'token' => $tokenHash,
        ]);

        // mark the user as 2fa verified
        if ($persistentSession['two_factor_verified']) {
            $user->markTwoFactorVerified();
        }

        return $user;
    }

    /**
     * Persists this cookie to the database.
     *
     * @throws \Exception when the model cannot be saved
     */
    public function persist(User $user): PersistentSession
    {
        $session = new PersistentSession();
        $session->email = $this->email;
        $session->series = $this->hash($this->series);
        $session->token = $this->hash($this->token);
        $session->user_id = (int) $user->id();
        $session->two_factor_verified = $user->isTwoFactorVerified();

        try {
            $session->save();
        } catch (\Exception $e) {
            throw new \Exception("Unable to save persistent session for user # {$user->id()}: ".$e->getMessage());
        }

        return $session;
    }

    /**
     * Destroys the persisted cookie in the data store.
     */
    public function destroy(): void
    {
        $seriesHash = $this->hash($this->series);
        PersistentSession::where('email', $this->email)
            ->where('series', $seriesHash)
            ->delete();
    }

    /**
     * Generates a random token.
     */
    private function generateToken(int $len = 32): string
    {
        return RandomString::generate($len, RandomString::CHAR_ALNUM);
    }

    /**
     * Hashes a token.
     */
    private function hash(string $token): string
    {
        return hash_hmac('sha512', $token, (string) getenv('APP_SALT'));
    }
}
