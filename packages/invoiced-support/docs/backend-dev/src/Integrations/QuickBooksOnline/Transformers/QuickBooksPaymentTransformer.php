<?php

namespace App\Integrations\QuickBooksOnline\Transformers;

use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ReadSync\AbstractPaymentTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\ValueObjects\QuickBooksPaymentLine;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Core\Orm\Model;

class QuickBooksPaymentTransformer extends AbstractPaymentTransformer
{
    public function __construct(
        private QuickBooksApi $quickBooksApi,
    ) {
    }

    /**
     * @param QuickBooksAccount $account
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        parent::initialize($account, $syncProfile);
        $this->quickBooksApi->setAccount($account);
    }

    /**
     * Builds a record for a QuickBooks payment.
     *
     * The record built is not 1-1 with the Invoiced payment instance
     * created using syncRecord. Unlike other transformers which use a 1-1
     * record to object instance, this transformer uses the record created in
     * this method to create an AccountingPayment object which is then
     * reconciled using AccountingPaymentLoader.
     *
     * I.e. Not all keys in the record returned from this method are values
     * that will be stored in the payment. E.g. The customer key is a reference
     * to the QBO customer, not the Invoiced customer.
     *
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // Detect if voided
        if (0 === count($input->document->Line ?? [])) {
            if (0 === $input->document->TotalAmt) {
                return [
                    'accounting_id' => $input->document->Id,
                    'voided' => true,
                ];
            }

            return null;
        }

        $record['method'] = PaymentMethod::OTHER;
        $record['currency'] ??= $this->syncProfile->tenant()->currency;

        // Parse line items into splits
        $record['applied_to'] = $this->buildSplits($input->document, $record['currency']);
        if (!$record['applied_to']) {
            // A QBO payment may consist of expenses and credits that don't contain the
            // Invoice or CreditMemo TxnType. In this case, 0 splits will be parsed and
            // the payment should not be imported.
            return null;
        }

        return $record;
    }

    /**
     * Parses line items to calculate how a payment is applied
     * to different documents.
     *
     * NOTE: A 'document application' mentioned in the comments is
     * referring to how a payment is applied to a document. It's
     * synonymous to a LinkedTxn entry and similar to an Invoiced
     * split.
     *
     * @throws TransformException
     */
    private function buildSplits(object $input, string $currency): array
    {
        // Store parsed line data from QBO payment instance.
        /** @var QuickBooksPaymentLine[] $invoiceLines */
        $invoiceLines = [];
        /** @var QuickBooksPaymentLine[] $cnLines */
        $cnLines = [];

        // Parse line items to determine document applications.
        // Lines with TxnType other than 'CreditMemo' or 'Invoice'
        // are ignored.
        foreach ($input->Line ?? [] as $line) {
            $documentId = $line->LinkedTxn[0]->TxnId;
            $documentType = $line->LinkedTxn[0]->TxnType;
            if (QuickBooksApi::CREDIT_NOTE === $documentType) {
                $docNumber = $this->getDocumentNumber($documentType, $documentId);
                $document = [
                    'accounting_id' => $documentId,
                    'number' => $docNumber,
                ];
                $cnLines[] = new QuickBooksPaymentLine($document, Money::fromDecimal($currency, $line->Amount));
            } elseif (QuickBooksApi::INVOICE === $documentType) {
                $docNumber = $this->getDocumentNumber($documentType, $documentId);
                $document = [
                    'accounting_id' => $documentId,
                    'number' => $docNumber,
                ];
                $invoiceLines[] = new QuickBooksPaymentLine($document, Money::fromDecimal($currency, $line->Amount));
            }
        }

        return $this->makePaymentSplits($cnLines, $invoiceLines, $currency);
    }

    /**
     * @throws TransformException
     */
    private function getDocumentNumber(string $documentType, string $documentId): string
    {
        // Retrieve the document to get the number which is not included with the payment
        try {
            $qboDocument = $this->quickBooksApi->get($documentType, $documentId);

            return $qboDocument->DocNumber ?? '';
        } catch (IntegrationApiException $e) {
            throw new TransformException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Build splits using QBO payment lines
     * The splits built use QBO ids rather than Invoiced ids because
     * the splits built are used to build AccountingPaymentSplits.
     * The AccountingPaymentSplit requires a Model instance which
     * may not be accessible in this scope. For instance, the Models
     * may be imported in `syncRecord()`.
     *
     * Calculation involves applying the available credits in a greedy fashion.
     * Credits from a credit note will be applied as much as possible to the first
     * invoice, then move to the next if it still has credits available.
     * The amounts on the QBO payment lines are adjusted to keep track of these
     * credit applications. The resulting amounts are then used to build
     * the splits.
     *
     * @param QuickBooksPaymentLine[] $cnLines
     * @param QuickBooksPaymentLine[] $invoiceLines
     */
    private function makePaymentSplits(array $cnLines, array $invoiceLines, string $currency): array
    {
        $qboSplits = [];
        foreach ($cnLines as $cnLine) {
            foreach ($invoiceLines as $invoiceLine) {
                $invoiceAmount = $invoiceLine->getAmount();
                $creditNoteAmount = $cnLine->getAmount();
                // Credit note has already been applied or invoice has been fully paid.
                if ($creditNoteAmount->isZero() || $invoiceAmount->isZero()) {
                    continue;
                }

                if ($creditNoteAmount->lessThanOrEqual($invoiceAmount)) {
                    $amount = $creditNoteAmount;
                    // The entire credit note can (and will) be used by this invoice.
                    $invoiceLine->setAmount($invoiceAmount->subtract($amount));
                    $cnLine->setAmount(Money::fromDecimal($currency, 0));
                } else {
                    $amount = $invoiceAmount;
                    // The entire invoice can (and will) be paid by a the credit note.
                    $cnLine->setAmount($creditNoteAmount->subtract($amount));
                    $invoiceLine->setAmount(Money::fromDecimal($currency, 0));
                }

                $qboSplits[] = [
                    'amount' => $amount->toDecimal(),
                    'type' => PaymentItemType::CreditNote->value,
                    'invoice' => $invoiceLine->document,
                    'credit_note' => $cnLine->document,
                    'document_type' => 'invoice',
                ];
            }
        }

        // Use remaining invoice applications as amount
        // of payment received.
        //
        // NOTICE: Applying credit note leftovers is currently unsupported.
        foreach ($invoiceLines as $invoiceLine) {
            $amount = $invoiceLine->getAmount();
            if ($amount->isPositive()) {
                $qboSplits[] = [
                    'amount' => $amount->toDecimal(),
                    'type' => PaymentItemType::Invoice->value,
                    'invoice' => $invoiceLine->document,
                ];
            }
        }

        return $qboSplits;
    }
}
