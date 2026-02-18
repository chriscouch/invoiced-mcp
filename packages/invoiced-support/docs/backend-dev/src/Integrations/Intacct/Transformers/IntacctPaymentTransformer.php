<?php

namespace App\Integrations\Intacct\Transformers;

use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\AccountingPaymentItem;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Libs\IntacctMapper;
use App\Integrations\Intacct\Libs\IntacctVoidFinder;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\ValueObjects\IntacctPaymentLine;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Core\Orm\Model;
use SimpleXMLElement;

class IntacctPaymentTransformer implements TransformerInterface
{
    private IntacctMapper $mapper;
    private string $defaultCurrency;
    private bool $importBillToContacts = false;
    private IntacctAccount $account;

    public function __construct(
        private IntacctVoidFinder $voidFinder
    ) {
        $this->mapper = new IntacctMapper();
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->defaultCurrency = $account->tenant()->currency;
        $this->importBillToContacts = IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO == $syncProfile->customer_import_type;
        $this->account = $account;
    }

    /**
     * @param AccountingXmlRecord $intacctPayment
     */
    public function transform(AccountingRecordInterface $intacctPayment): ?AccountingPayment
    {
        // If this is a reversal then we have to find the original payment ID.
        // A reversal is a separate transaction and does not indicate which
        // original payment that it reverses.
        if ('V' == $intacctPayment->document->{'STATE'}) {
            // If the payment is not mapped then we have to void it by finding a match
            if ($voidedPayment = $this->voidFinder->findMatch($intacctPayment->document, $this->account)) {
                return new AccountingPayment(
                    integration: IntegrationType::Intacct,
                    accountingId: $voidedPayment->{'RECORDNO'},
                    voided: true,
                );
            }

            // If the original payment is not found then we do not proceed with syncing the record.
            return null;
        }

        // parse the currency
        $currency = strtolower($intacctPayment->document->{'CURRENCY'});
        if (!$currency) {
            $currency = $this->defaultCurrency;
        }

        // parse the receipt date
        $timestamp = $this->mapper->parseIsoDate((string) $intacctPayment->document->{'RECEIPTDATE'});

        // parse the reference number
        $refId = (string) $intacctPayment->document->{'DOCNUMBER'};
        if (!$refId) {
            $refId = (string) $intacctPayment->document->{'RECORDID'};
        }
        if (!$refId) {
            $refId = null;
        }

        // build the splits
        $splits = $this->buildSplits($intacctPayment->document, $currency);

        // override customer w/ that of the splits for bill_to_contact mode
        if ($this->importBillToContacts) {
            // The invoice customer is used for bill_to_contact mode because
            // the Intacct "Bill-To Contact" Invoiced customer is the owner of
            // the invoice in Invoiced. We are going to allow the backend to
            // inherit the customer from the first line item.
            $customer = null;
        } else {
            $customer = new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '', // The payment data does not include the customer record number
                values: [
                    'name' => (string) $intacctPayment->document->{'CUSTOMERNAME'},
                    'number' => (string) $intacctPayment->document->{'CUSTOMERID'},
                ],
            );
        }

        // INVD-2570: Payment method determined by payment application (i.e splits)
        // When no invoice split was found (no amount received)
        // the payment method should be 'other'
        $method = PaymentMethod::OTHER;
        foreach ($splits as $split) {
            if (PaymentItemType::Invoice->value === $split->type) {
                // parse payment method
                $method = $this->mapper->parsePaymentMethod((string) $intacctPayment->document->{'PAYMENTTYPE'});
                break;
            }
        }

        $record = [
            'date' => $timestamp,
            'method' => $method,
            'reference' => $refId,
        ];
        if ($entity_id = (string) $intacctPayment->document->{'MEGAENTITYID'}) {
            $record['metadata']['intacct_entity'] = $entity_id;
        }

        return new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: (string) $intacctPayment->document->{'RECORDNO'},
            values: $record,
            currency: $currency,
            customer: $customer,
            appliedTo: $splits,
        );
    }

    /**
     * Builds payment splits.
     *
     * @return AccountingPaymentItem[]
     */
    private function buildSplits(SimpleXMLElement $intacctPayment, string $currency): array
    {
        $invoiceLines = [];
        $cnLines = [];

        if (0 == count($intacctPayment->{'INVOICES'}->children())) {
            return [];
        }

        foreach ($intacctPayment->{'INVOICES'} as $line) {
            $recordNo = (string) $line->{'RECORD'};
            $amount = Money::fromDecimal($currency, abs((float) $line->{'APPLIEDAMOUNT'}));
            $invoiceLines[] = new IntacctPaymentLine($recordNo, $amount, new AccountingInvoice(
                integration: IntegrationType::Intacct,
                accountingId: $recordNo,
                values: ['number' => (string) $line->{'RECORDID'}],
            ));
        }

        if (count($intacctPayment->{'CREDITS'}->children()) > 0) {
            // The conditional surrounds the foreach loop because
            // the CREDITS tag always exists. This means even
            // if there are no credits, the loop will execute once and
            // check for a mapping. The mapping will return null
            // and this would immediately return [].
            foreach ($intacctPayment->{'CREDITS'} as $line) {
                // Known record types values:
                // rr = advance payment
                // rp = payment/overpayment
                // ri = invoice or credit note
                // ra = a/r adjustment
                // We only want to consider applied A/R adjustments or credit notes
                // as a credit note line.
                if (!in_array((string) $line->{'RECORDTYPE'}, ['ra', 'ri'])) {
                    continue;
                }

                $recordNo = (string) $line->{'RECORD'};
                $amount = Money::fromDecimal($currency, abs((float) $line->{'APPLIEDAMOUNT'}));
                $cnLines[] = new IntacctPaymentLine($recordNo, $amount, new AccountingCreditNote(
                    integration: IntegrationType::Intacct,
                    accountingId: $recordNo,
                    values: ['number' => (string) $line->{'RECORDID'}],
                ));
            }
        }

        // Build splits using Intacct payment lines.
        //
        // Calculation involves applying the available credits in a greedy fashion.
        // Credits from a credit note will be applied as much as possible to the first
        // invoice, then move to the next if it still has credits available.
        // The amounts on the Intacct payment lines are adjusted to keep track of these
        // credit applications. The resulting amounts are then used to build
        // the splits. This approach is identical to QuickBooksPaymentTransformer's
        // approach.
        $splits = [];
        foreach ($cnLines as $cnLine) {
            foreach ($invoiceLines as $invoiceLine) {
                /** @var Money|null $amount */
                $amount = null;
                $invoiceAmount = $invoiceLine->getAmount();
                $creditNoteAmount = $cnLine->getAmount();

                if ($creditNoteAmount->isZero()) {
                    // Credit note has already been applied.
                    continue;
                }

                if ($invoiceAmount->isZero()) {
                    // Invoice has been fully paid.
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

                $splits[] = new AccountingPaymentItem(
                    amount: $amount,
                    type: PaymentItemType::CreditNote->value,
                    invoice: $invoiceLine->document, /* @phpstan-ignore-line */
                    creditNote: $cnLine->document, /* @phpstan-ignore-line */
                    documentType: 'invoice',
                );
            }
        }

        // Use remaining invoice applications as amount
        // of payment received.
        foreach ($invoiceLines as $invoiceLine) {
            $amount = $invoiceLine->getAmount();
            if ($amount->greaterThan(Money::fromDecimal($currency, 0))) {
                $splits[] = new AccountingPaymentItem(
                    amount: $amount,
                    invoice: $invoiceLine->document, /* @phpstan-ignore-line */
                );
            }
        }

        return $splits;
    }
}
