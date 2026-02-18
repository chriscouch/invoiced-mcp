<?php

namespace App\Companies\Api;

use App\Companies\Models\Company;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SetupCustomDomainRoute extends AbstractModelApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private HttpClientInterface $httpClient,
        private string $customDomainUrl,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'domain' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
            ],
            requiredPermissions: ['settings.edit'],
            features: ['custom_domain'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $company = $this->tenant->get();
        $domain = $context->requestParameters['domain'];
        if ($domain == $company->custom_domain) {
            return new Response('', 204);
        }

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN) || !str_contains($domain, '.')) {
            throw new InvalidRequest('Domain name is invalid');
        }

        $n = Company::where('custom_domain', $domain)->count();
        if ($n > 0) {
            throw new InvalidRequest('Domain is already taken: '.$domain);
        }

        // provision the SSL certificate on the custom domain proxy
        $endpoint = 'http://'.$this->customDomainUrl.':2424/add_domain.php';

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'body' => [
                    'domain' => $domain,
                ],
            ]);

            $result = json_decode($response->getContent());
        } catch (ExceptionInterface $e) {
            $this->logger->error('Provisioning custom domain failed', ['exception' => $e]);

            throw new InvalidRequest('An error occurred when provisioning the custom domain.');
        }

        if (isset($result->error)) {
            throw new InvalidRequest('Adding custom domain failed: '.$result->error);
        }

        // update the company
        $company->custom_domain = $domain;
        if ($company->save()) {
            return new Response('', 204);
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a generic one
        throw new ApiError('There was an error saving the company.');
    }
}
