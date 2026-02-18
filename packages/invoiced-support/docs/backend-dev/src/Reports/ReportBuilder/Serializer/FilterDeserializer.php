<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Filter;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Responsible for deserializing filter conditions on a data query.
 */
final class FilterDeserializer
{
    /**
     * @throws ReportException
     */
    public static function deserialize(array $data, Company $company, ?Member $member, JoinCollector $joins): Filter
    {
        // Always add a tenant condition to the query regardless of the requested filters.
        $conditions = [self::createTenantCondition($data, $company, $member)];

        // Add requested filter conditions.
        foreach ($data['filter'] as $conditionData) {
            if (!is_array($conditionData)) {
                throw new ReportException('Unrecognized filter');
            }

            $conditions[] = FilterConditionDeserializer::deserializeWithResolve($data['object'], $conditionData, $joins, $company);
        }

        return new Filter($conditions);
    }

    /**
     * @throws ReportException
     */
    private static function createTenantCondition(array $data, Company $company, ?Member $member): FilterCondition
    {
        // When multi-entity reports are requested this should obtain all tenant IDs
        // that the user is a member of.
        if ($data['multi_entity'] && $member) {
            /** @var Member[] $members */
            $members = Member::queryWithoutMultitenancyUnsafe()
                ->where('user_id', $member->user_id)
                ->where('(expires = 0 OR expires > '.time().')')
                ->all();

            $companyIds = [];
            foreach ($members as $member2) {
                // Verify company is active
                $company2 = $member2->tenant();
                if (!$company2->billingStatus()->isActive()) {
                    continue;
                }

                // Verify user has reporting permission
                $role = Role::queryWithTenant($company2)
                    ->where('id', $member2->role)
                    ->one();
                $member2->setRelation('role', $role); // needed because role() fails due to current tenant context
                if (!$member2->allowed('reports.create')) {
                    continue;
                }

                $companyIds[] = $company2->id();
            }
        } else {
            $companyIds = [$company->id()];
        }

        if (0 == count($companyIds)) {
            throw new ReportException('Multi-entity reporting access is not granted to this company');
        }

        return new FilterCondition(
            field: new FieldReferenceExpression(new Table($data['object']), 'tenant_id'),
            operator: '=',
            value: $companyIds
        );
    }
}
