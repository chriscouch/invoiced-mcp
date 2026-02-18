<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Generates the data query for a report section.
 */
final class DataQueryDeserializer
{
    private const MAX_FIELDS = 25;
    private const MAX_JOINS = 10;
    private const MAX_FILTERS = 10;
    private const MAX_GROUPS = 2;
    private const MAX_SORT = 3;
    private const MAX_RESULTS = 10000;

    /**
     * @throws ReportException
     */
    public static function deserialize(array $data, Company $company, ?Member $member): DataQuery
    {
        // each new sql query can restart the field alias counter
        SelectColumn::resetCounter();

        // This will build all of the components to the data query as
        // well as resolve the joins which will be required.
        $joinCollector = new JoinCollector($data['object']);
        $fields = FieldsDeserializer::deserialize($data['object'], $data['fields'], $joinCollector, $company);
        $filter = FilterDeserializer::deserialize($data, $company, $member, $joinCollector);
        $group = GroupDeserializer::deserialize($data['object'], $data['group'], $joinCollector, $company);
        $sort = SortDeserializer::deserialize($data['object'], $data['sort'], $joinCollector, $company);

        // finalize the joins
        $joinCollector->finalize();
        $joins = $joinCollector->all();

        // check limits
        if (count($fields) < 1 || count($fields) > self::MAX_FIELDS) {
            throw new ReportException('Reports must have at least one field and no more than '.self::MAX_FIELDS.' fields.');
        }
        if (count($joins) > self::MAX_JOINS) {
            throw new ReportException('Reports cannot have more than '.self::MAX_JOINS.' joins.');
        }
        // subtract 1 so that we do not count the tenant_id condition
        if (count($filter) - 1 > self::MAX_FILTERS) {
            throw new ReportException('Reports cannot have more than '.self::MAX_FILTERS.' filter conditions.');
        }
        if (count($group) > self::MAX_GROUPS) {
            throw new ReportException('Reports cannot have more than '.self::MAX_GROUPS.' groups.');
        }
        if (count($sort) > self::MAX_SORT) {
            throw new ReportException('Reports cannot have more than '.self::MAX_SORT.' sorting conditions.');
        }

        return new DataQuery(
            table: new Table($data['object']),
            joins: $joins,
            fields: $fields,
            filter: $filter,
            groupBy: $group,
            sort: $sort,
            maxResults: self::MAX_RESULTS
        );
    }
}
