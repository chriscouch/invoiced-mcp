<?php

namespace App\Integrations\QuickBooksOnline\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingConvenienceFeeMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\PaymentRoute;
use App\Integrations\AccountingSync\Writers\AbstractPaymentWriter;
use App\Integrations\AccountingSync\WriteSync\PaymentAccountMatcher;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Core\Orm\Model;

class QuickBooksPaymentWriter extends AbstractPaymentWriter
{
    private const SUPPORTED_SPLIT_TYPES = [
        PaymentItemType::ConvenienceFee->value,
        PaymentItemType::CreditNote->value,
        PaymentItemType::Invoice->value,
    ];

    public function __construct(private QuickBooksApi $quickbooksApi, private QuickBooksInvoiceWriter $invoiceWriter, private QuickBooksCreditNoteWriter $creditNoteWriter, private QuickBooksCustomerWriter $customerWriter)
    {
    }

    //
    // Event Handlers
    //

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performCreate(Payment $payment, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            // create customer if no mapping exists
            if ($customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType())) {
                $qboCustomerId = $customerMapping->accounting_id;
            } else {
                $qboCustomer = $this->customerWriter->createQBOCustomer($customer, $syncProfile);
                $qboCustomerId = (string) $qboCustomer->Id;
            }

            // ensure documents exist on QBO
            $documentsMap = $this->processDocuments($payment, $qboCustomerId, $syncProfile);

