<?php

namespace App\AccountsReceivable\Operations;

use App\AccountsReceivable\Exception\ConsolidationException;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareTrait;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Query;

/**
 * Performs invoice consolidation for a customer. The process
 * of consolidation takes many invoices for a customer, and potentially
 * sub-customers, and converts that into a single invoice. The original
 * invoices are destroyed in the process.
 *
 * Here is invoice data that is lost after consolidation:
 * (any invoice-level information)
 * - payment terms
 * - shipping details
 * - allowed payment methods
 * - invoice metadata
 * - notes
 */
class InvoiceConsolidator
{
    use LoggerAwareTrait;

    public function __construct(private Connection $database)
    {
    }

    /**
     * Performs invoice consolidation for a customer.
     *
     * This should only be called within a database transaction.
     *
     * @throws ConsolidationException when the consolidation process fails unexpectedly
     */
    public function consolidate(Customer $customer, ?int $cutoffDate = null): ?Invoice
    {
        // check if consolidation is enabled for the customer
        if (!$customer->consolidated) {
            throw new ConsolidationException('This customer does not have invoice consolidation enabled.');
        }

        $cutoffDate ??= time();
        $currency = $customer->calculatePrimaryCurrency(null, null, true);

        // fetch the invoices and credit notes to consolidated
        // if there are no invoices to consolidate, then no invoice is generated
        $invoices = $this->getInvoices($customer, $currency, $cutoffDate);
        if (0 === count($invoices)) {
            return null;
        }

        $creditNotes = $this->getCreditNotes($customer, $currency, $cutoffDate);

        return $this->buildConsolidatedInvoice($customer, $currency, $invoices, $creditNotes);
    }

    //
    // Helpers
    //

