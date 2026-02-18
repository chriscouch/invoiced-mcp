<?php

namespace App\AccountsReceivable\Operations;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\EstimateApproval;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;

class ApproveEstimate
{
    public function __construct(private EventSpool $eventSpool, private NotificationSpool $notificationSpool, private CustomerPortalEvents $customerPortalEvents)
    {
    }

    /**
     * Marks the estimate as approved.
     *
     * @param string $initials approval initials
     */
    public function approve(Estimate $estimate, string $ip, string $userAgent, string $initials, bool $isAutoPay = false): ?EstimateApproval
    {
        if (empty($initials) || $estimate->approved) {
            return null;
        }

        $initials = strtoupper($initials);

        // build the approval
        $approval = new EstimateApproval();
        $approval->estimate_id = (int) $estimate->id();
        $approval->ip = $ip;
        $approval->user_agent = $userAgent;
        $approval->initials = $initials;
        $saved = $approval->save();

        if (!$saved) {
            throw new \Exception('Unable to save estimate approval.');
        }

        // update the estimate
        $estimate->approved = $initials;
        $estimate->approval_id = (int) $approval->id();
        $saved = $estimate->skipClosedCheck()->save();

        if (!$saved) {
            throw new \Exception('Unable to approve estimate.');
        }

        // create an estimate.approved event
        $pendingEvent = new PendingEvent(
            object: $estimate,
            type: EventType::EstimateApproved
        );
        $this->eventSpool->enqueue($pendingEvent);

        // send a notification
        $customer = $estimate->customer();
        $this->notificationSpool->spool(NotificationEventType::EstimateApproved, $estimate->tenant_id, $estimate->id, $customer->id);

        // track the customer portal event
        $this->customerPortalEvents->track($customer, CustomerPortalEvent::ApproveEstimate);
        if ($isAutoPay) {
            $this->customerPortalEvents->track($customer, CustomerPortalEvent::AutoPayEnrollment);
        }

        return $approval;
    }
}
