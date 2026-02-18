<?php

namespace App\Core\RestApi\Interfaces;

use Symfony\Component\HttpFoundation\Response;

/**
 * An encoder handler takes a normalized array
 * and converts that into an HTTP response. For example,
 * an encoder might generate XML or JSON given normalized input.
 */
interface EncoderInterface
{
    /**
     * Encodes the given input according to this encoding scheme.
     */
    public function encode(array $input, Response $response): Response;
}
