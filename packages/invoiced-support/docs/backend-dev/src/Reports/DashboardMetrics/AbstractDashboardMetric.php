<?php

namespace App\Reports\DashboardMetrics;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Metadata\Libs\RestrictionQueryBuilder;
use App\Reports\Interfaces\DashboardMetricInterface;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;

abstract class AbstractDashboardMetric implements DashboardMetricInterface
{
    protected Company $company;
    protected ?Member $member = null;
    protected RestrictionQueryBuilder $restrictionQueryBuilder;
    protected ?Customer $customer = null;

    public function __construct(private ReportHelper $helper)
    {
    }

    protected function setContext(DashboardContext $context): void
    {
        $this->company = $context->company;
        $context->company->useTimezone();
        $this->helper->switchTimezone($context->company->time_zone);

        $this->member = $context->member;
        if ($context->member) {
            $restrictions = $context->member->restrictions() ?? [];
            $this->restrictionQueryBuilder = new RestrictionQueryBuilder($this->company, $restrictions);
        }

        $this->customer = $context->customer;
    }
}
