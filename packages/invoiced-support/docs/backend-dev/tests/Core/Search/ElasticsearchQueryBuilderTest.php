<?php

namespace App\Tests\Core\Search;

use App\Companies\Models\Member;
use App\Core\Search\Driver\Elasticsearch\ElasticsearchQueryBuilder;
use App\Core\Search\Libs\IndexRegistry;
use App\Tests\AppTestCase;
use App\Core\Orm\ACLModelRequester;

class ElasticsearchQueryBuilderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getQueryBuilder(): ElasticsearchQueryBuilder
    {
        return new ElasticsearchQueryBuilder(self::getService('test.database'), new IndexRegistry());
    }

    public function testBuildAllIndexes(): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $params = $queryBuilder->build('test query', '', self::$company, 10, true);

        $expected = [
            'index' => 'contact,credit_note,customer,invoice,payment,subscription,estimate,vendor',
            'timeout' => '30s',
            'size' => 10,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'query_string' => [
                                'query' => '*test query* OR test query*',
                                'fields' => [
                                    'name^6',
                                    'email',
                                    'address1',
                                    'address2',
                                    'postal_code',
                                    'city',
                                    'state',
                                    'country',
                                    'phone',
                                    'number^6',
                                    'purchase_order',
                                    'customer.name^3',
                                    'metadata',
                                    'reference',
                                    'plan.name^3',
                                ],
                                'fuzziness' => 'AUTO',
                                'lenient' => true,
                                'default_operator' => 'AND',
                            ],
                        ],
                        'filter' => [
                            [
                                'term' => [
                                    '_tenantId' => self::$company->id(),
                                ],
                            ],
                        ],
                    ],
                ],
                'indices_boost' => [
                    [
                        'customer' => 1.5,
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $params);
    }

    public function testBuildQueryString(): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $params = $queryBuilder->build('test query', 'customer', self::$company, 10, true);

        $expected = [
            'index' => 'customer',
            'timeout' => '30s',
            'size' => 10,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'query_string' => [
                                'query' => '*test query* OR test query*',
                                'fields' => [
                                    'name^6',
                                    'number^3',
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
                                'fuzziness' => 'AUTO',
                                'lenient' => true,
                                'default_operator' => 'AND',
                            ],
                        ],
                        'filter' => [
                            [
                                'term' => [
                                    '_tenantId' => self::$company->id(),
                                ],
                            ],
                        ],
                    ],
                ],
                'indices_boost' => [
                    [
                        'customer' => 1.5,
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $params);
    }

    public function testBuildMultiMatch(): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $params = $queryBuilder->build('test query', 'customer', self::$company, 10, false);

        $expected = [
            'index' => 'customer',
            'timeout' => '30s',
            'size' => 10,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => 'test query',
                                'fields' => [
                                    'name^6',
                                    'number^3',
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
                                'fuzziness' => 'AUTO',
                                'lenient' => true,
                            ],
                        ],
                        'filter' => [
                            [
                                'term' => [
                                    '_tenantId' => self::$company->id(),
                                ],
                            ],
                        ],
                    ],
                ],
                'indices_boost' => [
                    [
                        'customer' => 1.5,
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $params);
    }

    public function testBuildOwnerRestriction(): void
    {
        $requester = ACLModelRequester::get();

        $member = new Member();
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::OWNER_RESTRICTION;

        ACLModelRequester::set($member);

        $queryBuilder = $this->getQueryBuilder();
        $params = $queryBuilder->build('test query', 'customer', self::$company, 10, true);

        $expected = [
            'index' => 'customer',
            'timeout' => '30s',
            'size' => 10,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'query_string' => [
                                'query' => '*test query* OR test query*',
                                'fields' => [
                                    'name^6',
                                    'number^3',
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
                                'fuzziness' => 'AUTO',
                                'lenient' => true,
                                'default_operator' => 'AND',
                            ],
                        ],
                        'filter' => [
                            [
                                'term' => [
                                    '_tenantId' => self::$company->id(),
                                ],
                            ],
                            [
                                'terms' => [
                                    // empty because there are no assigned customers
                                    '_customer' => [],
                                ],
                            ],
                        ],
                    ],
                ],
                'indices_boost' => [
                    [
                        'customer' => 1.5,
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $params);

        ACLModelRequester::set($requester);
    }

    public function testBuildCustomFieldRestriction(): void
    {
        $requester = ACLModelRequester::get();

        $member = new Member();
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];

        ACLModelRequester::set($member);

        $queryBuilder = $this->getQueryBuilder();
        $params = $queryBuilder->build('test query', 'customer', self::$company, 10, true);

        $expected = [
            'index' => 'customer',
            'timeout' => '30s',
            'size' => 10,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'query_string' => [
                                'query' => '*test query* OR test query*',
                                'fields' => [
                                    'name^6',
                                    'number^3',
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
                                'fuzziness' => 'AUTO',
                                'lenient' => true,
                                'default_operator' => 'AND',
                            ],
                        ],
                        'filter' => [
                            [
                                'term' => [
                                    '_tenantId' => self::$company->id(),
                                ],
                            ],
                            [
                                'terms' => [
                                    // empty because there are no assigned customers
                                    '_customer' => [],
                                ],
                            ],
                        ],
                    ],
                ],
                'indices_boost' => [
                    [
                        'customer' => 1.5,
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $params);

        ACLModelRequester::set($requester);
    }
}
