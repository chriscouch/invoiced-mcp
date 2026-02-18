<?php

namespace App\Tests\Integrations\Xero\Readers;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Xero\Extractors\XeroExtractorFactory;
use App\Integrations\Xero\Extractors\XeroInvoiceExtractor;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Readers\XeroInvoiceReader;
use App\Integrations\Xero\Transformers\XeroInvoiceTransformer;
use App\Integrations\Xero\Transformers\XeroTransformerFactory;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class XeroInvoiceReaderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testGetId(): void
    {
        $api = Mockery::mock(XeroApi::class);
        $loader = Mockery::mock(LoaderInterface::class);
        $transformer = Mockery::mock(XeroInvoiceTransformer::class);
        $reader = $this->getReader($api, $transformer, $loader);
        $this->assertEquals('xero_invoice', $reader->getId());
    }

    private function getReader(XeroApi $api, XeroInvoiceTransformer $transformer, LoaderInterface $loader): XeroInvoiceReader
    {
        $loaderFactory = $this->getLoader($loader);
        $extractorFactory = Mockery::mock(XeroExtractorFactory::class);
        $extractorFactory->shouldReceive('get')->andReturn(new XeroInvoiceExtractor($api));
        $transformerFactory = Mockery::mock(XeroTransformerFactory::class);
        $transformerFactory->shouldReceive('get')->andReturn($transformer);

        return new XeroInvoiceReader(self::getService('test.transaction_manager'), $extractorFactory, $transformerFactory, $loaderFactory);
    }

    private function getLoader(LoaderInterface $loader): AccountingLoaderFactory
    {
        $loaderFactory = Mockery::mock(AccountingLoaderFactory::class);
        $loaderFactory->shouldReceive('get')
            ->andReturn($loader);

        return $loaderFactory;
    }

    public function testSyncAll(): void
    {
        $account = new XeroAccount();
        $syncProfile = new XeroSyncProfile();
        $syncProfile->read_invoices = true;

        $invoice1 = (object) ['InvoiceID' => 1];
        $invoice2 = (object) ['InvoiceID' => 2];

        $api = Mockery::mock(XeroApi::class);
        $api->shouldReceive('setAccount')
            ->withArgs([$account])
            ->once();
        $api->shouldReceive('getMany')
            ->withArgs([
                'Invoices',
                [
                    'page' => 1,
                    'where' => 'Type=="ACCREC"',
                ],
                [
                    'If-Modified-Since' => '2021-11-16T00:00:00',
                ],
            ])
            ->andReturn([$invoice1, $invoice2]);
        $api->shouldReceive('getMany')
            ->withArgs([
                'Invoices',
                [
                    'page' => 2,
                    'where' => 'Type=="ACCREC"',
                ],
                [
                    'If-Modified-Since' => '2021-11-16T00:00:00',
                ],
            ])
            ->andReturn([]);
        $api->shouldReceive('getPdf')
            ->withArgs(['Invoices', '1'])
            ->andReturn('pdf');
        $api->shouldReceive('getPdf')
            ->withArgs(['Invoices', '2'])
            ->andReturn('pdf');

        $transformer = Mockery::mock(XeroInvoiceTransformer::class);
        $transformer->shouldReceive('initialize')
            ->withArgs([$account, $syncProfile])
            ->once();
        $loader = Mockery::mock(LoaderInterface::class);
        $transformedInvoice1 = new AccountingInvoice(IntegrationType::Xero, '1', new AccountingCustomer(IntegrationType::Xero, '1'));
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedInvoice1);
        $loader->shouldReceive('load')
            ->withArgs([$transformedInvoice1])
            ->andReturn(new ImportRecordResult())
            ->once();
        $transformedInvoice2 = new AccountingInvoice(IntegrationType::Xero, '1', new AccountingCustomer(IntegrationType::Xero, '1'));
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedInvoice2);
        $loader->shouldReceive('load')
            ->withArgs([$transformedInvoice2])
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
        $syncProfile->read_invoices = true;

        $invoice = (object) ['InvoiceID' => '1234'];

        $api = Mockery::mock(XeroApi::class);
        $api->shouldReceive('setAccount')
            ->withArgs([$account])
            ->once();
        $api->shouldReceive('get')
            ->withArgs(['Invoices', '1234'])
            ->andReturn($invoice);
        $api->shouldReceive('getPdf')
            ->withArgs(['Invoices', '1234'])
            ->andReturn('pdf');

        $transformer = Mockery::mock(XeroInvoiceTransformer::class);
        $loader = Mockery::mock(LoaderInterface::class);
        $transformer->shouldReceive('initialize')
            ->withArgs([$account, $syncProfile])
            ->once();
        $transformedInvoice1 = new AccountingInvoice(IntegrationType::Xero, '1', new AccountingCustomer(IntegrationType::Xero, '1'));
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedInvoice1);
        $loader->shouldReceive('load')
            ->withArgs([$transformedInvoice1])
            ->andReturn(new ImportRecordResult())
            ->once();

        $reader = $this->getReader($api, $transformer, $loader);

        $reader->syncOne($account, $syncProfile, '1234');
    }
}
