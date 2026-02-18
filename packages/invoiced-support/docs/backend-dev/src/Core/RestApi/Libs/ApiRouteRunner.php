<?php

namespace App\Core\RestApi\Libs;

use App\Companies\Models\Member;
use App\Core\RestApi\Exception\ApiHttpException;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Interfaces\ApiRouteInterface;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\Serializer\Serializer;
use App\Core\RestApi\ValueObjects\AbstractParameter;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use App\Integrations\Libs\CloudWatchHandler;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ApiRouteRunner implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private Serializer $serializer,
        private TenantContext $tenant,
        private string $apiUrlBase,
        private string $environment,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
    ) {
    }

    /**
     * Runs the API route handler and generates a response.
     */
    public function run(ApiRouteInterface $route, Request $request): Response|StreamedResponse
    {
        $definition = $route->getDefinition();

        // Validate the request and create the context
        $this->checkFeatures($definition);
        $this->checkPermissions($definition);
        $context = $this->validateRequest($request, $definition);

        // Set the model class from the definition if this is a model API route
        if ($definition->modelClass && $route instanceof AbstractModelApiRoute) {
            $route->setModelClass($definition->modelClass);
        }

        // Set the API base URL on list model API routes (they use this for pagination links)
        if ($route instanceof AbstractListModelsApiRoute) {
            $route->setApiUrlBase($this->apiUrlBase);
        }

        // Execute the API route
        $result = $route->buildResponse($context);

        // Serialize a successful result into a response.
        if ($result instanceof Response) {
            return $result;
        }

        return $this->serializer->serialize($result, $route->getSuccessfulResponse());
    }

    /**
     * Checks whether the required permissions of this
     * API route are satisfied by the requester.
     *
     * @throws InvalidRequest
     */
    public function checkPermissions(ApiRouteDefinition $definition): void
    {
        $member = ACLModelRequester::get();
        if (!$member instanceof Member) {
            if ($definition->requiresMember) {
                throw $this->permissionError();
            }

            return;
        }

        foreach ($definition->requiredPermissions as $permission) {
            if (!$member->allowed($permission)) {
                throw $this->permissionError();
            }
        }
    }

    /**
     * Checks whether the required features of this
     * API route are satisfied by the tenant.
     *
     * @throws InvalidRequest
     */
    public function checkFeatures(ApiRouteDefinition $definition): void
    {
        foreach ($definition->features as $feature) {
            if (!$this->tenant->get()->features->has($feature)) {
                throw new InvalidRequest('Your Invoiced account does not have access to this feature');
            }
        }
    }

    /**
     * Performs validation on the data in the request and extracts
     * query and request parameters.
     *
     * @throws ApiHttpException when the request cannot be validated
     */
    public function validateRequest(Request $request, ApiRouteDefinition $definition): ApiCallContext
    {
        // Validate the query parameters.
        $queryParameters = $this->validateInput(
            $this->getQueryOptionsResolver($definition),
            $request->query->all(),
            'query',
            $definition->warn,
            $request
        );

        // Validate the request body.
        $requestParameters = $this->validateInput(
            $this->getRequestOptionsResolver($definition),
            $request->request->all(),
            'request',
            $definition->warn,
            $request
        );

        return new ApiCallContext($request, $queryParameters, $requestParameters, $definition);
    }

    public function getQueryOptionsResolver(ApiRouteDefinition $definition): ?OptionsResolver
    {
        $parameters = $definition->queryParameters;
        if (null === $parameters) {
            return null;
        }

        $resolver = new OptionsResolver();
        foreach ($parameters as $name => $parameter) {
            $this->applyParameter($resolver, $name, $parameter);
        }

        return $resolver;
    }

    private function applyParameter(OptionsResolver $resolver, string $name, AbstractParameter $parameter): void
    {
        if ($parameter->required) {
            $resolver->setRequired($name);
        } else {
            $resolver->setDefined($name);
        }

        if (null !== $parameter->types) {
            $resolver->setAllowedTypes($name, $parameter->types);
        }

        if (null !== $parameter->allowedValues) {
            $resolver->setAllowedValues($name, $parameter->allowedValues);
        }

        if ($parameter->hasDefaultValue) {
            $resolver->setDefault($name, $parameter->defaultValue);
        }
    }

    public function getRequestOptionsResolver(ApiRouteDefinition $definition): ?OptionsResolver
    {
        $parameters = $definition->requestParameters;
        if (null === $parameters) {
            return null;
        }

        $resolver = new OptionsResolver();
        foreach ($parameters as $name => $parameter) {
            $this->applyParameter($resolver, $name, $parameter);
        }

        return $resolver;
    }

    /**
     * @throws InvalidRequest
     */
    private function validateInput(?OptionsResolver $resolver, array $input, string $param, bool $warn, Request $request): array
    {
        if (!$resolver) {
            return $input;
        }

        try {
            return $resolver->resolve($input);
        } catch (ExceptionInterface $e) {
            // Replace double quotation marks with single for more clean output.
            $message = str_replace('"', "'", $e->getMessage());

            if ($warn) {
                $this->logValidationFailure($request, $message, $param);

                return $input;
            }
            throw new InvalidRequest($message, 400, $param);
        }
    }

    private function permissionError(): InvalidRequest
    {
        return new InvalidRequest('You do not have permission to do that', 403);
    }

    private function logValidationFailure(Request $request, string $message, string $param): void
    {
        $endpoint = str_replace('api_', '', (string) $request->attributes->get('_route'));
        $this->statsd->increment('api.validation_failed', 1.0, [
            'endpoint' => $endpoint,
            'param' => $param,
        ]);

        $logger = new Logger('api_validation_errors');
        if (!in_array($this->environment, ['dev', 'test'])) {
            $stream = (string) gethostname();
            $handler = new CloudWatchHandler($this->cloudWatchLogsClient, '/invoiced/ApiValidationErrors', $stream, 0, 10000, [], Logger::DEBUG, true, false);
            $logger->pushHandler($handler);
        } else {
            $logfile = dirname(dirname(dirname(dirname(__DIR__)))).'/var/log/api-validation-errors.log';
            $logger->pushHandler(new StreamHandler($logfile, Logger::DEBUG));
        }

        $logger->info($message, [
            'endpoint' => $endpoint,
            'param' => $param,
            'tenant_id' => $this->tenant->get()->id,
            'request_id' => $this->debugContext->getRequestId(),
            'correlation_id' => $this->debugContext->getCorrelationId(),
            'query' => $request->query->all(),
            'request' => $request->request->all(),
        ]);
    }
}
