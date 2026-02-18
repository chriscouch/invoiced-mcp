<?php

namespace App\Reports\Traits;

use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Reports\Exceptions\ReportException;
use App\Reports\Libs\StartReportJob;
use App\Reports\Models\Report as ReportModel;

trait BuildReportApiTrait
{
    private StartReportJob $startReportJob;
    private TenantContext $tenant;

    private function startReport(string $type, string|array|null $definition, array $parameters): ReportModel
    {
        $member = ACLModelRequester::get();
        if (!($member instanceof Member)) {
            $member = null;
        }

        if (is_array($definition)) {
            $definition = (string) json_encode($definition);
        }

        try {
            return $this->startReportJob->start($this->tenant->get(), $member, $type, $definition, $parameters);
        } catch (ReportException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
