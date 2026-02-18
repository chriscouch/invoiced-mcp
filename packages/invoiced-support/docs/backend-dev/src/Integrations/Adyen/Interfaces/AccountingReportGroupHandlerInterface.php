<?php

namespace App\Integrations\Adyen\Interfaces;

use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * Handles a set of rows that are line items for the same transaction and type
 * within the accounting report, such as a refund or payment. Classes that
 * implement this interface can handle a specific type of transaction.
 */
interface AccountingReportGroupHandlerInterface
{
    /**
     * Handles a set of grouped rows from the accounting report.
     *
     * @throws AdyenReconciliationException
     */
    public function handleRows(MerchantAccount $merchantAccount, string $identifier, array $rows): void;
}
