<?php

namespace App\CashApplication\Operations;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\RemittanceAdviceException;
use App\CashApplication\Enums\RemittanceAdviceStatus;
use App\CashApplication\Models\RemittanceAdvice;
use App\CashApplication\Models\RemittanceAdviceLine;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;

class CreateRemittanceAdvice
{
    public function __construct(
        private TenantContext $tenant,
        private PostRemittanceAdvicePayment $postPayment,
    ) {
    }

    /**
     * @throws ModelException
     */
    public function create(array $input): RemittanceAdvice
    {
        // Build the lines
        $company = $this->tenant->get();
        $currency = $input['currency'] ?? $company->currency;
        $totalGrossPaid = Money::zero($currency);
        $totalDiscount = Money::zero($currency);
        $totalNetPaid = Money::zero($currency);
        /** @var RemittanceAdviceLine[] $lines */
        $lines = [];
        foreach ($input['lines'] as $line) {
            $adviceLine = new RemittanceAdviceLine();
            $adviceLine->document_number = $line['document_number'];

            // Determine document type
            $document = $this->getDocumentForNumber($line['document_number']);
            if ($document instanceof Invoice) {
                $adviceLine->document_type = ObjectType::Invoice;
                $adviceLine->invoice = $document;
            }
            if ($document instanceof CreditNote) {
                $adviceLine->document_type = ObjectType::CreditNote;
                $adviceLine->credit_note = $document;
            }

            $adviceLine->description = $line['description'] ?? null;

            // Determine line item values from gross or net, depending on what was provided
            if (isset($line['gross_amount_paid'])) {
                $grossPaid = Money::fromDecimal($currency, $line['gross_amount_paid']);
                $adviceLine->gross_amount_paid = $grossPaid->toDecimal();

                $discount = Money::fromDecimal($currency, $line['discount'] ?? 0);
                $adviceLine->discount = $discount->toDecimal();

                $netPaid = $grossPaid->subtract($discount);
                $adviceLine->net_amount_paid = $netPaid->toDecimal();
            } elseif (isset($line['net_amount_paid'])) {
                $netPaid = Money::fromDecimal($currency, $line['net_amount_paid']);
                $adviceLine->net_amount_paid = $netPaid->toDecimal();

                $discount = Money::fromDecimal($currency, $line['discount'] ?? 0);
                $adviceLine->discount = $discount->toDecimal();

                $grossPaid = $netPaid->add($discount);
                $adviceLine->gross_amount_paid = $grossPaid->toDecimal();
            } else {
                throw new ModelException('Missing amount paid');
            }

            // Check for an exception
            if ($netPaid->isNegative() && !$adviceLine->credit_note) {
                $adviceLine->exception = RemittanceAdviceException::DisputeDetected;
            } elseif ($netPaid->isPositive() && !$adviceLine->invoice) {
                $adviceLine->exception = RemittanceAdviceException::DocumentDoesNotExist;
            }

            $totalGrossPaid = $totalGrossPaid->add($grossPaid);
            $totalDiscount = $totalDiscount->add($discount);
            $totalNetPaid = $totalNetPaid->add($netPaid);

            $lines[] = $adviceLine;
        }

        // Create the remittance advice
        $advice = $this->saveRemittanceAdvice($input, $lines, $totalGrossPaid, $totalDiscount, $totalNetPaid);

        // Auto-post the payment if the remittance advice is clear
        if (RemittanceAdviceStatus::ReadyToPost == $advice->status) {
            $this->postPayment->post($advice);
        }

        return $advice;
    }

    private function getDocumentForNumber(string $number): ?Model
    {
        $invoice = Invoice::where('number', $number)->oneOrNull();
        if ($invoice) {
            return $invoice;
        }

        $creditNote = CreditNote::where('number', $number)->oneOrNull();
        if ($creditNote) {
            return $creditNote;
        }

        return null;
    }

    private function saveRemittanceAdvice(array $input, array $lines, Money $totalGrossPaid, Money $totalDiscount, Money $totalNetPaid): RemittanceAdvice
    {
        $advice = new RemittanceAdvice();
        $advice->customer = $input['customer'] ?? null;
        $advice->payment_date = new CarbonImmutable($input['payment_date']);
        $advice->payment_method = $input['payment_method'] ?? PaymentMethod::OTHER;
        $advice->payment_reference = $input['payment_reference'] ?? '';
        $advice->total_gross_amount_paid = $totalGrossPaid->toDecimal();
        $advice->total_discount = $totalDiscount->toDecimal();
        $advice->total_net_amount_paid = $totalNetPaid->toDecimal();
        $advice->currency = $totalGrossPaid->currency;
        $advice->notes = $input['notes'] ?? null;
        $advice->setLines($lines);
        $advice->status = $advice->determineStatus();

        // Save the remittance advice lines
        foreach ($lines as $adviceLine) {
            $adviceLine->remittance_advice = $advice;
            $adviceLine->saveOrFail();
        }

        return $advice;
    }
}
