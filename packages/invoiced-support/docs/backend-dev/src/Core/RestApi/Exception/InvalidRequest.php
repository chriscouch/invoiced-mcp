<?php

namespace App\Core\RestApi\Exception;

class InvalidRequest extends ApiHttpException
{
    public function __construct(string $message, int $statusCode = 400, private ?string $param = null)
    {
        parent::__construct($statusCode, $message);
    }

    public function getParam(): ?string
    {
        return $this->param;
    }
}
