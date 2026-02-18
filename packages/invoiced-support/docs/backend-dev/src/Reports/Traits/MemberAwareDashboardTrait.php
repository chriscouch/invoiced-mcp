<?php

namespace App\Reports\Traits;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Metadata\Libs\RestrictionQueryBuilder;
use App\Reports\Libs\ReportHelper;

trait MemberAwareDashboardTrait
{
    protected ReportHelper $helper;
    protected Company $company;
    protected ?Member $member = null;
    protected RestrictionQueryBuilder $restrictionQueryBuilder;

    public function setCompany(Company $company): void
    {
        $this->company = $company;
        $company->useTimezone();
        $this->helper->switchTimezone($company->time_zone);
    }

    /**
     * Restricts the results to only include
     * customers where that the given member
     * is an account manager of.
     */
    public function setMember(Member $member): void
    {
        $this->member = $member;
        $restrictions = $member->restrictions() ?? [];
        $this->restrictionQueryBuilder = new RestrictionQueryBuilder($this->company, $restrictions);
    }
}
