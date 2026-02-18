<?php

namespace App\Statements\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Utils\ModelUtility;
use App\Statements\Interfaces\OpenItemStatementLineInterface;
use App\Statements\StatementLines\OpenItemStatementLineFactory;

final class OpenItemStatementData
{
    public function __construct(
        private OpenItemStatementLineFactory $factory,
        private CustomerHierarchy $hierarchy,
    ) {
    }

    /**
     * Gets the customer IDs to be included in the statement.
     * This will include sub-customers.
     *
     * @return int[]
     */
    public function getCustomerIds(Customer $customer): array
    {
        // include sub customer IDs in query
        $customerIds = $this->hierarchy->getSubCustomerIds($customer);
        $customerIds[] = $customer->id;

        return $customerIds;
    }

    /**
     * Gets a sorted list of statement lines based on
     * open transactions in the customer's account.
     *
     * @return OpenItemStatementLineInterface[]
     */
    public function getLines(array $customerIds, string $currency, int $end, bool $pastDueOnly): array
    {
        // get all open invoices and credit notes
        /** @var ReceivableDocument[] $activity */
        $activity = array_merge(
            $this->getInvoices($customerIds, $currency, $end, $pastDueOnly),
            $this->getCreditNotes($customerIds, $currency, $end, $pastDueOnly));
        $lines = $this->factory->makeFromList($activity);

        // sort activity by date
        usort($lines, [$this, 'lineSort']);

        return $lines;
    }

    /**
     * Get outstanding invoices.
     *
     * @return Invoice[]
     */
    private function getInvoices(array $customerIds, string $currency, int $end, bool $pastDueOnly): array
    {
        $query = Invoice::where('customer IN ('.implode(',', $customerIds).')')
            ->where('draft', false)
            ->where('currency', $currency)
            ->where('closed', false)
            ->where('voided', false)
            ->where('paid', false)
            ->where('date', $end, '<=');

        if ($pastDueOnly) {
            $query->where('status', InvoiceStatus::PastDue->value);
        }

        return ModelUtility::getAllModels($query);
    }

    /**
     * Get open credit notes.
     *
     * @return CreditNote[]
     */
    private function getCreditNotes(array $customerIds, string $currency, int $end, bool $pastDueOnly): array
    {
        // credit notes cannot be past due
        if ($pastDueOnly) {
            return [];
        }

        $query = CreditNote::where('customer IN ('.implode(',', $customerIds).')')
            ->where('draft', false)
            ->where('currency', $currency)
            ->where('closed', false)
            ->where('voided', false)
            ->where('paid', false)
            ->where('date', $end, '<=');

        return ModelUtility::getAllModels($query);
    }

    private function lineSort(OpenItemStatementLineInterface $a, OpenItemStatementLineInterface $b): int
    {
        // first, sort by date
        $aDate = $a->getDate();
        $bDate = $b->getDate();
        if ($aDate != $bDate) {
            return ($aDate > $bDate) ? 1 : -1;
        }

        // next, sort by amount
        return ($a->getLineTotal()->greaterThan($b->getLineTotal())) ? 1 : -1;
    }
}
