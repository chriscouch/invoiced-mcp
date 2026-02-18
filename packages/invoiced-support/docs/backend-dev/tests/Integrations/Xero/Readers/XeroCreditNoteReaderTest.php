<?php

namespace App\Tests\Integrations\Xero\Readers;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Xero\Extractors\XeroCreditNoteExtractor;
use App\Integrations\Xero\Extractors\XeroExtractorFactory;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Readers\XeroCreditNoteReader;
use App\Integrations\Xero\Transformers\XeroCreditNoteTransformer;
use App\Integrations\Xero\Transformers\XeroTransformerFactory;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class XeroCreditNoteReaderTest extends AppTestCase
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
        $transformer = Mockery::mock(XeroCreditNoteTransformer::class);
        $reader = $this->getReader($api, $transformer, $loader);
        $this->assertEquals('xero_credit_note', $reader->getId());
    }

    private function getReader(XeroApi $api, XeroCreditNoteTransformer $transformer, LoaderInterface $loader): XeroCreditNoteReader
    {
        $loaderFactory = $this->getLoader($loader);
        $extractorFactory = Mockery::mock(XeroExtractorFactory::class);
        $extractorFactory->shouldReceive('get')->andReturn(new XeroCreditNoteExtractor($api));
        $transformerFactory = Mockery::mock(XeroTransformerFactory::class);
        $transformerFactory->shouldReceive('get')->andReturn($transformer);

        return new XeroCreditNoteReader(self::getService('test.transaction_manager'), $extractorFactory, $transformerFactory, $loaderFactory);
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
        $syncProfile->read_credit_notes = true;

        $creditNote1 = (object) ['CreditNoteID' => 1];
        $creditNote2 = (object) ['CreditNoteID' => 2];

        $api = Mockery::mock(XeroApi::class);
        $api->shouldReceive('setAccount')
            ->withArgs([$account])
            ->once();
        $api->shouldReceive('getMany')
            ->withArgs([
                'CreditNotes',
                [
                    'page' => 1,
                    'where' => 'Type=="ACCRECCREDIT"',
                ],
                [
                    'If-Modified-Since' => '2021-11-16T00:00:00',
                ],
            ])
            ->andReturn([$creditNote1, $creditNote2]);
        $api->shouldReceive('getMany')
            ->withArgs([
                'CreditNotes',
                [
                    'page' => 2,
                    'where' => 'Type=="ACCRECCREDIT"',
                ],
                [
                    'If-Modified-Since' => '2021-11-16T00:00:00',
                ],
            ])
            ->andReturn([]);
        $api->shouldReceive('getPdf')
            ->withArgs(['CreditNotes', '1'])
            ->andReturn('pdf');
        $api->shouldReceive('getPdf')
            ->withArgs(['CreditNotes', '2'])
            ->andReturn('pdf');

        $transformer = Mockery::mock(XeroCreditNoteTransformer::class);
        $transformer->shouldReceive('initialize')
            ->withArgs([$account, $syncProfile])
            ->once();
        $transformedCreditNote1 = new AccountingCreditNote(IntegrationType::Xero, '1', new AccountingCustomer(IntegrationType::Xero, '1'));
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedCreditNote1);
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->withArgs([$transformedCreditNote1])
            ->andReturn(new ImportRecordResult())
            ->once();
        $transformedCreditNote2 = new AccountingCreditNote(IntegrationType::Xero, '1', new AccountingCustomer(IntegrationType::Xero, '1'));
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedCreditNote2);
        $loader->shouldReceive('load')
            ->withArgs([$transformedCreditNote2])
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
        $syncProfile->read_credit_notes = true;

        $creditNote = (object) ['CreditNoteID' => '1234'];

        $api = Mockery::mock(XeroApi::class);
        $api->shouldReceive('setAccount')
            ->withArgs([$account])
            ->once();
        $api->shouldReceive('get')
            ->withArgs(['CreditNotes', '1234'])
            ->andReturn($creditNote);
        $api->shouldReceive('getPdf')
            ->withArgs(['CreditNotes', '1234'])
            ->andReturn('pdf');

        $transformer = Mockery::mock(XeroCreditNoteTransformer::class);
        $transformer->shouldReceive('initialize')
            ->withArgs([$account, $syncProfile])
            ->once();
        $transformedCreditNote1 = new AccountingCreditNote(IntegrationType::Xero, '1', new AccountingCustomer(IntegrationType::Xero, '1'));
        $transformer->shouldReceive('transform')
            ->once()
            ->andReturn($transformedCreditNote1);
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')
            ->withArgs([$transformedCreditNote1])
            ->andReturn(new ImportRecordResult())
            ->once();

        $reader = $this->getReader($api, $transformer, $loader);

        $reader->syncOne($account, $syncProfile, '1234');
    }
}
