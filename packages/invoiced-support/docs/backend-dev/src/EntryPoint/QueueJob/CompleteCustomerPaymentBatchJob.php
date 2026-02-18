<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\PaymentProcessing\Models\CustomerPaymentBatch;
use App\PaymentProcessing\Operations\CompleteCustomerPaymentBatch;

class CompleteCustomerPaymentBatchJob extends AbstractResqueJob implements MaxConcurrencyInterface, TenantAwareQueueJobInterface
{
    public function __construct(
        private readonly CompleteCustomerPaymentBatch $processor,
    ) {
    }

    public function perform(): void
    {
        $paymentBatch = CustomerPaymentBatch::findOrFail($this->args['id']);
        $this->processor->complete($paymentBatch);
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'pay_batch_payment:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 1800;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
