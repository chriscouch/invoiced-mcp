<?php

namespace App\Integrations\Interfaces;

use App\Integrations\Exceptions\IntegrationException;

/**
 * Each instance represents an integration with an external cloud
 * service.
 */
interface IntegrationInterface
{
    /**
     * Indicates whether the integration is an accounting integration.
     */
    public function isAccountingIntegration(): bool;

    /**
     * Checks if the integration is connected.
     */
    public function isConnected(): bool;

    /**
     * Gets the name of the connected account.
     */
    public function getConnectedName(): ?string;

    /**
     * Gets the extra integration-specific information that is accessible to the end user.
     */
    public function getExtra(): \stdClass;

    /**
     * Disconnects the integration within our system. Implementations
     * should not perform a callout to the external service here.
     *
     * @throws IntegrationException if the operation fails
     */
    public function disconnect(): void;
}
