<?php

namespace App\Tests\Integrations\Xero\Readers;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Xero\Extractors\XeroExtractorFactory;
use App\Integrations\Xero\Extractors\XeroPaymentExtractor;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Readers\XeroPaymentReader;
use App\Integrations\Xero\Transformers\XeroPaymentTransformer;
use App\Integrations\Xero\Transformers\XeroTransformerFactory;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class XeroPaymentReaderTest extends AppTestCase
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
        $transformer = Mockery::mock(XeroPaymentTransformer::class);
        $reader = $this->getReader($api, $transformer, $loader);
        $this->assertEquals('xero_payment', $reader->getId());
    }

    private function getReader(XeroApi $api, XeroPaymentTransformer $transformer, LoaderInterface $loader): XeroPaymentReader
    {
        $loaderFactory = $this->getLoader($loader);
        $extractorFactory = Mockery::mock(XeroExtractorFactory::class);
        $extractorFactory->shouldReceive('get')->andReturn(new XeroPaymentExtractor($api));
        $transformerFactory = Mockery::mock(XeroTransformerFactory::class);
        $transformerFactory->shouldReceive('get')->andReturn($transformer);

        return new XeroPaymentReader(self::getService('test.transaction_manager'), $extractorFactory, $transformerFactory, $loaderFactory);
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
        $syncProfile->read_payments = true;

        $payment1 = (object) ['PaymentID' => 1];
        $payment2 = (object) ['PaymentID' => 2];

        $api = Mockery::mock(XeroApi::class);
        $api->shouldReceive('setAccount')
            ->withArgs([$account])
            ->once();
        $api->shouldReceive('getMany')
            ->withArgs([
                'Payments',
                [
                    'page' => 1,
                    'where' => 'PaymentType=="ACCRECPAYMENT"',
                ],
                [
                    'If-Modified-Since' => '2021-11-16T00:00:00',
                ],
            ])
            ->andReturn([$payment1, $payment2]);
        $api->shouldReceive('getMany')
            ->withArgs([
                'Payments',
                [
                    'page' => 2,
                    'where' => 'PaymentType=="ACCRECPAYMENT"',
                ],
                [
                    'If-Modified-Since' => '2021-11-16T00:00:00',
                ],
            ])
            ->andReturn([]);

        $transformer = Mockery::mock(XeroPaymentTransformer::class);
        $transformer->shouldReceive('initialize')
            ->withArgs([$account, $syncProfile])
            ->once();
        $transformedPayment1 = new AccountingPayment(IntegrationType::Xero, '1');
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedPayment1);
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->withArgs([$transformedPayment1])
            ->andReturn(new ImportRecordResult())
            ->once();
        $transformedPayment2 = new AccountingPayment(IntegrationType::Xero, '2');
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedPayment2);
        $loader->shouldReceive('load')
            ->withArgs([$transformedPayment2])
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
        $syncProfile->read_payments = true;

        $payment = (object) ['PaymentID' => '1234'];

        $api = Mockery::mock(XeroApi::class);
        $api->shouldReceive('setAccount')
            ->withArgs([$account])
            ->once();
        $api->shouldReceive('get')
            ->withArgs(['Payments', '1234'])
            ->andReturn($payment);

        $transformer = Mockery::mock(XeroPaymentTransformer::class);
        $transformer->shouldReceive('initialize')
            ->withArgs([$account, $syncProfile])
            ->once();
        $transformedPayment1 = new AccountingPayment(IntegrationType::Xero, '1');
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedPayment1);
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->withArgs([$transformedPayment1])
            ->andReturn(new ImportRecordResult())
            ->once();

        $reader = $this->getReader($api, $transformer, $loader);

        $reader->syncOne($account, $syncProfile, '1234');
    }
}
