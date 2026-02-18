<?php

namespace App\Integrations\Adyen\Interfaces;

use App\Integrations\Adyen\Exception\AdyenReconciliationException;

interface ReportHandlerInterface
{
    /**
     * Feeds a single row into the report handler.
     *
     * @throws AdyenReconciliationException
     */
    public function handleRow(array $row): void;

    /**
     * Informs the report handler that there are no more rows left.
     *
     * @throws AdyenReconciliationException
     */
    public function finish(): void;
}
