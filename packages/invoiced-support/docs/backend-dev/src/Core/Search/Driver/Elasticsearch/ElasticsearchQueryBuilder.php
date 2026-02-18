<?php

namespace App\Core\Search\Driver\Elasticsearch;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Search\Exceptions\SearchException;
use App\Core\Search\Libs\IndexRegistry;
use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Libs\RestrictionQueryBuilder;
use Doctrine\DBAL\Connection;
use App\Core\Orm\ACLModelRequester;

class ElasticsearchQueryBuilder
{
    private const MATCH_FIELDS = [
        'contact' => [
            'name',
            'email',
            'address1',
            'address2',
            'postal_code',
            'city',
            'state',
            'country',
            'phone',
        ],
        'credit_note' => [
            'name',
            'number',
            'purchase_order',
            'customer.name',
            'metadata',
        ],
        'customer' => [
            'name',
            'number',
            'email',
            'address1',
            'address2',
            'postal_code',
            'city',
            'state',
            'country',
            'phone',
            'metadata',
        ],
        'email_participant' => [
            'name',
            'email_address',
        ],
        'estimate' => [
            'name',
            'number',
            'purchase_order',
            'customer.name',
            'metadata',
        ],
        'invoice' => [
            'name',
            'number',
            'purchase_order',
            'customer.name',
            'metadata',
        ],
        'payment' => [
            'customer.name',
            'reference',
        ],
        'subscription' => [
            'plan.name',
            'customer.name',
            'metadata',
        ],
        'vendor' => [
            'name',
            'number',
        ],
    ];

    private const BOOSTED_FIELDS = [
        'contact' => [
            'name' => 6,
            'customer.name' => 3,
        ],
        'credit_note' => [
            'number' => 6,
            'customer.name' => 3,
        ],
        'customer' => [
            'name' => 6,
            'number' => 3,
        ],
        'estimate' => [
            'number' => 6,
            'customer.name' => 3,
        ],
        'invoice' => [
            'number' => 6,
            'customer.name' => 3,
        ],
        'payment' => [
            'customer.name' => 3,
        ],
        'subscription' => [
            'plan.name' => 3,
            'customer.name' => 3,
        ],
        'vendor' => [
            'name' => 6,
            'number' => 3,
        ],
    ];

    /**
     * These indexes are not searched when using the main search feature.
     */
    private const EXCLUDED_SEARCH_ALL_INDEXES = [
        'email_participant',
    ];

    public function __construct(
        private Connection $database,
        private IndexRegistry $registry,
    ) {
    }

