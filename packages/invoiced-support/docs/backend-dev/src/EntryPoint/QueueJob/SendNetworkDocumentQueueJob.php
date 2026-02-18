<?php

namespace App\EntryPoint\QueueJob;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Operations\CreateVendorPayment;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Network\Command\SendDocument;
use App\Network\Exception\NetworkSendException;
use App\Network\Models\NetworkConnection;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use App\Core\Orm\Exception\ModelException;

class SendNetworkDocumentQueueJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private SendDocument $sendDocument,
        private Connection $database,
        private CreateVendorPayment $createVendorPayment,
        private TenantContext $tenant,
    ) {
    }

    public function perform(): void
    {
        if (isset($this->args['customer'])) {
            $customer = Customer::findOrFail($this->args['customer']);
            $this->sendAllForCustomer($customer);
        } elseif (isset($this->args['invoice'])) {
            $invoice = Invoice::findOrFail($this->args['invoice']);
            $this->sendDocument($invoice);
        } elseif (isset($this->args['credit_note'])) {
            $creditNote = CreditNote::findOrFail($this->args['credit_note']);
            $this->sendDocument($creditNote);
        } elseif (isset($this->args['estimate'])) {
            $estimate = Estimate::findOrFail($this->args['estimate']);
            $this->sendDocument($estimate);
        } else {
            throw new NetworkSendException('Send network document type not provided');
        }
    }

    public function sendAllForCustomer(Customer $customer): void
    {
        $connection = $customer->network_connection;
        if (!$connection) {
            throw new NetworkSendException('Cannot send document because customer is not connected in network');
        }

        // Invoices
        $invoices = Invoice::where('customer', $customer)
            ->where('voided', false)
            ->where('draft', false)
            ->all();
        foreach ($invoices as $invoice) {
            $this->sendDocument($invoice, $connection);
        }

        // Credit Notes
        $creditNotes = CreditNote::where('customer', $customer)
            ->where('voided', false)
            ->where('draft', false)
            ->all();
        foreach ($creditNotes as $creditNote) {
            $this->sendDocument($creditNote, $connection);
        }

        // Estimates
        $estimates = Estimate::where('customer', $customer)
            ->where('voided', false)
            ->where('draft', false)
            ->all();
        foreach ($estimates as $estimate) {
            $this->sendDocument($estimate, $connection);
        }

        // Payments
        $payments = Payment::where('customer', $customer)
            ->where('voided', false)
            ->all();
        foreach ($payments as $payment) {
            $this->saveVendorPayment($payment, $connection);
        }

        $this->database->executeStatement('DELETE FROM NetworkQueuedSends WHERE tenant_id='.$customer->tenant_id.' AND customer_id='.$customer->id);
    }

    private function sendDocument(ReceivableDocument $document, ?NetworkConnection $connection = null): void
    {
        $connection ??= $document->customer()->network_connection;

        try {
            if (!$connection) {
                throw new NetworkSendException('Cannot send document because customer is not connected in network');
            }

            $this->sendDocument->sendFromModel($document->tenant(), null, $connection, $document);
        } catch (NetworkSendException $e) {
            $this->logger->error('Could not send document through network', ['exception' => $e, $document->object => $document->id]);
        }
    }

    private function saveVendorPayment(Payment $payment, NetworkConnection $connection): void
    {
        // Create a new vendor payment
        $charge = $payment->charge;
        $paymentSource = $charge?->payment_source;
        $parameters = [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'date' => CarbonImmutable::createFromTimestamp($payment->date),
            'reference' => $payment->reference,
            'payment_method' => $payment->method,
            'notes' => $paymentSource?->toString(true),
        ];

        // TODO: this does not handle all payment types, such as convenience fees, credit note applications, or other payment item types
        $appliedTo = [];
        foreach ($payment->applied_to as $item) {
            if (PaymentItemType::Invoice->value == $item['type']) {
                $invoice = Invoice::findOrFail($item['invoice']);
                if ($networkDocument = $invoice->network_document) {
                    $appliedTo[] = [
                        'network_document' => $networkDocument,
                        'amount' => $item['amount'],
                    ];
                }
            }
        }

        $this->tenant->runAs($connection->customer, function () use ($parameters, $appliedTo, $connection) {
            // Find the associated vendor
            $parameters['vendor'] = Vendor::where('network_connection_id', $connection)->oneOrNull();
            if (!$parameters['vendor']) {
                return;
            }

            try {
                // Convert network document references into bill models
                $appliedTo2 = [];
                foreach ($appliedTo as $item) {
                    $bill = Bill::where('network_document_id', $item['network_document'])->oneOrNull();
                    if ($bill) {
                        $appliedTo2[] = [
                            'bill' => $bill,
                            'amount' => $item['amount'],
                        ];
                    }
                }

                $this->createVendorPayment->create($parameters, $appliedTo2);
            } catch (ModelException $e) {
                // exceptions are ignored here
            }
        });
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'send_network_documents:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 300; // 5 minutes
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
