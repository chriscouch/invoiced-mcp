<?php

namespace App\Tests\Integrations\Xero\Writers;

use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Writers\XeroCustomerWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class XeroCustomerWriterTest extends AppTestCase
{
    private static StatsdClient $statsd;
    private static XeroSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        self::hasXeroAccount();
        self::$syncProfile = new XeroSyncProfile();
        self::$syncProfile->write_customers = true;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();

        self::$statsd = new StatsdClient();
    }

    private function getWriter(XeroApi $xeroApi): XeroCustomerWriter
    {
        $writer = new XeroCustomerWriter($xeroApi);
        $writer->setStatsd(self::$statsd);

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $writer = $this->getWriter($xeroApi);
        $this->assertFalse($writer->isEnabled(new XeroSyncProfile(['write_customers' => false])));
        $this->assertTrue($writer->isEnabled(new XeroSyncProfile(['write_customers' => true])));
    }

    public function testCreate(): void
    {
        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $xeroApi->shouldReceive('getMany')
            ->withArgs([
                'Contacts', [
                    'where' => 'Name=="Sherlock"',
                    'includeArchived' => 'true',
                ],
            ])
            ->andReturn([]);
        $xeroApi->shouldReceive('getMany')
            ->withArgs([
                'Contacts', [
                    'where' => 'AccountNumber=="CUST-00001"',
                    'includeArchived' => 'true',
                ],
            ])
            ->andReturn([]);
        $xeroApi->shouldReceive('createOrUpdate')
            ->withArgs([
                'Contacts',
                [
                    'Name' => 'Sherlock',
                    'ContactNumber' => 'CUST-00001',
                    'AccountNumber' => 'CUST-00001',
                    'ContactStatus' => 'ACTIVE',
                    'EmailAddress' => 'sherlock@example.com',
                    'Addresses' => [
                        [
                            'AddressLine1' => 'Test',
                            'AddressLine2' => 'Address',
                            'City' => 'Austin',
                            'Country' => 'US',
                            'PostalCode' => '78701',
                            'Region' => 'TX',
                            'AddressType' => 'STREET',
                        ],
                    ],
                ],
            ])
            ->andReturn((object) ['ContactID' => '1234']);

        $writer = $this->getWriter($xeroApi);

        $writer->create(self::$customer, self::$xeroAccount, self::$syncProfile);

        /** @var AccountingCustomerMapping $mapping */
        $mapping = AccountingCustomerMapping::find(self::$customer->id());
        $this->assertEquals(IntegrationType::Xero->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testUpdate(): void
    {
        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $xeroApi->shouldReceive('createOrUpdate')
            ->withArgs([
                'Contacts',
                [
                    'ContactID' => '1234',
                    'Name' => 'Sherlock',
                    'ContactNumber' => 'CUST-00001',
                    'AccountNumber' => 'CUST-00001',
                    'ContactStatus' => 'ACTIVE',
                    'EmailAddress' => 'sherlock@example.com',
                    'Addresses' => [
                        [
                            'AddressLine1' => 'Test',
                            'AddressLine2' => 'Address',
                            'City' => 'Austin',
                            'Country' => 'US',
                            'PostalCode' => '78701',
                            'Region' => 'TX',
                            'AddressType' => 'STREET',
                        ],
                    ],
                ],
            ])
            ->andReturn()
            ->once();

        $writer = $this->getWriter($xeroApi);

        $writer->update(self::$customer, self::$xeroAccount, self::$syncProfile);
    }
}
