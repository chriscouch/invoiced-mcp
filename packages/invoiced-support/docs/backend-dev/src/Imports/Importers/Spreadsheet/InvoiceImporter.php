<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Libs\PaymentTermsFactory;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Models\Import;
use App\Imports\Traits\ImportAccountingParametersTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Imports\ValueObjects\ImportResult;
use App\PaymentPlans\Exception\PaymentPlanCalculatorException;
use App\PaymentPlans\Libs\PaymentPlanCalculator;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;

class InvoiceImporter extends ReceivableDocumentImporter
{
    use ImportAccountingParametersTrait;

    private array $paymentPlanConstraints;
    private PaymentPlanCalculator $paymentPlanCalculator;

    protected function getDocumentClass(): string
    {
        return Invoice::class;
    }

    public function run(array $records, array $options, Import $import): ImportResult
    {
        // determine if payment plans are added to imported invoices
        $company = $import->tenant();
        if ($constraints = $company->accounts_receivable_settings->add_payment_plan_on_import) {
            $this->paymentPlanConstraints = (array) $constraints;
            $this->paymentPlanCalculator = new PaymentPlanCalculator($company);
        }

        return parent::run($records, $options, $import);
    }

    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        $record = parent::buildRecord($mapping, $line, $options, $import);

        return $this->buildRecordAccounting($record);
    }

    public function build(array $mapping, array $lines, array $options, Import $import): array
    {
        $result = parent::build($mapping, $lines, $options, $import);
        $company = $import->tenant();

        /* @todo this should be removed, when we will switch to PaymentTerms model instead of string */
        return array_map(function ($record) use ($company) {
            // add an early discount if the customer's payment terms support it
            if (!isset($record['payment_terms']) || !$record['payment_terms']) {
                return $record;
            }
            $terms = PaymentTermsFactory::get($record['payment_terms']);
            // fallback to usd is used, because we dont care really about the currency here
            // its just needed to populate data
            $invoice = InvoiceCalculator::calculate($record['currency'] ?? $company->currency, $record['items'] ?? [], [], []);
            $subtotal = Money::fromDecimal($invoice->currency, $invoice->subtotal);
            $discount = $terms->getEarlyDiscount($subtotal);
            if (!$discount) {
                return $record;
            }
            if (isset($record['discount']) && $record['discount'] && is_numeric($record['discount'])) {
                $record['discounts'] = [
                    [
                        'amount' => $record['discount'],
                    ],
                ];
                unset($record['discount']);
            }
            $record['discounts'][] = [
                'amount' => $discount['amount'],
                'expires' => $discount['expires'],
                'from_payment_terms' => true,
            ];

            return $record;
        }, $result);
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        $result = parent::createRecord($record);

        $invoice = $result->getModel();
        if ($invoice instanceof Invoice) {
            // add any payment plans as needed
            // (most imports do not use this)
            if (isset($this->paymentPlanConstraints)) {
                $this->createPaymentPlan($invoice, $record, $this->paymentPlanConstraints);
            }

            // add invoice delivery
            $this->setInvoiceDelivery($invoice, $record);

            // save accounting mapping
            if ($accountingSystem && $accountingId) {
                $this->saveAccountingMapping($invoice, $accountingSystem, $accountingId);
            }
        }

        return $result;
    }

    /**
     * @param Invoice $existingRecord
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        $result = parent::updateRecord($record, $existingRecord);

        // add/update invoice delivery
        $this->setInvoiceDelivery($existingRecord, $record);

        // save accounting mapping
        if ($accountingSystem && $accountingId) {
            $this->saveAccountingMapping($existingRecord, $accountingSystem, $accountingId);
        }

        return $result;
    }

    /**
     * Attaches a payment plan to an invoice.
     *
     * @throws RecordException when the payment plan cannot be created
     */
    private function createPaymentPlan(Invoice $invoice, array $record, array $constraints): void
    {
        $startDate = $invoice->date;
        if (isset($record['payment_plan_start_date'])) {
            $startDate = $record['payment_plan_start_date'];
        }

        try {
            $schedule = $this->paymentPlanCalculator->build($startDate, $invoice->currency, $invoice->total, $constraints);
        } catch (PaymentPlanCalculatorException $e) {
            throw new RecordException('Could not build payment plan: '.$e->getMessage());
        }

        $paymentPlan = new PaymentPlan();

        // build from request
        $installments = [];
        foreach ($schedule as $params) {
            $installment = new PaymentPlanInstallment();
            foreach ($params as $k => $v) {
                $installment->$k = $v;
            }

            $installments[] = $installment;
        }

        $paymentPlan->installments = $installments;
        if (!$invoice->attachPaymentPlan($paymentPlan, true, true)) {
            throw new RecordException('Could not save installment plan: '.$invoice->getErrors().' Generated schedule: '.json_encode($schedule));
        }
    }

    /**
     * Sets the invoice delivery model properties for an invoice.
     */
    private function setInvoiceDelivery(Invoice $invoice, array $record): void
    {
        if ($invoice->closed) {
            return;
        }

        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id())
            ->oneOrNull();

        // set cadence
        if ($cadenceName = $record['chasing_cadence'] ?? null) {
            $cadence = InvoiceChasingCadence::where('name', (string) $cadenceName)->oneOrNull();
            if ($cadence instanceof InvoiceChasingCadence) {
                // An invoice delivery should only be created if
                // a cadence is being applied.
                if (!($delivery instanceof InvoiceDelivery)) {
                    $delivery = new InvoiceDelivery();
                    $delivery->invoice = $invoice;
                }
                $delivery->applyCadence($cadence);
            }
        }

        if (!($delivery instanceof InvoiceDelivery)) {
            // Delivery did not exist and no cadence was applied.
            // Other settings are not applicable so they can be skipped.
            return;
        }

        // disable chasing
        if (isset($record['chasing_disabled'])) {
            $disabled = $record['chasing_disabled'];
            if (true === $disabled || false === $disabled) {
                $delivery->disabled = $disabled;
            }
        }

        // invoice email contacts
        if (isset($record['invoice_email_contacts'])) {
            if (!$record['invoice_email_contacts']) {
                $delivery->emails = null;
            } else {
                $delivery->emails = (string) $record['invoice_email_contacts'];
            }
        }

        if (!$delivery->save()) {
            throw new RecordException('Could not save chasing settings: '.$delivery->getErrors());
        }
    }
}
