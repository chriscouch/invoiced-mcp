<?php

namespace App\Tests\Core\RestApi\Filters;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Filters\FilterFactory;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Tests\AppTestCase;

class FilterFactoryTest extends AppTestCase
{
    private function getFactory(): FilterFactory
    {
        return new FilterFactory();
    }

    public function testGetFilterableFields(): void
    {
        $factory = $this->getFactory();
        $this->assertEquals([
            'active',
            'address1',
            'address2',
            'attention_to',
            'autopay',
            'autopay_delay_days',
            'avalara_entity_use_code',
            'avalara_exemption_number',
            'bill_to_parent',
            'chase',
            'chasing_cadence',
            'chasing_cadence_id',
            'city',
            'consolidated',
            'convenience_fee',
            'country',
            'created_at',
            'credit_balance',
            'credit_hold',
            'credit_limit',
            'currency',
            'default_source_type',
            'email',
            'id',
            'language',
            'late_fee_schedule',
            'late_fee_schedule_id',
            'metadata',
            'name',
            'network_connection',
            'network_connection_id',
            'next_chase_step',
            'next_chase_step_id',
            'number',
            'owner',
            'owner_id',
            'parent_customer',
            'payment_terms',
            'phone',
            'postal_code',
            'state',
            'surcharging',
            'tax_id',
            'taxable',
            'type',
            'updated_at',
        ], $factory->getFilterableProperties(Customer::class));

        $factory = $this->getFactory();
        $this->assertEquals([
            'amount_credited',
            'amount_paid',
            'amount_written_off',
            'attempt_count',
            'autopay',
            'balance',
            'closed',
            'consolidated',
            'created_at',
            'currency',
            'customer',
            'date',
            'date_bad_debt',
            'date_paid',
            'date_voided',
            'draft',
            'due_date',
            'id',
            'late_fees',
            'metadata',
            'name',
            'needs_attention',
            'next_payment_attempt',
            'number',
            'paid',
            'payment_terms',
            'purchase_order',
            'sent',
            'status',
            'subscription',
            'subscription_id',
            'subtotal',
            'total',
            'updated_at',
            'viewed',
            'voided',
        ], $factory->getFilterableProperties(Invoice::class));
    }

    public function testSimpleFilter(): void
    {
        $factory = $this->getFactory();
        $input = [
            'name' => '1234',
            'number' => '456',
        ];
        $expected = new ListFilter(
            [
                new FilterCondition(
                    operator: FilterOperator::Equal,
                    field: 'name',
                    value: '1234',
                ),
                new FilterCondition(
                    operator: FilterOperator::Equal,
                    field: 'number',
                    value: '456',
                ),
            ],
        );
        $this->assertEquals($expected, $factory->parseSimpleFilter($input, Customer::class));
    }

    public function testSimpleFilterNotAllowed(): void
    {
        $this->expectException(InvalidRequest::class);
        $factory = $this->getFactory();
        $input = [
            'not_allowed' => '1234',
        ];
        $factory->parseSimpleFilter($input, Customer::class);
    }

    public function testSimpleFilterInvalidValue(): void
    {
        $this->expectException(InvalidRequest::class);
        $factory = $this->getFactory();
        $input = [
            'name' => ['1234'],
        ];
        $factory->parseSimpleFilter($input, Customer::class);
    }

    public function testAdvancedFilter(): void
    {
        $factory = $this->getFactory();
        $input = [
            [
                'field' => 'purchase_order',
                'operator' => 'not_contains',
                'value' => '1234',
            ],
            [
                'field' => 'total',
                'operator' => '>=',
                'value' => 456,
            ],
            [
                'field' => 'date',
                'operator' => '>=',
                'value' => '2023-01-01',
            ],
            [
                'field' => 'date',
                'operator' => '<=',
                'value' => '2023-12-31',
            ],
            [
                'field' => 'updated_at',
                'operator' => '>=',
                'value' => '2023-12-31',
            ],
            [
                'field' => 'metadata.test',
                'operator' => '=',
                'value' => 'Custom Field Test',
            ],
        ];
        $input = (string) json_encode($input);
        $expected = new ListFilter(
            [
                new FilterCondition(
                    operator: FilterOperator::NotContains,
                    field: 'purchase_order',
                    value: '1234',
                ),
                new FilterCondition(
                    operator: FilterOperator::GreaterThanOrEqual,
                    field: 'total',
                    value: 456,
                ),
                new FilterCondition(
                    operator: FilterOperator::GreaterThanOrEqual,
                    field: 'date',
                    value: 1672531200,
                ),
                new FilterCondition(
                    operator: FilterOperator::LessThanOrEqual,
                    field: 'date',
                    value: 1704067199,
                ),
                new FilterCondition(
                    operator: FilterOperator::GreaterThanOrEqual,
                    field: 'updated_at',
                    value: '2023-12-31 00:00:00',
                ),
                new FilterCondition(
                    operator: FilterOperator::Equal,
                    field: 'metadata.test',
                    value: 'Custom Field Test',
                ),
            ],
        );
        $this->assertEquals($expected, $factory->parseAdvancedFilter($input, Invoice::class));
    }
}
