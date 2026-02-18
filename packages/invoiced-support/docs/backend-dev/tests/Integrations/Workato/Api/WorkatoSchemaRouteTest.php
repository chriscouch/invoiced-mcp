<?php

namespace App\Tests\Integrations\Workato\Api;

use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Workato\Api\WorkatoSchemaRoute;
use App\Metadata\Models\CustomField;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class WorkatoSchemaRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        $customField = new CustomField();
        $customField->id = 'test';
        $customField->object = 'customer';
        $customField->name = 'Test';
        $customField->type = CustomField::FIELD_TYPE_STRING;
        $customField->saveOrFail();

        $customField = new CustomField();
        $customField->id = 'test2';
        $customField->object = 'invoice';
        $customField->name = 'Test2';
        $customField->type = CustomField::FIELD_TYPE_BOOLEAN;
        $customField->saveOrFail();

        $customField = new CustomField();
        $customField->id = 'test3';
        $customField->object = 'credit_note';
        $customField->name = 'Test3';
        $customField->type = CustomField::FIELD_TYPE_DOUBLE;
        $customField->saveOrFail();

        $customField = new CustomField();
        $customField->id = 'test4';
        $customField->object = 'estimate';
        $customField->name = 'Test4';
        $customField->type = CustomField::FIELD_TYPE_INTEGER;
        $customField->saveOrFail();

        $customField = new CustomField();
        $customField->id = 'test5';
        $customField->object = 'customer';
        $customField->name = 'Test5';
        $customField->type = CustomField::FIELD_TYPE_ENUM;
        $customField->choices = ['a', 'b', 'c'];
        $customField->saveOrFail();

        $customField = new CustomField();
        $customField->id = 'test6';
        $customField->object = 'invoice';
        $customField->name = 'Test6';
        $customField->type = CustomField::FIELD_TYPE_DATE;
        $customField->saveOrFail();

        $customField = new CustomField();
        $customField->id = 'test7';
        $customField->object = 'credit_note';
        $customField->name = 'Test7';
        $customField->type = CustomField::FIELD_TYPE_MONEY;
        $customField->saveOrFail();
    }

    /**
     * @dataProvider provideTestCases
     */
    public function testBuildResponse(string $object, array $expected): void
    {
        $route = new WorkatoSchemaRoute();
        $definition = new ApiRouteDefinition(null, null, []);
        $request = new Request();
        $request->attributes->set('object', $object);
        $context = new ApiCallContext($request, [], [], $definition);
        $this->assertEquals($expected, $route->buildResponse($context));
    }

    public function provideTestCases(): array
    {
        return [
            [
                'object' => 'invalid',
                'expected' => [],
            ],
            [
                'object' => 'customer',
                'expected' => [
                    [
                        'name' => 'accounting_id',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'accounting_system',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ach_gateway',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'active',
                        'control_type' => 'checkbox',
                        'default' => true,
                    ],
                    [
                        'name' => 'address1',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'address2',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'attention_to',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'autopay',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'autopay_delay_days',
                        'type' => 'string',
                        'default' => -1,
                    ],
                    [
                        'name' => 'avalara_entity_use_code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'avalara_exemption_number',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'bill_to_parent',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'cc_gateway',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'chase',
                        'default' => true,
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'chasing_cadence',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'city',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'consolidated',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'convenience_fee',
                        'default' => true,
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'country',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'credit_hold',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'credit_limit',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'currency',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'language',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'late_fee_schedule',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'name',
                        'type' => 'string',
                        'optional' => false,
                    ],
                    [
                        'name' => 'next_chase_step',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'notes',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'number',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'owner',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'payment_terms',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'postal_code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'state',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'surcharging',
                        'default' => true,
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'tax_id',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'taxable',
                        'default' => true,
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'taxes',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'type',
                        'optional' => false,
                        'control_type' => 'select',
                        'pick_list' => [
                            ['Company', 'company'],
                            ['Government', 'government'],
                            ['Non-Profit', 'non_profit'],
                            ['Individual', 'person'],
                        ],
                    ],
                    [
                        'name' => 'metadata',
                        'type' => 'object',
                        'properties' => [
                            [
                                'name' => 'Test',
                                'type' => 'string',
                            ],
                            [
                                'name' => 'Test5',
                                'control_type' => 'select',
                                'pick_list' => [
                                    ['a', 'a'],
                                    ['b', 'b'],
                                    ['c', 'c'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'object' => 'invoice',
                'expected' => [
                    [
                        'name' => 'account_number',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'accounting_id',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'accounting_system',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'address1',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'address2',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'autopay',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'catalog_item',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'chasing_cadence',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'chasing_disabled',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'city',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'closed',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'country',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'currency',
                        'type' => 'string',
                        'optional' => false,
                    ],
                    [
                        'name' => 'customer',
                        'type' => 'string',
                        'optional' => false,
                    ],
                    [
                        'name' => 'customer_id',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'date',
                        'type' => 'string',
                        'optional' => false,
                        'default' => 'now',
                    ],
                    [
                        'name' => 'description',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'discount',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'draft',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'due_date',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'invoice_email_contacts',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'item',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'late_fees',
                        'default' => true,
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'name',
                        'type' => 'string',
                        'default' => 'Invoice',
                    ],
                    [
                        'name' => 'next_payment_attempt',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'notes',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'number',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'payment_plan_start_date',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'payment_terms',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'postal_code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'purchase_order',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'quantity',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'sent',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'ship_to.address1',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.address2',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.attention_to',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.city',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.country',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.name',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.postal_code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.state',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'starting_balance',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'state',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'tax',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'type',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'unit_cost',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'metadata',
                        'type' => 'object',
                        'properties' => [
                            [
                                'name' => 'Test2',
                                'control_type' => 'checkbox',
                            ],
                            [
                                'name' => 'Test6',
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'object' => 'credit_note',
                'expected' => [
                    [
                        'name' => 'account_number',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'accounting_id',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'accounting_system',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'address1',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'address2',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'catalog_item',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'city',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'closed',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'country',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'currency',
                        'type' => 'string',
                        'optional' => false,
                    ],
                    [
                        'name' => 'customer',
                        'type' => 'string',
                        'optional' => false,
                    ],
                    [
                        'name' => 'customer_id',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'date',
                        'type' => 'string',
                        'optional' => false,
                        'default' => 'now',
                    ],
                    [
                        'name' => 'description',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'discount',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'draft',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'item',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'name',
                        'type' => 'string',
                        'default' => 'Credit Note',
                    ],
                    [
                        'name' => 'notes',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'number',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'postal_code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'purchase_order',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'quantity',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'sent',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'starting_balance',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'state',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'tax',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'type',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'unit_cost',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'metadata',
                        'type' => 'object',
                        'properties' => [
                            [
                                'name' => 'Test3',
                                'type' => 'number',
                            ],
                            [
                                'name' => 'Test7',
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'object' => 'estimate',
                'expected' => [
                    [
                        'name' => 'account_number',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'address1',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'address2',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'approved',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'city',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'closed',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'country',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'currency',
                        'type' => 'string',
                        'optional' => false,
                    ],
                    [
                        'name' => 'customer',
                        'type' => 'string',
                        'optional' => false,
                    ],
                    [
                        'name' => 'customer_id',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'date',
                        'type' => 'string',
                        'optional' => false,
                        'default' => 'now',
                    ],
                    [
                        'name' => 'deposit',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'deposit_paid',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'description',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'discount',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'draft',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'expiration_date',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'item',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'name',
                        'type' => 'string',
                        'default' => 'Estimate',
                    ],
                    [
                        'name' => 'notes',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'number',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'payment_terms',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'postal_code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'purchase_order',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'quantity',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'sent',
                        'control_type' => 'checkbox',
                    ],
                    [
                        'name' => 'ship_to.address1',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.address2',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.attention_to',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.city',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.country',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.name',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.postal_code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'ship_to.state',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'state',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'tax',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'type',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'unit_cost',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'metadata',
                        'type' => 'object',
                        'properties' => [
                            [
                                'name' => 'Test4',
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