    /**
     * Builds a query for the Elasticsearch Search API using
     * the query_string search. This will use Elasticsearch's
     * query language.
     *
     * https://www.elastic.co/guide/en/elasticsearch/reference/7.9/search-search.html
     *
     * @param bool $useEsSyntax when true uses the Elasticsearch query string syntax
     *
     * @throws SearchException
     */
    public function build(string $query, ?string $index, Company $company, int $numResults, bool $useEsSyntax): array
    {
        // parse the query string to determine modifiers, indexes, fields, etc
        [$indexes, $query] = $this->determineIndexes($company, $query, $index);
        $fields = $this->determineFields($query, $indexes, $useEsSyntax);
        $query = $this->cleanQuery($query, $useEsSyntax);

        // determine the type of query
        // - query_string: uses the Elasticsearch query string syntax
        //   https://www.elastic.co/guide/en/elasticsearch/reference/7.9/query-dsl-query-string-query.html#query-string-syntax
        // - multi_match: looks for an exact match (fallback query)
        $matchType = $useEsSyntax ? 'query_string' : 'multi_match';

        // build the elasticsearch query to Search API
        $params = [
            'index' => join(',', $indexes),
            'timeout' => '30s',
            'size' => $numResults,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            $matchType => [
                                'query' => $query,
                                'fields' => $fields,
                                'fuzziness' => 'AUTO',
                                'lenient' => true,
                            ],
                        ],
                        'filter' => [
                            [
                                'term' => [
                                    '_tenantId' => $company->id(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // When ES query syntax is used we want separate operators to be
        // considered using AND logic instead of OR.
        if ($useEsSyntax) {
            $params['body']['query']['bool']['must'][$matchType]['default_operator'] = 'AND';
        }

        // prefer results from these indices
        if (in_array('customer', $indexes)) {
            $params['body']['indices_boost'] = [
                ['customer' => 1.5],
            ];
        }

        // apply user customer restrictions
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $customerIds = $this->buildCustomerFilter($company, $requester);
            if (is_array($customerIds)) {
                $params['body']['query']['bool']['filter'][] = [
                    'terms' => [
                        '_customer' => $customerIds,
                    ],
                ];
            }
        }

        return $params;
    }

    /**
     * Determines the index(es) to search in this order:
     * 1. Specifically requested index
     * 2. Modifier in search query (i.e. is:customer)
     * 3. All indexes.
     */
    private function determineIndexes(Company $company, string $query, ?string $index): array
    {
        if ($index) {
            return [[$index], $query];
        }

        // search all available indexes as the fallback
        $indexes = [];
        foreach ($this->registry->getIndexableObjectsForCompany($company) as $modelClass) {
            $indexes[] = ObjectType::fromModelClass($modelClass)->typeName();
        }

        foreach (self::EXCLUDED_SEARCH_ALL_INDEXES as $exclude) {
            $key = array_search($exclude, $indexes);
            if (false !== $key) {
                unset($indexes[$key]);
            }
        }

        // check if the user is searching for a specific index
        // i.e. `is:customer`
        $indexRegex = '/is:('.join('|', $indexes).')/';
        if (preg_match($indexRegex, $query, $matches)) {
            $query = trim(str_replace($matches[0], '', $query));
            $indexes = [$matches[1]];
        }

        return [$indexes, $query];
    }

    /**
     * Determines the fields to search. When a query_string
     * query is used and there are fields included in the query
     * then all fields should be searched.
     *
     * We detect this scenario by the presence of a `:` in the query.
     *
     * Otherwise we include our boosted field configuration depending
     * on the index(es) being searched.
     */
    private function determineFields(string $query, array $indexes, bool $useEsSyntax): array
    {
        // if the ES syntax is used and we detect a field modifier then we search all fields
        if ($useEsSyntax && str_contains($query, ':')) {
            return ['*'];
        }

        // determine fields to search based on selected indexes
        $fields = [];
        foreach ($indexes as $index) {
            foreach (self::MATCH_FIELDS[$index] as $field) {
                if (!in_array($field, $fields)) {
                    $fields[] = $field;
                }
            }
        }

        // add field boosting
        foreach ($fields as &$field) {
            foreach ($indexes as $index) {
                if (isset(self::BOOSTED_FIELDS[$index][$field])) {
                    $field .= '^'.self::BOOSTED_FIELDS[$index][$field];
                    break;
                }
            }
        }

        return $fields;
    }

    /**
     * Formats the query string before sending to Elasticsearch.
     */
    public function cleanQuery(string $query, bool $useEsSyntax): string
    {
        // When query_string is used add wildcard before and after if
        // no reserved characters used in the query. If reserved characters
        // are in the query then adding wildcards will likely break it.
        // The reserved characters are: + - = && || > < ! ( ) { } [ ] ^ " ~ * ? : \ /
        $query = trim($query);
        if ($useEsSyntax && preg_match('/^[\w\s]+$/', $query)) {
            $query = "*$query* OR $query*";
        }

        return $query;
    }

    /**
     * Builds any needed customer filter for a user based
     * on their customer restrictions.
     */
    public function buildCustomerFilter(Company $company, Member $member): ?array
    {
        if (Member::CUSTOM_FIELD_RESTRICTION == $member->restriction_mode) {
            if ($restrictions = $member->restrictions()) {
                $restrictionQueryBuilder = new RestrictionQueryBuilder($company, $restrictions);

                $customerIdQuery = $this->database->createQueryBuilder()
                    ->select('id')
                    ->from('Customers')
                    ->where('tenant_id = :tenantId')
                    ->setParameter('tenantId', $company->id());

                $restriction = $restrictionQueryBuilder->buildSql('id');
                if ($restriction) {
                    $customerIdQuery->where($restriction);
                }

                return $customerIdQuery->fetchFirstColumn();
            }

            return null;
        }

        if (Member::OWNER_RESTRICTION == $member->restriction_mode) {
            return $this->database->createQueryBuilder()
                ->select('id')
                ->from('Customers')
                ->where('tenant_id = :tenantId')
                ->setParameter('tenantId', $company->id())
                ->where('owner_id = :userId')
                ->setParameter('userId', $member->user_id)
                ->fetchFirstColumn();
        }

        return null;
    }
}
