<?php

namespace App\Integrations\Interfaces;

use App\Companies\Models\Company;

/**
 * Common interface for a class that processes a webhook event received by an integration.
 */
interface WebhookHandlerInterface
{
    /**
     * Checks if a webhook event should be processed.
     *
     * @param array $event webhook payload
     */
    public function shouldProcess(array &$event): bool;

    /**
     * Gets the list of companies that need to process this webhook event.
     *
     * @param array $event webhook payload
     *
     * @return Company[]
     */
    public function getCompanies(array $event): array;

    /**
     * Processes a webhook event.
     *
     * @param array $event webhook payload
     */
    public function process(Company $company, array $event): void;
}
