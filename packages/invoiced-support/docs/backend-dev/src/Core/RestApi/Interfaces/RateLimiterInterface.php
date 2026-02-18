<?php

namespace App\Core\RestApi\Interfaces;

/**
 * Represents a rate limiter.
 */
interface RateLimiterInterface
{
    /**
     * Checks if a request is allowed.
     *
     * @return bool when false the request should be rate limited
     */
    public function isAllowed(string $userId): bool;

    /**
     * Cleans up after a request is finished.
     */
    public function cleanUpAfterRequest(string $userId): void;

    /**
     * Gets the error message when the rate limiting fails.
     */
    public function getErrorMessage(): string;
}
