<?php

namespace App\PaymentPlans\Libs;

use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanApproval;

class ApprovePaymentPlan
{
    public function __construct(private NotificationSpool $notificationSpool, private CustomerPortalEvents $events)
    {
    }

    /**
     * Approves this payment plan.
     */
    public function approve(PaymentPlan $paymentPlan, string $ip, string $userAgent): PaymentPlanApproval
    {
        $approval = new PaymentPlanApproval();
        $approval->payment_plan_id = (int) $paymentPlan->id();
        $approval->ip = $ip;
        $approval->user_agent = $userAgent;
        $approval->saveOrFail();

        $paymentPlan->approval_id = (int) $approval->id();
        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        $paymentPlan->saveOrFail();

        // schedule the next payment attempt by triggering
        // a status update
        $invoice = $paymentPlan->invoice();
        $invoice->updateStatus();

        // send a notification
        if ($invoice->customer) {
            $this->notificationSpool->spool(NotificationEventType::PaymentPlanApproved, $paymentPlan->tenant_id, $paymentPlan->id, $invoice->customer);
        }

        // track the event
        $this->events->track($invoice->customer(), CustomerPortalEvent::ApprovePaymentPlan);

        return $approval;
    }
}
