<?php

namespace App\Core\RestApi\Serializer;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\ApiHttpException;
use App\Core\RestApi\Interfaces\EncoderInterface;
use App\Core\RestApi\Interfaces\NormalizerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serializes a result from an API route handler, that
 * could be any type, into a response object.
 */
class Serializer
{
    /**
     * @param NormalizerInterface[] $normalizers
     */
    public function __construct(
        private EncoderInterface $encoder,
        private array $normalizers = [],
    ) {
    }

    /**
     * Adds a normalizer to the chain. Normalizers are
     * executed in FIFO order until a result is returned.
     *
     * @return $this
     */
    public function addNormalizer(NormalizerInterface $normalizer)
    {
        $this->normalizers[] = $normalizer;

        return $this;
    }

    /**
     * Gets the chain of normalizers.
     */
    public function getNormalizers(): array
    {
        return $this->normalizers;
    }

    /**
     * Gets the encoder used.
     */
    public function getEncoder(): EncoderInterface
    {
        return $this->encoder;
    }

    /**
     * Serializes the given input into an HTTP response.
     *
     * @throws ApiHttpException
     */
    public function serialize(mixed $input, Response $response): Response
    {
        $normalizedOutput = false;
        foreach ($this->normalizers as $normalizer) {
            $normalizedOutput = $normalizer->normalize($input);
            if ($normalizedOutput) {
                break;
            }
        }

        if (!is_array($normalizedOutput)) {
            throw new ApiError('There was an error serializing the response.');
        }

        return $this->encoder->encode($normalizedOutput, $response);
    }
}
