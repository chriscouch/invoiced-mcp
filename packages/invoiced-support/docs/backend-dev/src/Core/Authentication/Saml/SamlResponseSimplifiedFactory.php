<?php

namespace App\Core\Authentication\Saml;

use OneLogin\Saml2\ValidationError;
use Symfony\Component\HttpFoundation\RequestStack;

class SamlResponseSimplifiedFactory
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * @throws ValidationError
     */
    public function getInstance(): SamlResponseSimplified
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new ValidationError('No Request specified');
        }
        $response = (string) $request->request->get('SAMLResponse');
        $response = base64_decode($response);

        return new SamlResponseSimplified($response);
    }
}
