<?php

namespace App\Network\Command;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Network\Enums\DocumentStatus;
use App\Network\Event\DocumentTransitionEvent;
use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkDocumentStatusTransition;
use Carbon\CarbonImmutable;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TransitionDocumentStatus
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Checks if transitioning a document to the given status is allowed.
     */
    public function isTransitionAllowed(DocumentStatus $fromStatus, DocumentStatus $toStatus, bool $isSender): bool
    {
        $allowedTransitions = $isSender ? $this->getAllowedTransitionsSender($fromStatus) : $this->getAllowedTransitionsReceiver($fromStatus);

        return in_array($toStatus, $allowedTransitions);
    }

    /**
     * Transitions a document to the given status.
     *
     * This function does not first check if the transition is allowed.
     */
    public function performTransition(NetworkDocument $document, Company $company, DocumentStatus $toStatus, ?Member $user, ?string $description = null, bool $flush = false): void
    {
        // set the current status on the document
        $document->current_status = $toStatus;

        // add the transition to the status history
        $statusHistory = new NetworkDocumentStatusTransition();
        $statusHistory->status = $toStatus;
        $statusHistory->document = $document;
        $statusHistory->company = $company;
        $statusHistory->member = $user;
        $statusHistory->description = $description;
        $statusHistory->effective_date = CarbonImmutable::now();
        $statusHistory->saveOrFail();

        if ($flush) {
            $document->saveOrFail();
        }

        // emit an event about the transition
        $this->eventDispatcher->dispatch(new DocumentTransitionEvent($document, $statusHistory));
    }

    /**
     * Gets a list of transitions that the sender can perform
     * on a document that is in a given document state.
     */
    private function getAllowedTransitionsSender(DocumentStatus $from): array
    {
        return match ($from) {
            DocumentStatus::PendingApproval => [
                DocumentStatus::PendingApproval,
                DocumentStatus::Paid,
                DocumentStatus::Voided,
            ],
            DocumentStatus::Approved => [
                DocumentStatus::PendingApproval,
                DocumentStatus::Paid,
                DocumentStatus::Voided,
            ],
            DocumentStatus::Rejected => [
                DocumentStatus::PendingApproval,
                DocumentStatus::Paid,
                DocumentStatus::Voided,
            ],
            // Paid is a final state
            DocumentStatus::Paid => [],
            // Voided is a final state
            DocumentStatus::Voided => [],
        };
    }

    /**
     * Gets a list of transitions that the recipient can perform
     * on a document that is in a given document state.
     */
    private function getAllowedTransitionsReceiver(DocumentStatus $from): array
    {
        return match ($from) {
            DocumentStatus::PendingApproval => [
                DocumentStatus::Rejected,
                DocumentStatus::Approved,
                DocumentStatus::Paid,
            ],
            DocumentStatus::Approved => [
                DocumentStatus::Rejected,
                DocumentStatus::Paid,
            ],
            DocumentStatus::Rejected => [
                DocumentStatus::Approved,
            ],
            // Paid is a final state
            DocumentStatus::Paid => [],
            // Voided is a final state
            DocumentStatus::Voided => [],
        };
    }
}
