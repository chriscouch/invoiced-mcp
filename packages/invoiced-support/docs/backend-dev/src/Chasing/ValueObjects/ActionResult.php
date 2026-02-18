<?php

namespace App\Chasing\ValueObjects;

/**
 * The result from the execution of a scheduled chasing activity,
 * i.e. sending an email to a customer.
 */
final class ActionResult
{
    public function __construct(private bool $successful, private ?string $message = null)
    {
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
