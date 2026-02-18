<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Utils\DebugContext;
use App\PaymentProcessing\Enums\ChargeApplicationType;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Interfaces\CreditChargeApplicationItemInterface;
use App\PaymentProcessing\Models\InitiatedCharge;
use App\PaymentProcessing\Models\InitiatedChargeDocument;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class InitiatedChargeFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private DebugContext $debugContext)
    {
    }

    /**
     * @throws ChargeException
     */
    public function create(?PaymentSource $source, Customer $customer, MerchantAccount $merchantAccount, ChargeApplication $application, array $parameters = []): InitiatedCharge
    {
        $creditNotes = [];
        $invoices = [];
        $estimates = [];

        foreach ($application->getItems() as $item) {
            $document = $item->getDocument();
            if ($document instanceof Invoice) {
                $invoices[] = $document->id;
            } elseif ($document instanceof CreditNote) {
                $creditNotes[] = $document->id;
            } elseif ($document instanceof Estimate) {
                $estimates[] = $document->id;
            }
        }
        if ($invoices && InitiatedChargeDocument::where('document_id IN ('.implode(',', $invoices).')')
            ->where('document_type', ChargeApplicationType::InvoiceChargeApplicationItem->value)
            ->count() > 0) {
            throw new ChargeException('Duplicate payment attempt detected.', null, 'duplicate_payment');
        }
        if ($creditNotes && InitiatedChargeDocument::where('document_id IN ('.implode(',', $creditNotes).')')
            ->where('document_type', ChargeApplicationType::CreditNoteChargeApplicationItem->value)
            ->count() > 0) {
            throw new ChargeException('Duplicate payment attempt detected.', null, 'duplicate_payment');
        }
        if ($estimates && InitiatedChargeDocument::where('document_id IN ('.implode(',', $estimates).')')
            ->where('document_type', ChargeApplicationType::EstimateChargeApplicationItem->value)
            ->count() > 0) {
            throw new ChargeException('Duplicate payment attempt detected.', null, 'duplicate_payment');
        }

        $money = $application->getPaymentAmount();
        $correlationId = $this->debugContext->getCorrelationId();
        $charge = new InitiatedCharge();
        $charge->correlation_id = $correlationId;
        $charge->gateway = $merchantAccount->gateway;
        $charge->amount = $money->toDecimal();
        $charge->application_source = $application->getPaymentSource()->toString();
        $charge->source_id = $source?->id;
        $charge->currency = $money->currency;
        $charge->customer = $customer;
        $charge->merchant_account_id = $merchantAccount->id;
        $charge->parameters = (object) [
            'receipt_email' => $parameters['receipt_email'] ?? null,
        ];

        if (!$charge->save()) {
            throw new ChargeException('Unable to save initiated charge: '.$charge->getErrors());
        }

        foreach ($application->getItems() as $applicationItem) {
            $document = $applicationItem->getDocument();
            $doc = new InitiatedChargeDocument();
            $doc->initiated_charge = $charge;
            $doc->document_id = $document?->id;
            $doc->document_type = ChargeApplicationType::make($applicationItem)->value;
            $amount = $applicationItem instanceof CreditChargeApplicationItemInterface ? $applicationItem->getCredit() : $applicationItem->getAmount();
            $doc->amount = $amount->toDecimal();
            if (!$doc->save()) {
                throw new ChargeException('Unable to save initiated charge document: '.$doc->getErrors());
            }
        }

        return $charge;
    }
}
