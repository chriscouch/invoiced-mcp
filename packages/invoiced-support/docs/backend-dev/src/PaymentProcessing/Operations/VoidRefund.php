<?php

namespace App\PaymentProcessing\Operations;
use App\Core\Orm\Exception\ModelException;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\ValueObjects\RefundValueObject;

class VoidRefund implements StatsdAwareInterface
{
    use StatsdAwareTrait;
    public function __construct(private NotificationSpool $notificationSpool, private CustomerPortalEvents $customerPortalEvents,)
    {
    }
    public function void(Refund $refund): void
    {
    
        if (RefundValueObject::VOIDED == $refund->status) {
            throw new ModelException('This refund has already been voided.');
        }
        
        $refund->status = RefundValueObject::VOIDED;
        $refund->saveOrFail();
        $charge = $refund->charge;
        if ($charge == null) {
            return;
        }
        
        // Reduce the refunded amount since this refund is now voided
        $refundAmount = $refund->getAmount();
        $newRefundedAmount = $charge->getAmountRefunded()->subtract($refundAmount);
        $charge->amount_refunded = max(0, $newRefundedAmount->toDecimal());
        $charge->refunded = $newRefundedAmount->greaterThanOrEqual($charge->getAmount());
        $charge->saveOrFail();
        $this->notificationSpool->spool(NotificationEventType::RefundReversalApplied, $charge->tenant_id, $refund->id, $charge->customer_id);
        if ($charge->customer) {
            $this->customerPortalEvents->track($charge->customer, CustomerPortalEvent::RefundReversalApplied);
        }
        $this->statsd->increment('refunds.reversal');
    }
}