    /**
     * @param Invoice[]    $invoices
     * @param CreditNote[] $creditNotes
     *
     * @throws ConsolidationException
     */
    private function buildConsolidatedInvoice(Customer $customer, string $currency, array $invoices, array $creditNotes): Invoice
    {
        try {
            // create a new invoice to be the container for our consolidated invoice
            $consolidatedInvoice = $this->newConsolidatedInvoice($customer, $currency);

            // add the invoices and credit notes to the consolidated invoice
            $this->consolidateDocuments($consolidatedInvoice, $currency, $invoices, $creditNotes);

            return $consolidatedInvoice;
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error('Exception occurred during invoice consolidation process', ['exception' => $e]);
            }

            // then rethrow
            throw new ConsolidationException('An error occurred when consolidating your invoices', $e->getCode(), $e);
        }
    }

    /**
     * Gets up to 100 invoices for this customer and any immediate
     * sub-customers that are eligible for consolidation.
     *
     * @return Invoice[]
     */
    private function getInvoices(Customer $customer, string $currency, int $cutoffDate): array
    {
        $query = Invoice::query()
            ->where('consolidated', false)
            ->where('closed', false)
            ->where('paid', false);

        return $this->getDocuments($query, $customer, $currency, $cutoffDate, 'Invoices');
    }

    /**
     * Gets up to 100 credit notes for this customer and any immediate
     * sub-customers that are eligible for consolidation.
     *
     * @return CreditNote[]
     */
    private function getCreditNotes(Customer $customer, string $currency, int $cutoffDate): array
    {
        $query = CreditNote::query()
            ->join(Invoice::class, 'invoice_id', 'id')
            ->where('Invoices.closed', false)
            ->where('Invoices.paid', false);

        return $this->getDocuments($query, $customer, $currency, $cutoffDate, 'CreditNotes');
    }

    private function getDocuments(Query $query, Customer $customer, string $currency, int $cutoffDate, string $tablename): array
    {
        return $query->join(Customer::class, 'customer', 'Customers.id')
            ->where('('.$tablename.'.customer = '.$customer->id().' OR (Customers.parent_customer = '.$customer->id().' AND Customers.consolidated = 1 AND Customers.bill_to_parent = 1))')
            ->where($tablename.'.draft', false)
            ->where($tablename.'.currency', $currency)
            ->where($tablename.'.voided', false)
            ->where($tablename.'.date', $cutoffDate, '<=')
            ->sort($tablename.'.date ASC')
            ->first(100);
    }

    private function newConsolidatedInvoice(Customer $customer, string $currency): Invoice
    {
        $consolidatedInvoice = new Invoice();
        $consolidatedInvoice->consolidated = true;
        $consolidatedInvoice->name = 'Consolidated Invoice';
        $consolidatedInvoice->setCustomer($customer);
        $consolidatedInvoice->currency = $currency;
        $consolidatedInvoice->calculate_taxes = false;
        $consolidatedInvoice->setRelation('customer', $customer);
        $consolidatedInvoice->saveOrFail();

        return $consolidatedInvoice;
    }

    /**
     * @param Invoice[]    $invoices
     * @param CreditNote[] $creditNotes
     *
     * @throws ModelException
     */
    private function consolidateDocuments(Invoice $consolidatedInvoice, string $currency, array $invoices, array $creditNotes): void
    {
        $items = [];
        $discounts = [];
        $taxes = [];
        $total = new Money($currency, 0);
        $paid = new Money($currency, 0);

        foreach ($invoices as $invoice) {
            $this->consolidateDocument($consolidatedInvoice, $invoice, $items, $discounts, $taxes);

            $total = $total->add(Money::fromDecimal($currency, $invoice->total));
            $paid = $paid->add(Money::fromDecimal($currency, $invoice->amount_paid));
        }

        foreach ($creditNotes as $creditNote) {
            $this->consolidateDocument($consolidatedInvoice, $creditNote, $items, $discounts, $taxes);

            $creditNoteTotal = Money::fromDecimal($currency, $creditNote->total);
            $total = $total->subtract($creditNoteTotal);
        }

        // save the collected line items, discounts, taxes, payments, and credits on the consolidated invoice
        $this->finishConsolidatedInvoice($consolidatedInvoice, $items, $discounts, $taxes, $total, $paid);
    }

    /**
     * @throws ModelException
     */
    private function consolidateDocument(Invoice $consolidatedInvoice, Invoice|CreditNote $document, array &$items, array &$discounts, array &$taxes): void
    {
        $document->skipClosedCheck();
        $document->consolidated_invoice_id = (int) $consolidatedInvoice->id();
        $document->void(true);

        // add line items to consolidated invoice
        $isCreditNote = $document instanceof CreditNote;
        foreach ($document->items() as $item) {
            unset($item['id']);

            foreach ($item['discounts'] as &$itemDiscount) {
                unset($itemDiscount['id']);

                if ($isCreditNote) {
                    $itemDiscount['amount'] *= -1;
                }
            }

            foreach ($item['taxes'] as &$itemTax) {
                unset($itemTax['id']);

                if ($isCreditNote) {
                    $itemTax['amount'] *= -1;
                }
            }

            if ($isCreditNote) {
                $item['quantity'] *= -1;
            }

            $items[] = $item;
        }

        // add subtotal discounts to consolidated invoice
        foreach ($document->discounts() as $discount) {
            unset($discount['id']);

            if ($isCreditNote) {
                $discount['amount'] *= -1;
            }

            $discounts[] = $discount;
        }

        // add subtotal taxes to consolidated invoice
        foreach ($document->taxes() as $tax) {
            unset($tax['id']);

            if ($isCreditNote) {
                $tax['amount'] *= -1;
            }

            $taxes[] = $tax;
        }

        // update any payments to be applied to the consolidated invoice
        if ($document instanceof Invoice && $document->amount_paid > 0) {
            $this->database->update('Transactions', [
                'invoice' => $consolidatedInvoice->id(),
            ], [
                'invoice' => $document->id(),
                'tenant_id' => $document->tenant_id,
            ]);
        }
    }

    private function finishConsolidatedInvoice(Invoice $consolidatedInvoice, array $items, array $discounts, array $taxes, Money $total, Money $paid): void
    {
        $consolidatedInvoice->items = $items;
        $consolidatedInvoice->discounts = $discounts;
        $consolidatedInvoice->taxes = $taxes;
        $consolidatedInvoice->skipClosedCheck();
        $isPaid = $total->subtract($paid)->isZero();
        $consolidatedInvoice->closed = $isPaid;
        $consolidatedInvoice->amount_paid = $paid->toDecimal();
        $consolidatedInvoice->saveOrFail();
    }
}
