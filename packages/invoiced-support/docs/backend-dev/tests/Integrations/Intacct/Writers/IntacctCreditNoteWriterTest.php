<?php

namespace App\Tests\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Writers\IntacctCreditNoteWriter;
use App\Integrations\Intacct\Writers\IntacctCustomerWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Functions\AbstractFunction;
use Intacct\Functions\AccountsReceivable\ArAdjustmentCreate;
use Intacct\Functions\AccountsReceivable\ArAdjustmentDelete;
use Mockery;

class IntacctCreditNoteWriterTest extends AppTestCase
{
    private static IntacctSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasUnappliedCreditNote();

        self::hasIntacctAccount();
        self::$syncProfile = new IntacctSyncProfile();
        self::$syncProfile->item_account = 'SALES001';
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->line_item_custom_field_mapping = (object) [
            'metadata.department' => 'DEPARTMENT',
        ];
        self::$syncProfile->customer_top_level = false;
        self::$syncProfile->saveOrFail();
    }

    private function getWriter(IntacctApi $intacctApi): IntacctCreditNoteWriter
    {
        $customerWriter = Mockery::mock(IntacctCustomerWriter::class);
        $customerWriter->shouldReceive('createIntacctCustomer')
            ->andReturn('0'); // return isn't used so it doesnt matter
        $customerWriter->shouldReceive('getIntacctEntity')->andReturn(null);

        $writer = new IntacctCreditNoteWriter($intacctApi, $customerWriter);
        $writer->setStatsd(new StatsdClient());
        $writer->setLogger(self::$logger);

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $writer = $this->getWriter($intacctApi);
        $this->assertFalse($writer->isEnabled(new IntacctSyncProfile(['write_credit_notes' => false])));
        $this->assertTrue($writer->isEnabled(new IntacctSyncProfile(['write_credit_notes' => true])));
    }

    public function testCreate(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (AbstractFunction $function) {
                if ($function instanceof ArAdjustmentCreate) {
                    $this->assertEquals('CUST-00001', $function->getCustomerId());
                    $this->assertEquals('CN-00001', $function->getAdjustmentNumber());
                    $this->assertEquals('Credit note # CN-00001 imported from Invoiced (ID: '.self::$creditNote->id().')', $function->getDescription());
                    $this->assertEquals(CarbonImmutable::createFromTimestamp(self::$creditNote->date), $function->getTransactionDate());

                    $lines = $function->getLines();
                    $this->assertCount(1, $lines);
                    $this->assertEquals(-100.0, $lines[0]->getTransactionAmount());
                    $this->assertEquals('Test Item: test', $lines[0]->getMemo());
                    $this->assertEquals('SALES001', $lines[0]->getGlAccountNumber());
                    $this->assertEquals('CUST-00001', $lines[0]->getCustomerId());

                    return '1234';
                }

                throw new \Exception('Unrecognized request');
            });

        $writer = $this->getWriter($intacctApi);

        $writer->create(self::$creditNote, self::$intacctAccount, self::$syncProfile);

        /** @var AccountingCreditNoteMapping $mapping */
        $mapping = AccountingCreditNoteMapping::find(self::$creditNote->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testUpdateNotVoided(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->never();

        $writer = $this->getWriter($intacctApi);
        $writer->update(self::$creditNote, self::$intacctAccount, self::$syncProfile);
    }

    public function testVoid(): void
    {
        self::$creditNote->void();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArAdjustmentDelete $adjustmentDelete) {
                $this->assertEquals('1234', $adjustmentDelete->getRecordNo());

                return '1234';
            });

        $writer = $this->getWriter($intacctApi);

        $writer->update(self::$creditNote, self::$intacctAccount, self::$syncProfile);
    }

    public function testCreateCustomFieldMapping(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArAdjustmentCreate $intacctCreditNote) {
                foreach ($intacctCreditNote->getLines() as $lineItem) {
                    $expected = [
                        'DEPARTMENT' => 'Sales',
                    ];
                    $this->assertEquals($expected, $lineItem->getCustomFields());
                }

                return '1235';
            })
            ->once();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'unit_cost' => 100,
                'name' => 'Test',
                'metadata' => (object) ['department' => 'Sales'],
            ],
        ];
        $creditNote->metadata = (object) ['department' => 'Sales'];
        $creditNote->saveOrFail();

        $writer = $this->getWriter($intacctApi);

        $writer->create($creditNote, self::$intacctAccount, self::$syncProfile);
    }

    public function testCreateDimensionMapping(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArAdjustmentCreate $intacctCreditNote) {
                [$lineItem1, $lineItem2] = $intacctCreditNote->getLines();
                $this->assertEquals('glaccountno', $lineItem1->getGlAccountNumber());
                $this->assertEquals('allocation', $lineItem1->getAllocationId());
                $this->assertEquals('location', $lineItem1->getLocationId());
                $this->assertEquals('department', $lineItem1->getDepartmentId());
                $this->assertEquals('project', $lineItem1->getProjectId());
                $this->assertEquals('vendor', $lineItem1->getVendorId());
                $this->assertEquals('employee', $lineItem1->getEmployeeId());
                $this->assertEquals('item', $lineItem1->getItemId());
                $this->assertEquals('class', $lineItem1->getClassId());
                $this->assertEquals('contract', $lineItem1->getContractId());
                $this->assertEquals('warehouse', $lineItem2->getWarehouseId());
                $this->assertEquals('offsetglaccountno', $lineItem2->getOffsetGLAccountNumber());

                return '1235';
            })
            ->once();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'unit_cost' => 100,
                'name' => 'Test',
                'metadata' => (object) [
                    'intacct_glaccountno' => 'glaccountno',
                    'intacct_allocation' => 'allocation',
                    'intacct_location' => 'location',
                    'intacct_department' => 'department',
                    'intacct_project' => 'project',
                    'intacct_vendor' => 'vendor',
                    'intacct_employee' => 'employee',
                    'intacct_item' => 'item',
                    'intacct_class' => 'class',
                    'intacct_contract' => 'contract',
                ],
            ],
            [
                'unit_cost' => 100,
                'name' => 'Test',
                // split into 2 lines due to metadata limit of 10 per line item
                'metadata' => (object) [
                    'intacct_offsetglaccountno' => 'offsetglaccountno',
                    'intacct_warehouse' => 'warehouse',
                ],
            ],
        ];
        $creditNote->saveOrFail();

        $writer = $this->getWriter($intacctApi);

        $writer->create($creditNote, self::$intacctAccount, self::$syncProfile);
    }
}
