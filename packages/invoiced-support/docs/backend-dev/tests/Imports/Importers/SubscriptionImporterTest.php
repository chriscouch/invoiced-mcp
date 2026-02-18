<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Models\Customer;
use App\Imports\Importers\Spreadsheet\SubscriptionImporter;
use App\Imports\Models\Import;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use Mockery;

class SubscriptionImporterTest extends ImporterTestBase
{
    const SHIP_TO = [
        'name' => 'ship_to_name',
        'attention_to' => 'ship_to_attention_to',
        'address1' => 'ship_to_address1',
        'address2' => 'ship_to_address2',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78735',
        'country' => 'US',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasPlan();
        self::hasCoupon();
    }

    protected function getImporter(): SubscriptionImporter
    {
        return self::getService('test.importer_factory')->get('subscription');
    }

    public function testRunCreate(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $import = $this->getImport();

        $records = $importer->build($mapping, $lines, [], $import);
        $result = $importer->run($records, [], $import);

        // verify result
        $this->assertEquals(3, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(0, $result->getNumUpdated());

        // should create a customer
        $customer = Customer::where('name', 'Sherlock Holmes 2')->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer->id())->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);

        // should create another subscription
        $subscription2 = Subscription::where('customer', $customer->id())->start(1)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription2);

        $shipToResult = $subscription2->ship_to->toArray(); /* @phpstan-ignore-line */
        unset($shipToResult['created_at']);
        unset($shipToResult['updated_at']);
        $this->assertEquals(self::SHIP_TO, $shipToResult);

        $expected = [
            'customer' => $customer->id(),
            'plan' => self::$plan->id,
            'quantity' => 1.0,
            'description' => null,
            'cycles' => null,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_NONE,
            'contract_renewal_cycles' => null,
            'contract_period_start' => null,
            'contract_period_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'canceled_reason' => null,
            'addons' => [],
            'discounts' => [
                self::$coupon->toArray(),
            ],
            'taxes' => [],
            'mrr' => 47.5,
            'recurring_total' => 95.0,
            'payment_source' => null,
            'status' => SubscriptionStatus::ACTIVE,
            'approval' => null,
            'metadata' => (object) ['test' => '1234'],
            'paused' => false,
            'bill_in' => 'advance',
            'bill_in_advance_days' => 0,
            'snap_to_nth_day' => null,
            'ship_to' => self::SHIP_TO,
            'amount' => null,
        ];

        $arr = $subscription->toArray();
        $sub = Subscription::oneOrNull();
        unset($arr['ship_to']['created_at']);
        unset($arr['ship_to']['updated_at']);

        foreach (['object', 'created_at', 'updated_at', 'id', 'date', 'url', 'start_date', 'renewed_last', 'period_start', 'period_end', 'renews_next'] as $property) {
            unset($arr[$property]);
        }

        $this->assertEquals($expected, $arr);
        $this->assertEquals(self::$company->id(), $subscription->tenant_id);

        // should create a customer
        $customer = Customer::where('name', 'Subscription Customer')->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer);

        // should create another subscription
        $subscription3 = Subscription::where('customer', $customer->id())->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription3);
        $this->assertEquals(600.0, $subscription3->recurring_total);

        // should update the position
        $this->assertEquals(3, $import->position);
    }

    protected function getLines(): array
    {
        return [
            [
                'Sherlock Holmes 2',
                '',
                date('M-d-Y', (int) mktime(6, 0, 0, (int) date('m'), 1, (int) date('Y'))),
                // items
                '',
                self::$plan->id,
                '',
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
                '1234',
                self::$coupon->id,
            ],
            [
                '',
                'CUST-00001',
                '',
                // items
                '2',
                self::$plan->id,
                'description',
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
                '',
                '',
            ],
            [
                'Subscription Customer',
                '',
                '',
                // items
                '1',
                self::$plan->id,
                '',
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
                '',
                '',
            ],
            [
                'Subscription Customer',
                '',
                '',
                // items
                '2',
                self::$plan->id,
                '',
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
                '',
                '',
            ],
            [
                'Subscription Customer',
                '',
                '',
                // items
                '3',
                self::$plan->id,
                '',
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
                '',
                '',
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'customer',
            'account_number',
            'start_date',
            // items
            'quantity',
            'plan',
            'description',
            'ship_to.name',
            'ship_to.attention_to',
            'ship_to.address1',
            'ship_to.address2',
            'ship_to.city',
            'ship_to.state',
            'ship_to.postal_code',
            'ship_to.country',
            'metadata.test',
            'discounts',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save,tenant]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->shouldReceive('tenant')
            ->andReturn(self::$company);
        $import->type = 'subscription';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock Holmes 2',
                    'number' => '',
                ],
                'start_date' => mktime(6, 0, 0, (int) date('m'), 1, (int) date('Y')),
                'plan' => 'starter',
                'quantity' => 1,
                'description' => '',
                'ship_to' => self::SHIP_TO,
                'metadata' => (object) ['test' => '1234'],
                'discounts' => ['coupon'],
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => '',
                    'number' => 'CUST-00001',
                ],
                'start_date' => null,
                'plan' => 'starter',
                'quantity' => 2.0,
                'description' => 'description',
                'ship_to' => self::SHIP_TO,
                'metadata' => (object) ['test' => null],
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Subscription Customer',
                    'number' => '',
                ],
                'start_date' => null,
                'plan' => 'starter',
                'quantity' => 1.0,
                'description' => '',
                'ship_to' => self::SHIP_TO,
                'metadata' => (object) ['test' => null],
                'addons' => [
                    [
                        'plan' => 'starter',
                        'quantity' => 2.0,
                    ],
                    [
                        'plan' => 'starter',
                        'quantity' => 3.0,
                    ],
                ],
            ],
        ];
    }
}
