<?php

namespace App\Integrations\OAuth\Traits;

use App\Integrations\Exceptions\OAuthException;
use stdClass;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

trait OAuthIntegrationTrait
{
    public function getAuthorizationUrl(string $state): string
    {
        return $this->settings['authorizationUrl'].'?'.http_build_query([
                'client_id' => $this->getClientId(),
                'scope' => $this->getScope(),
                'redirect_uri' => $this->getRedirectURL(),
                'response_type' => 'code',
                'state' => $state,
            ]);
    }

    public function getScope(): string
    {
        return $this->settings['scope'];
    }

    public function getSuccessRedirectUrl(): string
    {
        return $this->settings['successUrl'];
    }

    /**
     * @throws OAuthException
     */
    protected function createToken(string $grantType, string $code): stdClass
    {
        $parameters = [
            'grant_type' => $grantType,
        ];
        if ('authorization_code' == $grantType) {
            $parameters['code'] = $code;
            $parameters['redirect_uri'] = $this->getRedirectUrl();
        } elseif ('refresh_token' == $grantType) {
            $parameters['refresh_token'] = $code;
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                $this->getTokenUrl(),
                $this->getHttpRequestOptions($parameters)
            );

            return json_decode($response->getContent());
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $body = $response->getContent(false);
            $result = json_decode($body);
            $msg = $body;
            if ($result && isset($result->error_description)) {
                $msg = $result->error_description;
            } elseif ($result && isset($result->error)) {
                $msg = $result->error;
            }

            throw new OAuthException('Could not create access token: '.$msg, $response->getStatusCode(), $e);
        } catch (TransportExceptionInterface $e) {
            throw new OAuthException('Could not create access token due to a connection error', $e->getCode(), $e);
        }
    }

    protected function getHttpRequestOptions(array $parameters): array
    {
        $options = [
            'body' => $parameters,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Invoiced/1.0',
            ],
        ];

        $authMode = $this->settings['authMode'] ?? 'basic';
        if ('body' == $authMode) {
            $options['body']['client_id'] = $this->getClientId();
            $options['body']['client_secret'] = $this->getClientSecret();
        } else {
            $options['auth_basic'] = [$this->getClientId(), $this->getClientSecret()];
        }

        return $options;
    }

    protected function getClientId(): string
    {
        return $this->settings['clientId'];
    }

    protected function getClientSecret(): string
    {
        return $this->settings['clientSecret'];
    }

    protected function getTokenUrl(): string
    {
        return $this->settings['tokenUrl'];
    }

    protected function getRevokeUrl(): string
    {
        return $this->settings['revokeUrl'];
    }

    /**
     * Used for testing.
     */
    public function setHttpClient(HttpClientInterface $httpClient): void
    {
        $this->httpClient = $httpClient;
    }
}
