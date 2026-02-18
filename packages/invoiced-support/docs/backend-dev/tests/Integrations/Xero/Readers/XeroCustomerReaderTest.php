<?php

namespace App\Tests\Integrations\Xero\Readers;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Xero\Extractors\XeroContactExtractor;
use App\Integrations\Xero\Extractors\XeroExtractorFactory;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Readers\XeroCustomerReader;
use App\Integrations\Xero\Transformers\XeroContactTransformer;
use App\Integrations\Xero\Transformers\XeroTransformerFactory;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class XeroCustomerReaderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getReader(XeroApi $api, XeroContactTransformer $transformer, LoaderInterface $loader): XeroCustomerReader
    {
        $loaderFactory = $this->getLoader($loader);
        $extractorFactory = Mockery::mock(XeroExtractorFactory::class);
        $extractorFactory->shouldReceive('get')->andReturn(new XeroContactExtractor($api));
        $transformerFactory = Mockery::mock(XeroTransformerFactory::class);
        $transformerFactory->shouldReceive('get')->andReturn($transformer);

        return new XeroCustomerReader(self::getService('test.transaction_manager'), $extractorFactory, $transformerFactory, $loaderFactory);
    }

    private function getLoader(LoaderInterface $loader): AccountingLoaderFactory
    {
        $loaderFactory = Mockery::mock(AccountingLoaderFactory::class);
        $loaderFactory->shouldReceive('get')
            ->andReturn($loader);

        return $loaderFactory;
    }

    public function testGetId(): void
    {
        $api = Mockery::mock(XeroApi::class);
        $loader = Mockery::mock(LoaderInterface::class);
        $transformer = Mockery::mock(XeroContactTransformer::class);
        $reader = $this->getReader($api, $transformer, $loader);
        $this->assertEquals('xero_contact', $reader->getId());
    }

    public function testSyncAll(): void
    {
        $account = new XeroAccount();
        $syncProfile = new XeroSyncProfile();
        $syncProfile->read_customers = true;

        $contact1 = (object) ['ContactID' => 1];
        $contact2 = (object) ['ContactID' => 2];

        $api = Mockery::mock(XeroApi::class);
        $api->shouldReceive('setAccount')
            ->withArgs([$account])
            ->once();
        $api->shouldReceive('getMany')
            ->withArgs([
                'Contacts',
                [
                    'page' => 1,
                    'where' => 'IsCustomer==true',
                    'includeArchived' => 'true',
                ],
                [
                    'If-Modified-Since' => '2021-11-16T00:00:00',
                ],
            ])
            ->andReturn([$contact1, $contact2]);
        $api->shouldReceive('getMany')
            ->withArgs([
                'Contacts',
                [
                    'page' => 2,
                    'where' => 'IsCustomer==true',
                    'includeArchived' => 'true',
                ],
                [
                    'If-Modified-Since' => '2021-11-16T00:00:00',
                ],
            ])
            ->andReturn([]);

        $transformer = Mockery::mock(XeroContactTransformer::class);
        $transformer->shouldReceive('initialize')
            ->withArgs([$account, $syncProfile])
            ->once();
        $customer1 = new AccountingCustomer(IntegrationType::Xero, '1');
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($customer1);
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->withArgs([$customer1])
            ->andReturn(new ImportRecordResult())
            ->once();
        $customer2 = new AccountingCustomer(IntegrationType::Xero, '2');
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($customer2);
        $loader->shouldReceive('load')
            ->withArgs([$customer2])
            ->andReturn(new ImportRecordResult())
            ->once();

        $reader = $this->getReader($api, $transformer, $loader);

        $query = new ReadQuery(new CarbonImmutable('2021-11-16'));

        $reader->syncAll($account, $syncProfile, $query);
    }

    public function testSyncOne(): void
    {
        $account = new XeroAccount();
        $syncProfile = new XeroSyncProfile();
        $syncProfile->read_customers = true;

        $contact = (object) ['ContactID' => '1234'];

        $api = Mockery::mock(XeroApi::class);
        $api->shouldReceive('setAccount')
            ->withArgs([$account])
            ->once();
        $api->shouldReceive('get')
            ->withArgs(['Contacts', '1234'])
            ->andReturn($contact);

        $transformer = Mockery::mock(XeroContactTransformer::class);
        $transformer->shouldReceive('initialize')
            ->withArgs([$account, $syncProfile])
            ->once();
        $customer1 = new AccountingCustomer(IntegrationType::Xero, '1');
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($customer1);
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->withArgs([$customer1])
            ->andReturn(new ImportRecordResult())
            ->once();

        $reader = $this->getReader($api, $transformer, $loader);

        $reader->syncOne($account, $syncProfile, '1234');
    }
}
