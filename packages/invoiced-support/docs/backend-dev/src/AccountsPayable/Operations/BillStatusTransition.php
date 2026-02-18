<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Ledger\BillBalanceGenerator;
use App\AccountsPayable\Models\Bill;
use App\Companies\Models\Member;
use App\Network\Command\TransitionDocumentStatus;
use App\Network\Enums\DocumentStatus;
use App\Core\Orm\ACLModelRequester;

class BillStatusTransition
{
    public function __construct(
        private readonly BillBalanceGenerator $balanceGenerator,
        private readonly TransitionDocumentStatus $transitionDocumentStatus,
    ) {
    }

    public function transitionStatus(Bill $bill): void
    {
        $balance = $this->balanceGenerator->getBalance($bill);
        if ($balance->isPositive() && PayableDocumentStatus::Paid === $bill->status) {
            $bill->status = PayableDocumentStatus::Approved;
            $bill->saveOrFail();
        } elseif (!$balance->isPositive() && PayableDocumentStatus::Paid !== $bill->status) {
            $bill->status = PayableDocumentStatus::Paid;
            $bill->saveOrFail();

            // Transition the network document status to paid
            if ($networkDocument = $bill->network_document) {
                if ($this->transitionDocumentStatus->isTransitionAllowed($networkDocument->current_status, DocumentStatus::Paid, false)) {
                    $requester = ACLModelRequester::get();
                    $member = $requester instanceof Member ? $requester : null;
                    $this->transitionDocumentStatus->performTransition($networkDocument, $bill->tenant(), DocumentStatus::Paid, $member, null, true);
                }
            }
        }
    }
}
