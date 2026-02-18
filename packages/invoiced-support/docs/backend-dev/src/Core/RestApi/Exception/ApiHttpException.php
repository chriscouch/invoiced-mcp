<?php

namespace App\Core\RestApi\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiHttpException extends HttpException
{
    /**
     * Gets the error type when returning a response from the API.
     */
    public function getErrorType(): string
    {
        $statusCode = $this->getStatusCode();
        if ($statusCode >= 500) {
            return 'api_error';
        }

        if (429 == $statusCode) {
            return 'rate_limit_error';
        }

        return 'invalid_request';
    }
}
