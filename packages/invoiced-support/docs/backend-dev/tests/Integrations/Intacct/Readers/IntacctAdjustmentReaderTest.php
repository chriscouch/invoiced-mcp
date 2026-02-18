<?php

namespace App\Tests\Integrations\Intacct\Readers;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Extractors\IntacctAdjustmentExtractor;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Readers\IntacctAdjustmentReader;
use App\Integrations\Intacct\Transformers\IntacctAdjustmentTransformer;
use Carbon\CarbonImmutable;
use Mockery;

class IntacctAdjustmentReaderTest extends AbstractIntacctReaderTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasUnappliedCreditNote();
    }

    public function testGetId(): void
    {
        $api = $this->getApi();
        $transformer = $this->getTransformer(IntacctAdjustmentTransformer::class);
        $reader = $this->getReader($api, $transformer, IntacctAdjustmentReader::class);
        $this->assertEquals('intacct_ar_adjustment', $reader->getId());
    }

    public function testIsEnabled(): void
    {
        $api = $this->getApi();
        $transformer = $this->getTransformer(IntacctAdjustmentTransformer::class);
        $reader = $this->getReader($api, $transformer, IntacctAdjustmentReader::class);
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_ar_adjustments = false;
        $syncProfile->credit_note_types = ['test', 'test2'];
        $this->assertFalse($reader->isEnabled($syncProfile));
        $syncProfile->read_ar_adjustments = true;
        $this->assertTrue($reader->isEnabled($syncProfile));
    }

    public function testCreditNoteEmpty(): void
    {
        $api = $this->getApi();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_ar_adjustments = true;
        $syncProfile->credit_note_types = ['test'];
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $transformer = $this->getTransformer(IntacctAdjustmentTransformer::class);
        $extractor = Mockery::mock(IntacctAdjustmentExtractor::class);
        $extractor->shouldReceive('initialize')->once();
        $extractor->shouldReceive('getObjects')->once()->andReturnUsing(function () {
            yield from [];
        });
        $reader = $this->getReader($api, $transformer, IntacctAdjustmentReader::class, null, $extractor);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    public function testSyncAll(): void
    {
        $api = $this->getApi();
        $syncProfile = new IntacctSyncProfile();
        $syncProfile->read_ar_adjustments = true;
        $syncProfile->credit_note_types = ['test'];
        $syncProfile->created_at = (new CarbonImmutable('2022-08-02'))->getTimestamp();
        $query = new ReadQuery(new CarbonImmutable('2022-08-02'));
        $extractor = Mockery::mock(IntacctAdjustmentExtractor::class);
        $extractor->shouldReceive('initialize')->once();
        $extractor->shouldReceive('getObjects')->once()->andReturnUsing(function () {
            yield new AccountingXmlRecord(
                document: new \SimpleXMLElement('<test>
                        <RECORDNO>test1</RECORDNO>
                    </test>'),
                lines: [],
            );
            yield new AccountingXmlRecord(
                document: new \SimpleXMLElement('<test>
                        <RECORDNO>test2</RECORDNO>
                    </test>'),
                lines: [],
            );
        });
        $extractor->shouldReceive('getObjectId')->andReturnUsing(fn (AccountingXmlRecord $input) => (string) $input->document->{'RECORDNO'});
        $transformer = $this->getTransformer(IntacctAdjustmentTransformer::class);
        $transformedCreditNote1 = new AccountingCreditNote(IntegrationType::Intacct, '1', new AccountingCustomer(IntegrationType::Intacct, '1'));
        $transformer->shouldReceive('transform')->times(2)->andReturn($transformedCreditNote1); /* @phpstan-ignore-line */
        $loader = Mockery::mock(LoaderInterface::class);
        $loader->shouldReceive('load')->withArgs([$transformedCreditNote1])->times(2)->andReturn(new ImportRecordResult());

        // half of results should end up as false for should sync, because
        // 'RECORDNO' => 'test2',
        $mapping = new AccountingCreditNoteMapping();
        $mapping->integration_id = 1;
        $mapping->accounting_id = 'test2';
        $mapping->credit_note = self::$creditNote;
        $mapping->source = AbstractMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();
        $reader = $this->getReader($api, $transformer, IntacctAdjustmentReader::class, $loader, $extractor);
        $reader->syncAll(self::$account, $syncProfile, $query);
    }

    protected function getExtractor(IntacctApi $api): ExtractorInterface
    {
        return new IntacctAdjustmentExtractor($api);
    }
}
