<?php

namespace App\Core\RestApi\Exception;

class ApiError extends ApiHttpException
{
    public function __construct(string $message, int $statusCode = 500)
    {
        parent::__construct($statusCode, $message);
    }
}
