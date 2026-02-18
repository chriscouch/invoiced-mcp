<?php

namespace App\PaymentPlans\ListQueryBuilders;

use App\AccountsReceivable\ListQueryBuilders\InvoiceListQueryBuilder;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Orm\Query;
use App\PaymentPlans\Models\PaymentPlan;

class PaymentPlanListQueryBuilder extends InvoiceListQueryBuilder
{
    public static function getClassString(): string
    {
        return PaymentPlan::class;
    }

    protected function getQueryClass(): string
    {
        return Invoice::class;
    }

    public function getBuildQuery(int $limit = 1000): Query
    {
        // invoice query
        $query = parent::getBuildQuery($limit);
        $ids = [];
        foreach ($query->all() as $invoice) {
            $ids[] = $invoice->id;
        }
        $query = $ids ? PaymentPlan::where('invoice_id', $ids) : PaymentPlan::where('id', -1);
        // is converted to payment plan query
        // so we apply limit again
        $query->limit($limit);

        return $query;
    }

    public function setOptions(array $options): void
    {
        parent::setOptions($options);

        // we are not interested in the invoices without payment plans
        $this->options['payment_plan'] = 1;
    }
}
