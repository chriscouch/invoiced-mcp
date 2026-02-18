<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Enums\DisputeStatus;
use App\AccountsReceivable\Models\DisputeReason;
use App\AccountsReceivable\Models\InvoiceDispute;
use App\Tests\ModelTestCase;

/**
 * @extends ModelTestCase<InvoiceDispute>
 */
class InvoiceDisputeTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();
        self::hasInvoice();
    }

    protected function getModelCreate(): InvoiceDispute
    {
        $reason = new DisputeReason();
        $reason->name = 'Test';
        $reason->saveOrFail();

        $dispute = new InvoiceDispute();
        $dispute->invoice = self::$invoice;
        $dispute->status = DisputeStatus::Open;
        $dispute->reason = $reason;
        $dispute->currency = 'usd';
        $dispute->amount = 10;

        return $dispute;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'id' => $model->id,
            'amount' => 10.0,
            'currency' => 'usd',
            'invoice_id' => self::$invoice->id,
            'notes' => null,
            'reason_id' => $model->reason?->id,
            'status' => 'Open',
        ];
    }

    protected function getModelEdit($model): InvoiceDispute
    {
        $model->status = DisputeStatus::Accepted;

        return $model;
    }
}
