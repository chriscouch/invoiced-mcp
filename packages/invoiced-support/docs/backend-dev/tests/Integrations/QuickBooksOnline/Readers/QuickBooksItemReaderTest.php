<?php

namespace App\Tests\Integrations\QuickBooksOnline\Readers;

use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksExtractorFactory;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksItemExtractor;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Readers\QuickBooksItemReader;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksItemTransformer;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksTransformerFactory;
use App\Tests\AppTestCase;
use Mockery;

class QuickBooksItemReaderTest extends AppTestCase
{
    private function getReader(QuickBooksApi $api, QuickBooksItemTransformer $transformer, ?LoaderInterface $loader = null): QuickBooksItemReader
    {
        $loaderFactory = $loader ? $this->getLoader($loader) : self::getService('test.accounting_loader_factory');
        $extractorFactory = Mockery::mock(QuickBooksExtractorFactory::class);
        $extractorFactory->shouldReceive('get')->andReturn(new QuickBooksItemExtractor($api));
        $transformerFactory = Mockery::mock(QuickBooksTransformerFactory::class);
        $transformerFactory->shouldReceive('get')->andReturn($transformer);

        return new QuickBooksItemReader(self::getService('test.transaction_manager'), $extractorFactory, $transformerFactory, $loaderFactory);
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
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $transformer = Mockery::mock(new QuickBooksItemTransformer());
        $reader = $this->getReader($quickBooksApi, $transformer);
        $this->assertEquals('quickbooks_online_item', $reader->getId());
    }
}
