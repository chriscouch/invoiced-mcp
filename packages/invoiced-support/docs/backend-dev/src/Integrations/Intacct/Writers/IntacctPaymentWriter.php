<?php

namespace App\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\CurrencyConverter;
use App\Core\I18n\ValueObjects\Money;
use App\ActivityLog\ValueObjects\IntacctWriteFailureEvent;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\PaymentRoute;
use App\Integrations\AccountingSync\Writers\AbstractPaymentWriter;
use App\Integrations\AccountingSync\WriteSync\PaymentAccountMatcher;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Libs\IntacctMapper;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Traits\IntacctEntityWriterTrait;
use Carbon\Carbon;
use Intacct\Functions\AccountsReceivable\AbstractArPayment;
use Intacct\Functions\AccountsReceivable\ArPaymentApply;
use Intacct\Functions\AccountsReceivable\ArPaymentCreate;
use Intacct\Functions\AccountsReceivable\ArPaymentItem;
use Intacct\Functions\AccountsReceivable\ArPaymentReverse;
use Intacct\Functions\AccountsReceivable\InvoiceCreate;
use Intacct\Functions\AccountsReceivable\InvoiceLineCreate;
use Intacct\Functions\OrderEntry\OrderEntryTransactionCreate;
use App\Core\Orm\Model;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class IntacctPaymentWriter extends AbstractPaymentWriter implements LoggerAwareInterface
{
    use IntacctEntityWriterTrait, LoggerAwareTrait;

    public function __construct(
        private IntacctApi $intacctApi,
        private CurrencyConverter $currencyConverter,
        private EventDispatcherInterface $dispatcher,
        private IntacctCustomerWriter $customerWriter,
        private IntacctArInvoiceWriter $arInvoiceWriter,
        private IntacctOrderEntryInvoiceWriter $oeInvoiceWriter,
    ) {
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performCreate(Payment $payment, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->intacctApi->setAccount($account);

        try {
            // look for customer, create if it doesn't exist
            $customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType());
            if (!$customerMapping) {
                $this->customerWriter->createIntacctCustomer($customer, $syncProfile, $account);
            }

            // Build a list of requests to perform. A payment sometimes requires multiple
            // requests to create. However, we only support mapping a single record to a payment.
            // Therefore, if multiple records are created by the payment then there will be a
            // discrepancy if the payment is voided. Only the first created record will be voided
            // in that case.
            $paymentRequests = $this->buildCreateRequests($customer, $payment, $syncProfile, $account);
            $savedMapping = false;
            foreach ($paymentRequests as $paymentRequest) {
                $createdId = $this->createObjectWithEntityHandling($paymentRequest, $account, $syncProfile, $payment);
                if (!$savedMapping) {
                    $this->savePaymentMapping($payment, $syncProfile->getIntegrationType(), $createdId);
                    $savedMapping = true;
                }
            }
        } catch (IntegrationApiException $e) {
            if ($e instanceof IntegrationApiException) {
                $this->dispatcher->dispatch(new IntacctWriteFailureEvent($this->intacctApi->getAccount(), $e));
            }

            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performUpdate(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void
    {
        // Modifying payments is not supported by Intacct
        // In order to modify we would have to reverse and re-create
        // Writing updates is not supported
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performVoid(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void
    {
        $this->intacctApi->setAccount($account);

        // WARNING: Only a single Intacct record can be mapped to a payment. If a payment resulted in
        // multiple Intacct records then voiding the payment will only reverse the first created record.
        try {
            if ($reversePaymentRequest = $this->reversePaymentRequest($payment)) {
                $this->createObjectWithEntityHandling($reversePaymentRequest, $account, $syncProfile, $payment);
            }
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    //
    // Helpers
    //

    /**
     * Builds the A/R payment function to post the payment
     * to Intacct.
     *
     * @throws IntegrationApiException
     * @throws SyncException
     *
     * @return AbstractArPayment[]
     */
    private function buildCreateRequests(Customer $customer,
                                         Payment $payment,
                                         IntacctSyncProfile $syncProfile,
                                         IntacctAccount $intacctAccount): array
    {
        // If there is a payment created then we want this to go first
        // because this is the ID we want to use for the mapping.
        $requests = [];
        if ($request = $this->newPaymentRequest($customer, $payment, $syncProfile, $intacctAccount)) {
            $requests[] = $request;
        }

        // Credit note applications require an A/R payment apply request
        // instead of a create A/R payment request. This will create a separate
        // A/R apply payment request for each credit note line item.
        foreach ($payment->applied_to as $lineItem) {
            if (PaymentItemType::CreditNote->value == $lineItem['type']) {
                if ($request = $this->applyPaymentRequest($payment, $lineItem)) {
                    $requests[] = $request;
                }
            }
        }

        return $requests;
    }

    /**
     * Builds a create A/R payment request.
     *
     * @throws IntegrationApiException
     * @throws SyncException
     */
    private function newPaymentRequest(Customer $customer,
                                       Payment $payment,
                                       IntacctSyncProfile $syncProfile,
                                       IntacctAccount $intacctAccount): ?ArPaymentCreate
    {
        $intacctPayment = new ArPaymentCreate();
        $intacctPayment->setReceivedDate(Carbon::createFromTimestamp($payment->date));

        // set the customer
        // we need to supply the customer ID, not the record number
        if (IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO == $syncProfile->customer_import_type) {
            $metadata = $customer->metadata;
            $customerNumber = property_exists($metadata, 'intacct_customer_number') ? $metadata->intacct_customer_number : null;
            if (!$customerNumber) {
                throw new SyncException('Billing contact is missing customer number.');
            }
        } else {
            $customerNumber = $customer->number;
        }
        $intacctPayment->setCustomerId($customerNumber);

        // set the payment amount
        $paymentAmount = $payment->getAmount();
        $intacctPayment->setTransactionPaymentAmount($paymentAmount->toDecimal());

        // multi-currency
        $company = $payment->tenant();
        $isForeignCurrency = false;
        if ($company->features->has('multi_currency')) {
            $intacctPayment->setBaseCurrency(strtoupper($company->currency));
            $intacctPayment->setTransactionCurrency(strtoupper($payment->currency));
            $intacctPayment->setExchangeRateType('Intacct Daily Rate');
            $isForeignCurrency = $payment->currency != $company->currency;

            // Get the translated amount
            $translatedAmount = $this->currencyConverter->convert($paymentAmount, $intacctPayment->getBaseCurrency());
            $intacctPayment->setBasePaymentAmount($translatedAmount->toDecimal());
        }

        // set the payment method
        $intacctMapper = new IntacctMapper();
        $intacctPayment->setPaymentMethod($intacctMapper->getPaymentMethodToIntacct($payment->method, $isForeignCurrency));
        $intacctPayment->setReferenceNumber($this->getReferenceNumber($payment));

        // build the payment applications
        $totalApplied = Money::fromDecimal($payment->currency, 0);
        $paymentItems = [];
        foreach ($payment->applied_to as $lineItem) {
            if ($paymentItem = $this->buildPaymentItem($customer, $payment, $lineItem, $totalApplied, $syncProfile)) {
                $paymentItems[] = $paymentItem;
            }
        }

        if (0 == count($paymentItems)) {
            return null;
        }

        $intacctPayment->setApplyToTransactions($paymentItems);

        // check for overpayment and set the overpayment dimensions
        if ($totalApplied->lessThan($paymentAmount)) {
            if ($overpaymentLocationId = $syncProfile->overpayment_location_id) {
                $intacctPayment->setOverpaymentLocationId($overpaymentLocationId);
            }

            if ($overpaymentDepartmentId = $syncProfile->overpayment_department_id) {
                $intacctPayment->setOverpaymentDepartmentId($overpaymentDepartmentId);
            }
        }

        // determine the account to add the payment to
        $route = PaymentRoute::fromPayment($payment);
        if ($intacctAccount->sync_all_entities) {
            $entityId = $this->getIntacctEntity($payment);
            $router = new EntityAwarePaymentAccountMatcher($syncProfile->payment_accounts, $entityId);
        } else {
            $router = new PaymentAccountMatcher($syncProfile->payment_accounts);
        }
        $result = $router->match($route);

        if ($result->isUndepositedFunds) {
            $intacctPayment->setUndepositedFundsGlAccountNo($result->account);
        } else {
            $intacctPayment->setBankAccountId($result->account);
        }

        return $intacctPayment;
    }

    /**
     * @throws IntegrationApiException
     */
    private function buildPaymentItem(Customer $customer, Payment $payment, array $lineItem, Money &$totalApplied, IntacctSyncProfile $syncProfile): ?ArPaymentItem
    {
        if (PaymentItemType::Invoice->value == $lineItem['type']) {
            return $this->buildInvoicePaymentItem($payment, $lineItem, $totalApplied);
        }

        if (PaymentItemType::ConvenienceFee->value == $lineItem['type']) {
            return $this->buildConvenienceFeePaymentItem($customer, $payment, $lineItem, $syncProfile);
        }

        return null;
    }

    private function buildInvoicePaymentItem(Payment $payment, array $lineItem, Money &$totalApplied): ?ArPaymentItem
    {
        $totalApplied = $totalApplied->add(Money::fromDecimal($payment->currency, $lineItem['amount']));

        $mapping = $this->getInvoiceMapping($lineItem['invoice']);
        if (!$mapping) {
            return null;
        }

        $paymentItem = new ArPaymentItem();
        $paymentItem->setAmountToApply($lineItem['amount']);
        $paymentItem->setApplyToRecordId($mapping->accounting_id);

        return $paymentItem;
    }

    private function getInvoiceMapping(int $invoiceId): ?AccountingInvoiceMapping
    {
        $mapping = AccountingInvoiceMapping::find($invoiceId);
        if ($mapping) {
            return $mapping;
        }

        $invoice = Invoice::findOrFail($invoiceId);
        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = $invoice;

        return $this->lookupMapping($mapping, $invoice); /* @phpstan-ignore-line */
    }

    private function buildConvenienceFeePaymentItem(Customer $customer, Payment $payment, array $lineItem, IntacctSyncProfile $syncProfile): ?ArPaymentItem
    {
        if (!$syncProfile->write_convenience_fees) {
            return null;
        }

        if (!$syncProfile->convenience_fee_account) {
            throw new SyncException('Convenience fee account is not set up.');
        }

        // create invoice for convenience fee
        $amount = $lineItem['amount'];
        $intacctInvoiceId = $this->createConvenienceFeeInvoice($payment, $customer, $amount, $syncProfile);

        $paymentItem = new ArPaymentItem();
        $paymentItem->setAmountToApply($amount);
        $paymentItem->setApplyToRecordId($intacctInvoiceId);

        return $paymentItem;
    }

    /**
     * Builds a reverse A/R payment request.
     */
    private function reversePaymentRequest(Payment $payment): ?ArPaymentReverse
    {
        $intacctReversal = new ArPaymentReverse();
        $intacctReversal->setReverseDate(Carbon::createFromTimestamp((int) $payment->date_voided));

        // look up the Intacct mapping from the original payment
        $paymentMapping = AccountingPaymentMapping::find($payment->id());
        if (!$paymentMapping) {
            return null;
        }

        $intacctReversal->setRecordNo($paymentMapping->accounting_id);

        return $intacctReversal;
    }

    /**
     * Builds an A/R Adjustment Apply (ArPaymentApply) request.
     */
    private function applyPaymentRequest(Payment $payment, array $lineItem): ?ArPaymentApply
    {
        // don't create request if credit note doesn't exist within Intacct
        $creditNoteMapping = $this->getCreditNoteMapping($lineItem['credit_note']);
        if (!$creditNoteMapping) {
            return null;
        }

        // build the payment item
        $totalApplied = Money::fromDecimal($payment->currency, 0);
        $paymentItem = $this->buildAdjustmentPaymentItem($payment, $lineItem, $totalApplied);
        if (!$paymentItem) {
            return null;
        }

        // build ArPaymentApply request
        $intacctApply = new ArPaymentApply();
        $intacctApply->setRecordNo($creditNoteMapping->accounting_id);
        $intacctApply->setReceivedDate(Carbon::createFromTimestamp($payment->date));
        $intacctApply->setApplyToTransactions([$paymentItem]);

        return $intacctApply;
    }

    private function buildAdjustmentPaymentItem(Payment $payment, array $lineItem, Money &$totalApplied): ?ArPaymentItem
    {
        // other payment application types are not supported
        // when a credit note is applied
        if (PaymentItemType::CreditNote->value == $lineItem['type']) {
            $totalApplied = $totalApplied->add(Money::fromDecimal($payment->currency, $lineItem['amount']));

            // non-invoice credit note applications not supported
            if ('invoice' != ($lineItem['document_type'] ?? '')) {
                return null;
            }

            $invoiceMapping = $this->getInvoiceMapping($lineItem['invoice']);
            if (!$invoiceMapping) {
                return null;
            }

            $paymentItem = new ArPaymentItem();
            $paymentItem->setApplyToRecordId($invoiceMapping->accounting_id);
            $paymentItem->setAmountToApply($lineItem['amount']); // Intacct requires positive value.

            return $paymentItem;
        }

        return null;
    }

    private function getCreditNoteMapping(int $creditNoteId): ?AccountingCreditNoteMapping
    {
        $creditNoteMapping = AccountingCreditNoteMapping::find($creditNoteId);
        if ($creditNoteMapping) {
            return $creditNoteMapping;
        }

        $creditNote = CreditNote::findOrFail($creditNoteId);
        $mapping = new AccountingCreditNoteMapping();
        $mapping->credit_note = $creditNote;

        return $this->lookupMapping($mapping, $creditNote); /* @phpstan-ignore-line */
    }

    private function lookupMapping(AbstractMapping $mapping, ReceivableDocument $document): ?AbstractMapping
    {
        // If a mapping is not found, then attempt to lookup the transaction
        // on Intacct through Order Entry. This requires that we know both
        // the document type and the credit note # to be able to locate the transaction.
        $metadata = $document->metadata;
        $documentType = $metadata->intacct_document_type ?? null;
        if (!$documentType) {
            return null;
        }

        try {
            $mapping->accounting_id = $this->intacctApi->getOrderEntryTransactionPrRecordKey($documentType, $document->number);
        } catch (IntegrationApiException) {
            // Intacct API errors are ignored if the document cannot be found
            return null;
        }

        // save the mapping with the accounting system as the source
        $mapping->setIntegration(IntegrationType::Intacct);
        $mapping->source = AccountingCreditNoteMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->save();

        return $mapping;
    }

    /**
     * Generates the reference number value.
     */
    private function getReferenceNumber(Payment $payment): string
    {
        $memo = [];
        if ($reference = $payment->reference) {
            $memo[] = 'Reference: '.$reference;
        }

        if ($charge = $payment->charge) {
            $memo[] = 'Gateway: '.$charge->gateway;

            if ($source = $charge->payment_source) {
                $memo[] = $source->toString(true);
            }
        }

        $memo[] = 'Invoiced ID: '.$payment->id;

        return implode(', ', $memo);
    }

    /**
     * Creates an invoice on Intacct to represent the convenience fee.
     *
     * @throws IntegrationApiException
     * @throws SyncException
     */
    private function createConvenienceFeeInvoice(Payment $payment, Customer $customer, float $amount, IntacctSyncProfile $syncProfile): string
    {
        $feeInvoice = new Invoice();
        $feeInvoice->setCustomer($customer);
        $feeInvoice->name = 'Convenience Fee';
        $feeInvoice->number = 'CF-'.$payment->id();
        $feeInvoice->currency = $payment->currency;
        $feeInvoice->date = time();
        $item = new LineItem();
        $item->name = 'Convenience Fee';
        $item->amount = $amount;
        $item->quantity = 1;
        $feeInvoice->items = [$item];

        if ($syncProfile->write_to_order_entry) {
            $invoiceRequest = $this->oeInvoiceWriter->buildCreateRequest($feeInvoice, $syncProfile);
        } else {
            $invoiceRequest = $this->arInvoiceWriter->buildCreateRequest($feeInvoice, $syncProfile);
        }

        /** @var InvoiceCreate|OrderEntryTransactionCreate $invoiceRequest */
        // update the G/L account to use the convenience fee account number
        $line = $invoiceRequest->getLines()[0];
        if ($line instanceof InvoiceLineCreate) {
            $line->setGlAccountNumber((string) $syncProfile->convenience_fee_account);
        }

        $intacctId = $this->intacctApi->createObject($invoiceRequest);
        $this->saveConvenienceFeeMapping($payment, $syncProfile->getIntegrationType(), $intacctId);

        return $intacctId;
    }

    private function getIntacctEntity(Invoice|Payment|CreditNote $payment): ?string {
        if (count($payment->applied_to) === 0) {  /* @phpstan-ignore-line */
            return null;
        }

        foreach ($payment->applied_to as $lineItem) {
            // Only process if it's an invoice line
            if (($lineItem['type'] ?? null) === PaymentItemType::Invoice->value && !empty($lineItem['invoice'])) {
                $invoice = Invoice::find($lineItem['invoice']);
                if (!$invoice) {
                    continue;
                }

                if ($invoice->metadata !== null && property_exists($invoice->metadata, 'intacct_entity')) {
                    return $invoice->metadata->intacct_entity;
                }

                return $this->customerWriter->getIntacctEntity($invoice->customer());
            }
        }

        return null;
    }
}