            // create QBO payment
            if ($qboPaymentDetails = $this->buildQBOPaymentDetails($payment, $qboCustomerId, $documentsMap, $syncProfile)) {
                if ($qboPayment = $this->quickbooksApi->createPayment($qboPaymentDetails)) {
                    $this->savePaymentMapping($payment, $syncProfile->getIntegrationType(), $qboPayment->Id);
                }
            }
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performUpdate(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            // obtain payment from QBO
            $qboPayment = $this->quickbooksApi->getPayment($paymentMapping->accounting_id);
            $qboCustomerId = (string) $qboPayment->CustomerRef->value;

            // ensure documents exist on QBO.
            $documentsMap = $this->processDocuments($payment, $qboCustomerId, $syncProfile);

            if ($qboPaymentDetails = $this->buildQBOPaymentDetails($payment, $qboCustomerId, $documentsMap, $syncProfile)) {
                $this->quickbooksApi->updatePayment((string) $qboPayment->Id, (string) $qboPayment->SyncToken, $qboPaymentDetails);
            }
        } catch (IntegrationApiException|SyncException $e) {
            // When updating a payment if we get an account period closed error
            // then we simply ignore it. When the accounting period is closed modifying
            // the payment is not permitted.
            if (str_contains(strtolower($e->getMessage()), 'account period closed')) {
                return;
            }

            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performVoid(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            // obtain payment from QBO and void it
            $qboPayment = $this->quickbooksApi->getPayment($paymentMapping->accounting_id);
            $this->quickbooksApi->voidPayment((string) $qboPayment->Id, (string) $qboPayment->SyncToken);

            // void convenience fee invoice if exists
            $feeMapping = AccountingConvenienceFeeMapping::findForPayment($payment, $syncProfile->getIntegrationType());
            if ($feeMapping instanceof AccountingConvenienceFeeMapping) {
                $qboFeeInvoiceId = $feeMapping->accounting_id;
                $qboFeeInvoice = $this->quickbooksApi->getInvoice($qboFeeInvoiceId);
                $this->quickbooksApi->voidInvoice($qboFeeInvoice->Id, $qboFeeInvoice->SyncToken);
            }
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    //
    // Helpers
    //

    /**
     * Iterates through documents attached to payment object
     * to determine if they exist on QBO. Documents that do
     * not exist on QBO are created using their corresponding
     * writer class given the sync profile allows writing
     * of that type.
     *
     * @throws IntegrationApiException|SyncException
     *
     * @return array mapping document ids to their corresponding QBO document id
     */
    public function processDocuments(Payment $payment, string $qboCustomerId, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        $map = [
            'invoice' => [],
            'credit_note' => [],
            'estimate' => [],
        ];

        $unmappedInvoices = [];
        $unmappedCreditNotes = [];

        foreach ($payment->applied_to as $split) {
            if (!in_array($split['type'], self::SUPPORTED_SPLIT_TYPES)) {
                continue;
            } elseif (PaymentItemType::CreditNote->value === $split['type'] && !isset($split['invoice'])) {
                continue;
            }

            // Process invoices.
            if (isset($split['invoice'])) {
                $invoice = Invoice::find($split['invoice']);
                if ($invoice && $invoiceMapping = AccountingInvoiceMapping::findForInvoice($invoice, $syncProfile->getIntegrationType())) {
                    $map['invoice'][$invoice->id()] = $invoiceMapping->accounting_id;
                } elseif ($invoice) {
                    $unmappedInvoices[] = $invoice;
                }
            }

            // Process credit notes
            if (isset($split['credit_note'])) {
                $creditNote = CreditNote::find($split['credit_note']);
                if ($creditNote && $creditNoteMapping = $this->creditNoteWriter->getCreditNoteMapping($creditNote, $syncProfile->getIntegrationType())) {
                    $map['credit_note'][$creditNote->id()] = $creditNoteMapping->accounting_id;
                } elseif ($creditNote) {
                    $unmappedCreditNotes[] = $creditNote;
                }
            }
        }

        // Create missing invoices.
        foreach ($unmappedInvoices as $invoice) {
            $qboInvoice = $this->invoiceWriter->createQBOInvoice($invoice, $qboCustomerId, $syncProfile);
            $map['invoice'][$invoice->id()] = (string) $qboInvoice->Id;
        }

        // Create missing credit notes
        foreach ($unmappedCreditNotes as $creditNote) {
            $qboCreditNote = $this->creditNoteWriter->createQBOCreditMemo($creditNote, $qboCustomerId, $syncProfile);
            $map['credit_note'][$creditNote->id()] = (string) $qboCreditNote->Id;
        }

        return $map;
    }

    /**
     * Builds QBO payment request details.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function buildQBOPaymentDetails(Payment $payment, string $qboCustomerId, array $documentsMap, QuickBooksOnlineSyncProfile $syncProfile): ?array
    {
        // format date for QBO
        $txnDate = date('Y-m-d', $payment->date); // yyyy-mm-dd format

        $details = [
            'TxnDate' => $txnDate,
            'TotalAmt' => $payment->amount,
            'PrivateNote' => $this->buildQBOPaymentNotes($payment),
            'CustomerRef' => [
                'value' => $qboCustomerId,
            ],
            'PaymentMethodRef' => [
                'value' => $this->getQBOPaymentMethodId($payment->method),
            ],
        ];

        if ($lines = $this->buildQBOPaymentLines($payment, $qboCustomerId, $documentsMap, $syncProfile)) {
            $details['Line'] = $lines;
        } else {
            return null;
        }

        // Set 'Reference No.' field
        if ($reference = $payment->reference) {
            $details['PaymentRefNum'] = substr($reference, 0, 21);
        }

        // Set DepositToAccountRef
        if ($depositToAccountRef = $this->getQBODepositToAccountId($payment, $syncProfile)) {
            $details['DepositToAccountRef'] = [
                'value' => $depositToAccountRef,
            ];
        }

        // Set currency ref and exchange rate
        $company = $payment->tenant();
        if ($company->features->has('multi_currency')) {
            $details['CurrencyRef'] = [
                'value' => $payment->currency,
            ];

            if ($payment->currency != $company->currency) {
                $currency = strtoupper($payment->currency);
                $details['ExchangeRate'] = $this->quickbooksApi->getExchangeRate($currency, $txnDate)->Rate;
            }
        }

        return $details;
    }

    /**
     * Builds PrivateNote property for QBO payment object.
     */
    private function buildQBOPaymentNotes(Payment $payment): string
    {
        $notes = 'Invoiced ID: '.$payment->id();

        if ($charge = $payment->charge) {
            $notes .= "\nGateway: ".$charge->gateway;
            if ($source = $charge->payment_source) {
                $notes .= "\nSource: ".$source->toString();
            }
        }

        if ($paymentNotes = $payment->notes) {
            $notes .= "\nNotes: $paymentNotes";
        }

        return substr($notes, 0, 4000);
    }

    /**
     * Builds the 'Line' property for a QBO payment request.
     *
     * @throws SyncException
     */
    private function buildQBOPaymentLines(Payment $payment, string $qboCustomerId, array $documentsMap, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        /** @var Money[] $invoices */
        $invoices = [];

        /** @var Money[] $creditNotes */
        $creditNotes = [];

        // Iterate the splits to calculate the total amount
        // of credits + amount received for each document.
        $qboPaymentLines = [];
        foreach ($payment->applied_to as $split) {
            // skip documents from unsupported split types
            if (!in_array($split['type'], self::SUPPORTED_SPLIT_TYPES)) {
                continue;
            } elseif (PaymentItemType::CreditNote->value === $split['type'] && !isset($split['invoice'])) {
                continue;
            }

            $amount = Money::fromDecimal($payment->currency, $split['amount']);
            // Check for 'invoice' key instead of by split type
            // because credit note split type uses 'invoice'
            // as well.
            if (isset($split['invoice'])) {
                if (PaymentItemType::CreditNote->value === $split['type']) {
                    // add to total amount used by this credit note
                    $id = $split['credit_note'];
                    $creditNotes[$id] = isset($creditNotes[$id])
                        ? $creditNotes[$id]->add($amount)
                        : $amount;
                }

                // add to total amount used by this invoice
                $id = $split['invoice'];
                $invoices[$id] = isset($invoices[$id])
                    ? $invoices[$id]->add($amount)
                    : $amount;
            } elseif (PaymentItemType::ConvenienceFee->value === $split['type']) {
                $qboPaymentLines[] = $this->buildConvenienceFeeLine($payment, $amount, $qboCustomerId, $syncProfile);
            }
        }

        // Create invoice lines
        foreach ($invoices as $id => $amount) {
            $qboPaymentLines[] = [
                'Amount' => $amount->toDecimal(),
                'LinkedTxn' => [[
                    'TxnType' => 'Invoice',
                    'TxnId' => $documentsMap['invoice'][$id],
                ]],
            ];
        }

        // Create credit note lines
        foreach ($creditNotes as $id => $amount) {
            $qboPaymentLines[] = [
                'Amount' => $amount->toDecimal(),
                'LinkedTxn' => [[
                    'TxnType' => 'CreditMemo',
                    'TxnId' => $documentsMap['credit_note'][$id],
                ]],
            ];
        }

        return $qboPaymentLines;
    }

    /**
     * Ties a convenience fee to its own invoice on QBO.
     * If the payment is being created, a QBO invoice is created.
     * If the payment is being updated, the convenience fee invoice
     * id is retrieved from an AccountingConvenienceFeeMapping.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function buildConvenienceFeeLine(Payment $payment, Money $amount, string $qboCustomerId, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        $feeMapping = AccountingConvenienceFeeMapping::findForPayment($payment, $syncProfile->getIntegrationType());
        if ($feeMapping instanceof AccountingConvenienceFeeMapping) {
            $qboFeeInvoiceId = $feeMapping->accounting_id;
        } else {
            // Create fee invoice on QBO
            $feeInvoice = $this->createQBOFeeInvoice($payment, $amount, $qboCustomerId, $syncProfile);
            $qboFeeInvoiceId = $feeInvoice->Id;
        }

        return [
            'Amount' => $amount->toDecimal(),
            'LinkedTxn' => [[
                'TxnType' => 'Invoice',
                'TxnId' => $qboFeeInvoiceId,
            ]],
        ];
    }

    /**
     * Creates an invoice on QBO which represents a convenience fee.
     */
    private function createQBOFeeInvoice(Payment $payment, Money $amount, string $qboCustomerId, QuickBooksOnlineSyncProfile $syncProfile): \stdClass
    {
        // create fee invoice and save mapping
        $feeInvoice = $this->buildConvenienceFeeInvoice($payment, $amount);
        $qboFeeInvoice = $this->invoiceWriter->createQBOInvoice($feeInvoice, $qboCustomerId, $syncProfile, false);
        $this->saveConvenienceFeeMapping($payment, $syncProfile->getIntegrationType(), $qboFeeInvoice->Id);

        return $qboFeeInvoice;
    }

    /**
     * Attempts to find the Id of an existing QBO PaymentMethod.
     * If one is not found, one is created with the $method
     * provided.
     *
     * @throws IntegrationApiException
     */
    private function getQBOPaymentMethodId(string $method): string
    {
        $name = $this->formatPaymentMethodName($method);
        $foundPaymentMethod = $this->quickbooksApi->getPaymentMethodByName($name);
        if (!$foundPaymentMethod) {
            $foundPaymentMethod = $this->quickbooksApi->createPaymentMethod([
                'Name' => $name,
            ]);
        }

        return (string) $foundPaymentMethod->Id;
    }

    /**
     * Determines the correct account which a payment should be deposited
     * to in QuickBooks by matching the payment method and payment type
     * of the sync profiles payment account to that of the payment.
     * Looks up the matched account on QuickBooks to obtain its ID
     * or returns null if a match exists with no account value.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function getQBODepositToAccountId(Payment $payment, AccountingSyncProfile $syncProfile): ?string
    {
        if (0 === count($syncProfile->payment_accounts)) {
            return null;
        }

        $route = PaymentRoute::fromPayment($payment);
        $router = new PaymentAccountMatcher($syncProfile->payment_accounts);
        $rule = $router->match($route);

        if ($accountName = $rule->account) {
            $qboAccount = $this->quickbooksApi->getAccountByName($accountName);
            if (!$qboAccount) {
                throw new SyncException('Could not find QuickBooks Online account: '.$accountName);
            }

            return (string) $qboAccount->Id;
        }

        return null;
    }

    /**
     * Formats a payment method to one suitable for QBO.
     */
    private function formatPaymentMethodName(string $method): string
    {
        return match ($method) {
            PaymentMethod::CASH => 'Cash',
            PaymentMethod::ACH => 'ACH',
            PaymentMethod::PAYPAL => 'PayPal',
            PaymentMethod::WIRE_TRANSFER => 'Wire Transfer',
            PaymentMethod::CREDIT_CARD => 'Credit Card',
            PaymentMethod::CHECK => 'Check',
            PaymentMethod::BALANCE => 'Balance',
            PaymentMethod::DIRECT_DEBIT => 'Direct Debit',
            default => 'Other',
        };
    }
}
