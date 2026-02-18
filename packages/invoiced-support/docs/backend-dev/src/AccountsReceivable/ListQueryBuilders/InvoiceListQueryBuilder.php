<?php

namespace App\AccountsReceivable\ListQueryBuilders;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowEnrollment;
use App\Chasing\Models\PromiseToPay;
use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentPlans\Models\PaymentPlan;

/**
 * @extends DocumentListQueryBuilder<Invoice>
 */
class InvoiceListQueryBuilder extends DocumentListQueryBuilder
{
    protected function fixLegacyOptions(ListFilter $filter): ListFilter
    {
        $filter = parent::fixLegacyOptions($filter);
        $paymentAttempted = array_value($this->options, 'payment_attempted');
        if ('0' === $paymentAttempted) {
            $filter = $filter->with(new FilterCondition(FilterOperator::Equal, 'attempt_count', 0));
        } elseif ($paymentAttempted) {
            $filter = $filter->with(new FilterCondition(FilterOperator::GreaterThan, 'attempt_count', 0));
        }

        // has payment plan
        $hasPaymentPlan = array_value($this->options, 'payment_plan');
        if ('0' === $hasPaymentPlan) {
            $filter = $filter->with(new FilterCondition(FilterOperator::Empty, 'payment_plan_id', null));
        } elseif ('needs_approval' === $hasPaymentPlan) {
            $this->query->join(PaymentPlan::class, 'payment_plan_id', 'PaymentPlans.id')
                ->where('PaymentPlans.status', PaymentPlan::STATUS_PENDING_SIGNUP);
        } elseif ($hasPaymentPlan) {
            $filter = $filter->with(new FilterCondition(FilterOperator::NotEmpty, 'payment_plan_id', null));
        }

        return $this->fixLegacyNumericJson($filter, 'balance');
    }

    public function initialize(): void
    {
        parent::initialize();

        // invoice tags
        if ($tags = (array) array_value($this->options, 'tags')) {
            // build IN query
            $in = $tags;
            foreach ($in as &$tag) {
                $tag = $this->database->quote($tag);
            }
            $in = implode(',', $in);

            $this->query->where("(SELECT COUNT(*) FROM InvoiceTags WHERE invoice_id=Invoices.id AND tag IN ($in)) > 0");
        }

        // broken promises
        if (array_value($this->options, 'broken_promises')) {
            $this->query->where('paid', false)
                ->where('closed', false)
                ->where('draft', false)
                ->where('voided', false)
                ->where('ExpectedPaymentDates.date', time(), '<')
                ->join(PromiseToPay::class, 'id', 'invoice_id');
        }

        // has payment info
        $hasPaymentInfo = array_value($this->options, 'customer_payment_info');
        if ('0' === $hasPaymentInfo) {
            $this->query->join(Customer::class, 'Invoices.customer', 'Customers.id')
                ->where('Customers.default_source_id', null);
        }

        // chasing
        $chasing = array_value($this->options, 'chasing');
        $cadence = array_value($this->options, 'cadence');
        if ($cadence) {
            $this->query->join(InvoiceDelivery::class, 'id', 'InvoiceDeliveries.invoice_id')
                ->where('InvoiceDeliveries.cadence_id', $cadence);
            if ('1' === $chasing) {
                $this->query->where('InvoiceDeliveries.disabled = FALSE');
            } elseif ('0' === $chasing) {
                $this->query->where('InvoiceDeliveries.disabled = TRUE');
            }
        } elseif ('1' === $chasing) {
            $this->query->join(InvoiceDelivery::class, 'id', 'InvoiceDeliveries.invoice_id')
                ->where('InvoiceDeliveries.disabled = FALSE');
        } elseif ('0' === $chasing) {
            // explicit false condition (different than null)
            $this->query->join(InvoiceDelivery::class, 'id', 'InvoiceDeliveries.invoice_id', 'LEFT JOIN')
                ->where('(InvoiceDeliveries.id IS NULL OR InvoiceDeliveries.disabled = TRUE)');
        }
    }

    public static function getClassString(): string
    {
        return Invoice::class;
    }

    public function applyAutomation(Query $query): void
    {
        if (!$automation = array_value($this->options, 'automation')) {
            return;
        }

        $workflow = AutomationWorkflow::find($automation);

        if (!$workflow) {
            return;
        }

        if (ObjectType::PaymentPlan === $workflow->object_type) {
            $query->join(AutomationWorkflowEnrollment::class, 'payment_plan_id', 'object_id')
                ->where('AutomationWorkflowEnrollments.workflow_id', $automation);
        } else {
            parent::applyAutomation($query);
        }
    }
}
