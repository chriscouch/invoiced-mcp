<?php

namespace App\EntryPoint\QueueJob;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Libs\InvoiceDeliveryProcessor;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Core\Database\TransactionManager;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use Doctrine\DBAL\Connection;

class ScheduleInvoiceChaseSends extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    private InvoiceDeliveryProcessor $processor;

    public function __construct(private TenantContext $tenant, private Connection $database, TransactionManager $transaction)
    {
        $this->processor = new InvoiceDeliveryProcessor($database, $transaction);
    }

    public function perform(): void
    {
        $this->processor->initialize();

        $deliveries = $this->getInvoiceDeliveries();
        foreach ($deliveries as $delivery) {
            $id = $delivery['id'];
            // the InvoiceDelivery instance is retrieved by id rather than
            // by an ORM iterator query to ensure the most up to date data
            // is used at the time of processing
            $delivery = InvoiceDelivery::where('id', $id)->oneOrNull();
            if (!($delivery instanceof InvoiceDelivery)) {
                continue;
            }
            // we do not process deliveries for pending transactions
            if (InvoiceStatus::Pending->value === $delivery->invoice->status) {
                continue;
            }

            $shouldProcess = !$delivery->invoice->draft;
            $chasingEnabled = $this->tenant->get()->features->has('invoice_chasing') &&
                $this->tenant->get()->features->has('smart_chasing');
            if ($chasingEnabled && $shouldProcess) {
                $this->processor->process($delivery);
            } else {
                // InvoiceDeliveries should be marked as processed even if the invoice should not be chased.
                // Anytime a state change occurs that should enable invoice chasing, the "processed" property
                // should first be set to false. InvoiceDeliveries should never be left in a "processed=0" state
                // because the property is used to determine if this job should be enqueued thus the state
                // will cause redundant processing.
                $delivery->processed = true;
                $delivery->saveOrFail();
            }
        }
    }

    private function getInvoiceDeliveries(): array
    {
        return $this->database->createQueryBuilder()
            ->select('id')
            ->from('InvoiceDeliveries')
            ->where('tenant_id = :tid')
            ->andWhere('processed = FALSE')
            ->setParameter('tid', $this->tenant->get()->id())
            ->fetchAllAssociative();
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'invoice_chase_scheduling:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 300; // 5 minutes
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        // If this job is already running for a tenant then there
        // is no need to retry this one because it is automatically
        // retried every minute.
        return false;
    }
}
