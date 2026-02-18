<?php

namespace App\Integrations\Xero\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingConvenienceFeeMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingTransactionMapping;
use App\Integrations\AccountingSync\ValueObjects\PaymentRoute;
use App\Integrations\AccountingSync\Writers\AbstractPaymentWriter;
use App\Integrations\AccountingSync\WriteSync\PaymentAccountMatcher;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;
use Carbon\CarbonImmutable;
use App\Core\Orm\Model;

class XeroPaymentWriter extends AbstractPaymentWriter
{
    public function __construct(private XeroApi $xeroApi, private XeroCustomerWriter $customerWriter, private XeroCreditNoteWriter $creditNoteWriter, private XeroInvoiceWriter $invoiceWriter)
    {
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performCreate(Payment $payment, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->xeroApi->setAccount($account);

        try {
            // Ensure documents exist on Xero
            $documentsMap = $this->processDocuments($payment, $syncProfile);

            // Create a batch payment or payment on Xero.
            // Payments with a currency different from the base
            // currency require special handling because Xero
            // batch payments do not support multi-currency.
            if ($payment->currency == $payment->tenant()->currency) {
                $this->createXeroBatchPayment($payment, $documentsMap, $syncProfile);
            } else {
                $this->createXeroPayment($payment, $documentsMap, $syncProfile);
            }

            // Sync credit note applications separately
            $this->createCreditNoteApplications($payment, $documentsMap, $syncProfile);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performUpdate(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void
    {
        $this->xeroApi->setAccount($account);

        try {
            // Ensure documents exist on Xero
            $documentsMap = $this->processDocuments($payment, $syncProfile);

            // Update the batch payment or payment on Xero.
            // Payments with a currency different from the base
            // currency require special handling because Xero
            // batch payments do not support multi-currency.
            if ($payment->currency == $payment->tenant()->currency) {
                $this->updateXeroBatchPayment($payment, $documentsMap, $paymentMapping, $syncProfile);
            } else {
                $this->updateXeroPayment($payment, $documentsMap, $paymentMapping, $syncProfile);
            }

            // Sync credit note applications separately
            $this->createCreditNoteApplications($payment, $documentsMap, $syncProfile);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performVoid(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void
    {
        $this->xeroApi->setAccount($account);

        try {
            // Delete the batch payment or payment on Xero.
            // Payments with a currency different from the base
            // currency require special handling because Xero
            // batch payments do not support multi-currency.
            if ($payment->currency == $payment->tenant()->currency) {
                $this->deleteXeroBatchPayment($paymentMapping->accounting_id);
            } else {
                $this->deleteRelatedXeroPayments($payment);
            }

            // void convenience fee invoice if exists
            $feeMapping = AccountingConvenienceFeeMapping::findForPayment($payment, $syncProfile->getIntegrationType());
            if ($feeMapping instanceof AccountingConvenienceFeeMapping) {
                $this->invoiceWriter->voidXeroInvoice($feeMapping->accounting_id);
            }
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * Iterates through documents attached to payment object
     * to determine if they exist on Xero. Documents that do
     * not exist on Xero are created using their corresponding
     * writer class given the sync profile allows writing
     * of that type.
     *
     * @throws IntegrationApiException|SyncException
     *
     * @return array mapping document ids to their corresponding Xero document id
     */
    public function processDocuments(Payment $payment, XeroSyncProfile $syncProfile): array
    {
        $map = [
            'invoice' => [],
            'credit_note' => [],
        ];

        foreach ($payment->applied_to as $split) {
            if (!in_array($split['type'], [PaymentItemType::Invoice->value, PaymentItemType::CreditNote->value, PaymentItemType::ConvenienceFee->value])) {
                continue;
            }

            if (PaymentItemType::CreditNote->value == $split['type'] && PaymentItemType::Invoice->value != ($split['document_type'] ?? '')) {
                continue;
            }

            // Process invoices by finding mapping or creating if missing
            if ($xeroId = $this->mapInvoice($split['invoice'] ?? 0, $syncProfile)) {
                $map['invoice'][$split['invoice']] = $xeroId;
            }

            // Process credit notes by finding mapping or creating if missing
            if ($xeroId = $this->mapCreditNote($split['credit_note'] ?? 0, $syncProfile)) {
                $map['credit_note'][$split['credit_note']] = $xeroId;
            }
        }

        return $map;
    }

    /**
     * @throws SyncException|IntegrationApiException
     */
    private function mapInvoice(int $invoiceId, XeroSyncProfile $syncProfile): ?string
    {
        if (!$invoiceId) {
            return null;
        }

        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            return null;
        }

        if ($invoiceMapping = AccountingInvoiceMapping::findForInvoice($invoice, $syncProfile->getIntegrationType())) {
            return $invoiceMapping->accounting_id;
        }

        // find or create the xero customer
        $customer = $invoice->customer();
        if ($customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType())) {
            $xeroCustomerId = $customerMapping->accounting_id;
        } else {
            $xeroCustomer = $this->customerWriter->createXeroCustomer($customer, $syncProfile);
            $xeroCustomerId = $xeroCustomer->ContactID;
        }

        // create the invoice
        $xeroInvoice = $this->invoiceWriter->createXeroInvoice($invoice, $xeroCustomerId, $syncProfile);

        return $xeroInvoice->InvoiceID;
    }

    /**
     * @throws SyncException|IntegrationApiException
     */
    private function mapCreditNote(int $creditNoteId, XeroSyncProfile $syncProfile): ?string
    {
        if (!$creditNoteId) {
            return null;
        }

        $creditNote = CreditNote::find($creditNoteId);
        if (!$creditNote) {
            return null;
        }

        if ($creditNoteMapping = $this->creditNoteWriter->getCreditNoteMapping($creditNote, $syncProfile->getIntegrationType())) {
            return $creditNoteMapping->accounting_id;
        }

        // find or create the xero customer
        $customer = $creditNote->customer();
        if ($customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType())) {
            $xeroCustomerId = $customerMapping->accounting_id;
        } else {
            $xeroCustomer = $this->customerWriter->createXeroCustomer($customer, $syncProfile);
            $xeroCustomerId = $xeroCustomer->ContactID;
        }

        // create the credit note
        $xeroCreditNote = $this->creditNoteWriter->createXeroCreditNote($creditNote, $xeroCustomerId, $syncProfile);

        return $xeroCreditNote->CreditNoteID;
    }

    /**
     * @throws IntegrationApiException|SyncException
     */
    private function createXeroBatchPayment(Payment $payment, array $documentsMap, XeroSyncProfile $syncProfile): void
    {
        if ($request = $this->buildCreateBatchPaymentRequest($payment, $documentsMap, $syncProfile)) {
            $xeroPayment = $this->xeroApi->create('BatchPayments', $request);
            $this->savePaymentMapping($payment, $syncProfile->getIntegrationType(), $xeroPayment->BatchPaymentID);
        }
    }

    /**
     * @throws IntegrationApiException|SyncException
     */
    private function updateXeroBatchPayment(Payment $payment, array $documentsMap, AccountingPaymentMapping $paymentMapping, XeroSyncProfile $syncProfile): void
    {
        if ($request = $this->buildCreateBatchPaymentRequest($payment, $documentsMap, $syncProfile)) {
            // Updates are not supported so we delete and re-create
            $this->deleteXeroBatchPayment($paymentMapping->accounting_id);
            $xeroPayment = $this->xeroApi->create('BatchPayments', $request);
            $paymentMapping->accounting_id = $xeroPayment->BatchPaymentID;
            $paymentMapping->saveOrFail();
        }
    }

    /**
     * @throws IntegrationApiException
     */
    private function deleteXeroBatchPayment(string $xeroId): void
    {
        $this->xeroApi->createOrUpdate('BatchPayments', [
            'BatchPaymentID' => $xeroId,
            'Status' => 'DELETED',
        ]);
    }

    /**
     * @throws IntegrationApiException|SyncException
     */
    private function createXeroPayment(Payment $payment, array $documentsMap, XeroSyncProfile $syncProfile): void
    {
        // Create all payments at once
        if ($request = $this->buildCreatePaymentRequest($payment, $documentsMap, $syncProfile)) {
            $xeroPayment = $this->xeroApi->create('Payments', $request);

            // Save the payment mapping
            // WARNING: This only references the first payment if multiple were created.
            $this->savePaymentMapping($payment, $syncProfile->getIntegrationType(), $xeroPayment->PaymentID);
        }
    }

    /**
     * @throws IntegrationApiException|SyncException
     */
    private function updateXeroPayment(Payment $payment, array $documentsMap, AccountingPaymentMapping $paymentMapping, XeroSyncProfile $syncProfile): void
    {
        // Look up and delete all payments on Xero associated with this payment.
        $this->deleteRelatedXeroPayments($payment);

        // Recreate the payment
        if ($request = $this->buildCreatePaymentRequest($payment, $documentsMap, $syncProfile)) {
            $xeroPayment = $this->xeroApi->create('Payments', $request);

            // Save the payment mapping
            // WARNING: This only references the first payment if multiple were created.
            $paymentMapping->accounting_id = $xeroPayment->PaymentID;
            $paymentMapping->saveOrFail();
        }
    }

    /**
     * @throws IntegrationApiException
     */
    private function deleteRelatedXeroPayments(Payment $payment): void
    {
        $xeroPayments = $this->xeroApi->getMany('Payments', [
            'where' => 'Reference=="'.$payment->id().'"',
        ]);

        foreach ($xeroPayments as $xeroPayment) {
            $this->deleteXeroPayment($xeroPayment->PaymentID);
        }
    }

    /**
     * @throws IntegrationApiException
     */
    private function deleteXeroPayment(string $xeroId): void
    {
        $this->xeroApi->createOrUpdate('Payments', [
            'PaymentID' => $xeroId,
            'Status' => 'DELETED',
        ]);
    }

    /**
     * Builds request to create a new batch payment.
     *
     * @throws SyncException
     */
    public function buildCreateBatchPaymentRequest(Payment $payment, array $documentsMap, XeroSyncProfile $syncProfile): ?array
    {
        $request = [
            'Date' => CarbonImmutable::createFromTimestamp($payment->date)->toDateString(),
            'Reference' => $payment->id(),
            'Account' => [
                'AccountID' => $this->getDepositToAccountId($payment, $syncProfile),
            ],
            'Payments' => [],
        ];

        foreach ($payment->applied_to as $split) {
            if (PaymentItemType::Invoice->value == $split['type']) {
                $request['Payments'][] = [
                    'Invoice' => [
                        'InvoiceID' => $documentsMap['invoice'][$split['invoice']],
                    ],
                    'Amount' => $split['amount'],
                ];
            } elseif (PaymentItemType::ConvenienceFee->value === $split['type']) {
                $amount = Money::fromDecimal($payment->currency, $split['amount']);
                $xeroFeeInvoiceId = $this->createConvenienceFeeInvoice($payment, $amount, $syncProfile);
                $request['Payments'][] = [
                    'Amount' => $split['amount'],
                    'Invoice' => [
                        'InvoiceID' => $xeroFeeInvoiceId,
                    ],
                ];
            }
        }

        if (0 == count($request['Payments'])) {
            return null;
        }

        return $request;
    }

    /**
     * Builds request to create one or more new payments.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function buildCreatePaymentRequest(Payment $payment, array $documentsMap, XeroSyncProfile $syncProfile): ?array
    {
        $request = [
            'Payments' => [],
        ];

        foreach ($payment->applied_to as $split) {
            if (!in_array($split['type'], [PaymentItemType::Invoice->value, PaymentItemType::ConvenienceFee->value])) {
                continue;
            }

            if (PaymentItemType::ConvenienceFee->value === $split['type']) {
                $amount = Money::fromDecimal($payment->currency, $split['amount']);
                $xeroInvoiceId = $this->createConvenienceFeeInvoice($payment, $amount, $syncProfile);
            } else {
                $xeroInvoiceId = $documentsMap['invoice'][$split['invoice']];
            }

            $request['Payments'][] = [
                'Date' => CarbonImmutable::createFromTimestamp($payment->date)->toDateString(),
                'Reference' => $payment->id(),
                'Account' => [
                    'AccountID' => $this->getDepositToAccountId($payment, $syncProfile),
                ],
                'Invoice' => [
                    'InvoiceID' => $xeroInvoiceId,
                ],
                'Amount' => $split['amount'],
            ];
        }

        if (0 == count($request['Payments'])) {
            return null;
        }

        return $request;
    }

    /**
     * Finds or creates an invoice on Xero which represents a convenience fee.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function createConvenienceFeeInvoice(Payment $payment, Money $amount, XeroSyncProfile $syncProfile): string
    {
        $feeMapping = AccountingConvenienceFeeMapping::findForPayment($payment, $syncProfile->getIntegrationType());
        if ($feeMapping instanceof AccountingConvenienceFeeMapping) {
            return $feeMapping->accounting_id;
        }

        // find or create the xero customer
        /** @var Customer $customer */
        $customer = $payment->customer();
        if ($customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType())) {
            $xeroCustomerId = $customerMapping->accounting_id;
        } else {
            $xeroCustomer = $this->customerWriter->createXeroCustomer($customer, $syncProfile);
            $xeroCustomerId = $xeroCustomer->ContactID;
        }

        // create fee invoice and save mapping
        $feeInvoice = $this->buildConvenienceFeeInvoice($payment, $amount);
        $xeroFeeInvoice = $this->invoiceWriter->createXeroInvoice($feeInvoice, $xeroCustomerId, $syncProfile, false);
        $this->saveConvenienceFeeMapping($payment, $syncProfile->getIntegrationType(), $xeroFeeInvoice->InvoiceID);

        return $xeroFeeInvoice->InvoiceID;
    }

    /**
     * Determines the correct account which a payment should be deposited
     * to in Xero by matching the payment method and payment type
     * of the sync profiles payment account to that of the payment.
     *
     * @throws SyncException
     */
    private function getDepositToAccountId(Payment $payment, XeroSyncProfile $syncProfile): string
    {
        if (0 === count($syncProfile->payment_accounts)) {
            throw new SyncException('No payment deposit accounts are configured.');
        }

        $route = PaymentRoute::fromPayment($payment);
        $router = new PaymentAccountMatcher($syncProfile->payment_accounts);
        $rule = $router->match($route);

        $account = $rule->account;
        if (!$account) {
            // it's possible that the account is an empty string
            throw new SyncException('No payment deposit accounts match the payment\'s method and/or currency.');
        }

        return $account;
    }

    /**
     * @throws IntegrationApiException
     */
    private function createCreditNoteApplications(Payment $payment, array $documentsMap, XeroSyncProfile $syncProfile): void
    {
        // Xero does not allow credit note allocations to
        // be modified or deleted through the API. This
        // function can only create new allocations.

        foreach ($payment->applied_to as $split) {
            if (PaymentItemType::CreditNote->value != $split['type'] || PaymentItemType::Invoice->value != ($split['document_type'] ?? '')) {
                continue;
            }

            // check if already mapped
            if (AccountingTransactionMapping::find($split['id'])) {
                continue;
            }

            $request = [
                'Amount' => $split['amount'],
                'Invoice' => [
                    'InvoiceID' => $documentsMap['invoice'][$split['invoice']],
                ],
            ];
            $xeroCreditNoteId = $documentsMap['credit_note'][$split['credit_note']];
            $xeroAllocation = $this->xeroApi->createAllocation($xeroCreditNoteId, $request);

            // create mapping
            // Allocations do not have an ID so we have to make one that is unique
            $accountingId = md5($xeroCreditNoteId.'/'.$xeroAllocation->Invoice->InvoiceID.'/'.$xeroAllocation->Date);
            $transaction = new Transaction(['id' => $split['id']]);
            $this->saveTransactionMapping($transaction, $syncProfile->getIntegrationType(), $accountingId);
        }
    }
}
