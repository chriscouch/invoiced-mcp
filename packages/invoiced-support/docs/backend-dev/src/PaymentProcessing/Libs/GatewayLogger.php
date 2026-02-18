<?php

namespace App\PaymentProcessing\Libs;

use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Exception\CredentialsException;
use DateTime;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyResponse;
use SimpleXMLElement;
use SoapClient;

/**
 * The gateway logger tracks the raw response bodies
 * received from the currently active payment gateway.
 */
class GatewayLogger implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private ?string $gateway = null;
    private ?string $lastRequest = null;
    private ?string $lastResponse = null;
    private ?float $lastResponseTiming = null;



    const TABLENAME = 'CustomerPortalLogs';

    const MAX_STRING_SIZE = 102400; // 100KB, max DynamoDB item size is 400KB

    public function __construct(
        private readonly DynamoDbClient $dynamodb,
        private readonly TenantContext $tenantContext,
        private readonly string $environment
    )
    {
    }

    /**
     * Sets the ID of the current gateway.
     *
     * @return $this
     */
    public function setCurrentGateway(string $id): self
    {
        $this->gateway = $id;

        return $this;
    }

    /**
     * Gets the ID of the current gateway.
     */
    public function getCurrentGateway(): ?string
    {
        return $this->gateway;
    }

    // ////////////////////////////////
    // / Request Logging
    // ////////////////////////////////

    /**
     * Sets the body of the last gateway request.
     * This function DOES NOT scrub sensitive information.
     *
     * @return $this
     */
    public function setLastRequest(?string $body): self
    {
        $this->lastRequest = $body;

        return $this;
    }

    /**
     * Adds a gateway request to the log.
     * This function DOES NOT scrub sensitive information.
     *
     * @return $this
     */
    public function addRequest(?string $body): self
    {
        $this->lastRequest = trim($this->lastRequest."\n\n".$body);

        return $this;
    }

    /**
     * Gets the body of the last gateway request.
     */
    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }

    /**
     * Logs a Form-Data API request in a PCI compliant
     * manner by scrubbing sensitive data first.
     *
     * @param string[] $maskedParameters
     */
    public function logFormDataRequest(array $request, array $maskedParameters): void
    {
        $requestStr = http_build_query($this->maskArray($request, $maskedParameters));
        $this->addRequest($requestStr);
    }

    /**
     * Logs a JSON API request in a PCI compliant
     * manner by scrubbing sensitive data first.
     *
     * @param string[] $maskedParameters
     */
    public function logJsonRequest(array $request, array $maskedParameters): void
    {
        $requestJson = (string) json_encode($this->maskArray($request, $maskedParameters));
        $this->addRequest($requestJson);
    }

    /**
     * Logs a SOAP API request in a PCI compliant
     * manner by scrubbing sensitive data first.
     *
     * @param string[] $maskRegexes
     */
    public function logSoapRequest(SoapClient $client, array $maskRegexes): void
    {
        $requestStr = $client->__getLastRequest();
        $this->logStringRequest((string) $requestStr, $maskRegexes);
    }

    public function logSymfonyHttpRequest(string $method, string $url, array $options, array $maskedParameters): void
    {
        $requestStr = "$method $url\n";
        $requestStr .= json_encode($this->maskArray($options, $maskedParameters));
        $this->addRequest($requestStr);
    }

    /**
     * Logs a string request body in a PCI compliant
     * manner by scrubbing sensitive data first.
     *
     * @param string[] $maskRegexes
     */
    public function logStringRequest(string $request, array $maskRegexes): void
    {
        foreach ($maskRegexes as $regex) {
            if (preg_match($regex, $request, $matches)) {
                $request = $this->mask((string) $matches[1], $request);
            }
        }

        $this->addRequest($request);
    }

    /**
     * Logs an XML request in a PCI compliant
     * manner by scrubbing sensitive data first.
     *
     * @param string[] $maskRegexes
     */
    public function logXmlRequest(SimpleXMLElement $request, array $maskRegexes): void
    {
        $requestXml = (string) $request->asXML();
        $this->logStringRequest($requestXml, $maskRegexes);
    }

    /**
     * Masks a sensitive value by replacing with x's.
     */
    private function mask(string $sensitiveValue, string $subject): string
    {
        return str_replace($sensitiveValue, 'x{' . strlen($sensitiveValue). '}', $subject);
    }

    /**
     * Recursively masks an array of values.
     */
    private function maskArray(array $request, array $maskedParameters): array
    {
        // recursively check each element in the request
        foreach ($request as $key => &$value) {
            if (is_array($value)) {
                $value = $this->maskArray($value, $maskedParameters);
            } elseif (in_array($key, $maskedParameters) && null !== $value) {
                $value = 'x{' . strlen($value). '}';
            }
        }

        return $request;
    }

    // ////////////////////////////////
    // / Response Logging
    // ////////////////////////////////

    /**
     * Sets the body of the last gateway response.
     */
    public function setLastResponse(?string $body): void
    {
        $this->lastResponse = $body;
    }

    /**
     * Adds the body of the last gateway response.
     */
    public function addResponse(?string $body): void
    {
        $this->lastResponse = trim("{$this->lastResponse}\n\n$body");
    }

    /**
     * Gets the body of the last gateway response.
     */
    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    /**
     * Logs the body of an HTTP response.
     */
    public function logSymfonyHttpResponse(SymfonyResponse $response): void
    {
        $this->logStringResponse($response->getContent(false));
    }

    /**
     * Logs the body of an HTTP response.
     */
    public function logHttpResponse(ResponseInterface $response): void
    {
        $this->logStringResponse((string) $response->getBody());
    }

    /**
     * Logs a JSON response.
     */
    public function logJsonResponse(object $result): void
    {
        $this->logStringResponse((string) json_encode($result));
    }

    /**
     * Logs a SOAP response.
     */
    public function logSoapResponse(SoapClient $client): void
    {
        $this->logStringResponse((string) $client->__getLastResponse());
    }

    /**
     * Logs a string response.
     */
    public function logStringResponse(string $response): void
    {
        $this->addResponse($response);
    }

    /**
     * Sets how long the last response from the gateway took.
     *
     * @param float $time in microseconds
     */
    public function setLastResponseTiming(float $time): void
    {
        $this->lastResponseTiming = $time;

        if ($id = $this->gateway) {
            $this->statsd->timing('payments.gateway_response_time', round($time * 1000), 1.0, ['gateway' => $id]);
        }
    }

    /**
     * Gets how long the last response from the gateway took.
     */
    public function getLastResponseTiming(): ?float
    {
        return $this->lastResponseTiming;
    }

    public function flush(Request $request): void
    {
        if (!$this->getLastRequest() && !$this->getLastResponse()) {
            return;
        }

        $marshaler = new Marshaler();

        $json = $this->buildLogJson($request);
        $params = [
            'TableName' => self::TABLENAME,
            'Item' => $marshaler->marshalJson($json),
        ];

        try {
            $this->dynamodb->putItem($params);
        } catch (CredentialsException $e) {
            if (isset($this->logger)) {
                $this->logger->error('Could not connect to DynamoDB', ['exception' => $e]);
            }
        } catch (DynamoDbException $e) {
            if (isset($this->logger)) {
                $this->logger->error('Could not log API request to DynamoDB', ['exception' => $e]);
            }
        }
    }

    /**
     * Builds a log entry and returns the JSON encoded version.
     */
    public function buildLogJson(Request $request): string
    {
        // log timestamps must always be in UTC
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('UTC'));

        $params = [
            'environment' => $this->environment,
            'request_id' => $request->attributes->get('requestId'),
            'correlation_id' => $request->attributes->get('correlationId'),
            'timestamp' => $date->format('Y-m-d H:i:s.v'),
            'method' => $request->getMethod(),
            'endpoint' => $request->getPathInfo(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'expires' => strtotime('+90 days'),
            'id' => (string) $this->tenantContext->get()->id(),
        ];

        // look for a tenant ID provided with the request
        if ($tenantId = $request->headers->get('X-Tenant-Id')) {
            $params['tenant_id'] = $tenantId;
        }

        // log any request/responses from the current gateway
        if ($id = $this->getCurrentGateway()) {
            $params['gateway'] = $id;
        }

        if ($request = $this->getLastRequest()) {
            $params['gateway_request'] = $this->compress($request);
        }

        if ($response = $this->getLastResponse()) {
            $params['gateway_response'] = $response;
        }

        if ($time = $this->getLastResponseTiming()) {
            $params['gateway_response_time'] = round($time * 1000);
        }

        return (string) json_encode($params);
    }

    /**
     * Compresses a string.
     */
    private function compress(string $str): string
    {
        if (strlen($str) > self::MAX_STRING_SIZE) {
            mb_internal_encoding('UTF-8');
            $str = mb_strcut($str, 0, self::MAX_STRING_SIZE);
        }

        return base64_encode((string) gzdeflate($str, 9));
    }
}
