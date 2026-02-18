<?php

namespace App\Core\RestApi\Encoders;

use App\Core\RestApi\Interfaces\EncoderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates JSON API responses.
 */
class JsonEncoder implements EncoderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_JSON_PARAMS = JSON_INVALID_UTF8_SUBSTITUTE;

    private int $jsonParams = self::DEFAULT_JSON_PARAMS;

    public function __construct(RequestStack $requestStack)
    {
        // pretty print when requested or if the client is curl
        $request = $requestStack->getCurrentRequest();
        if ($request && str_starts_with((string) $request->headers->get('User-Agent'), 'curl')) {
            $this->prettyPrint();
        }
    }

    /**
     * Serializes input to pretty printed JSON.
     */
    public function prettyPrint(): void
    {
        $this->jsonParams = self::DEFAULT_JSON_PARAMS | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    }

    /**
     * Serializes input to compact JSON.
     */
    public function compactPrint(): void
    {
        $this->jsonParams = self::DEFAULT_JSON_PARAMS;
    }

    /**
     * Gets the parameters to be passed to json_encode.
     */
    public function getJsonParams(): int
    {
        return $this->jsonParams;
    }

    public function encode(array $input, Response $response): Response
    {
        $result = json_encode($input, $this->jsonParams);

        if (is_string($result)) {
            $response->setContent($result);
        }

        if (json_last_error()) {
            if (isset($this->logger)) {
                $this->logger->error(json_last_error_msg());
            }

            return $response;
        }

        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
