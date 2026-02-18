<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Saml\SamlResponseSimplified;
use OneLogin\Saml2\Error;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SamlV1LoginStrategy extends AbstractSamlLoginStrategy
{
    public function getId(): string
    {
        return 'saml';
    }

    /**
     * @throws AuthException
     */
    public function doSignIn(Request $request, User $user, SamlResponseSimplified $response): void
    {
        $domain = $this->getDomainFromEmail($user->email);

        $samlSettings = CompanySamlSettings::where('domain', $domain)->oneOrNull();
        if (!$samlSettings instanceof CompanySamlSettings) {
            throw new AuthException('Single sign-on is not configured');
        }

        if (!$samlSettings->enabled) {
            throw new AuthException('Single sign-on is disabled');
        }

        $this->parseResponse($samlSettings);

        parent::doSignIn($request, $user, $response);
    }

    /**
     * Handles a user authentication request.
     *
     * @throws AuthException when unable to authenticate the user
     */
    public function authenticate(Request $request): Response
    {
        if ($email = (string) $request->request->get('email')) {
            $domain = $this->getDomainFromEmail($email);
        } else {
            $domain = $request->attributes->get('domain');
        }

        $samlSettings = CompanySamlSettings::where('domain', $domain)
            ->where('enabled', 1)
            ->oneOrNull();
        if (!$samlSettings instanceof CompanySamlSettings) {
            throw new AuthException('Single sign-on is not configured');
        }

        try {
            $this->authFactory->get($samlSettings)->login();
        } catch (Error $e) {
            $this->logger->error('Could not login with SAML', ['exception' => $e]);

            throw new AuthException('Unable to sign in');
        }

        return new Response();
    }

    public function getDomainFromEmail(string $email): string
    {
        $parts = explode('@', strtolower($email));

        return trim(end($parts));
    }
}
