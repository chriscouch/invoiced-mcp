<?php

namespace App\EntryPoint\QueueJob;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\PaymentProcessing\Exceptions\AutoPayException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Operations\AutoPay;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;

class AutoPayJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const BATCH_SIZE = 500;

    public function __construct(private AutoPay $autoPay, private LockFactory $lockFactory)
    {
    }

    public function perform(): void
    {
        // only proceed if we can obtain the lock
        $lock = $this->lockFactory->createLock('autopay:'.$this->args['tenant_id'], 1800);
        if (!$lock->acquire()) {
            return;
        }

        foreach ($this->getInvoices() as $invoice) {
            try {
                $this->autoPay->collect($invoice);
            } catch (AutoPayException $e) {
                $hasCharge = $e->getPrevious() instanceof ChargeException;
                if (!$hasCharge && AutoPayException::EXPECTED_FAILURE_CODE !== $e->getCode()) {
                    $this->logger->error('AutoPay(Charge) Exception occurred', ['exception' => $e]);
                }
                // do nothing
                // keep on processing
            }
        }
    }

    /**
     * Gets the invoices that need to be collected.
     *
     * @return Invoice[]
     */
    private function getInvoices(): array
    {
        return Invoice::where('Invoices.autopay', true)
            ->where('Invoices.closed', false)
            ->where('Invoices.paid', false)
            ->where('Invoices.draft', false)
            ->where('Invoices.voided', false)
            ->where('Invoices.next_payment_attempt', time(), '<=')
            ->where('Invoices.next_payment_attempt IS NOT NULL')
            ->where('Invoices.status', InvoiceStatus::Pending->value, '<>')
            ->first(self::BATCH_SIZE);
    }
}
