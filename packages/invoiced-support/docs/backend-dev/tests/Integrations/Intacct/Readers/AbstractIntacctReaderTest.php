<?php

namespace App\Tests\Integrations\Intacct\Readers;

use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Readers\AbstractIntacctReader;
use App\Tests\AppTestCase;
use Mockery;

abstract class AbstractIntacctReaderTest extends AppTestCase
{
    protected static IntacctAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$account = new IntacctAccount();
    }

    protected function getApi(): IntacctApi|Mockery\MockInterface
    {
        $api = Mockery::mock(IntacctApi::class);
        $api->shouldReceive('setAccount');

        return $api;
    }

    protected function getTransformer(string $class): TransformerInterface
    {
        $transformer = Mockery::mock($class);
        $transformer->shouldReceive('clean', 'initialize');
        $transformer->shouldReceive('getIntacctFields')->andReturn([]);

        return $transformer; /* @phpstan-ignore-line */
    }

    protected function getReader(IntacctApi $api, TransformerInterface $transformer, string $class, ?LoaderInterface $loader = null, ?ExtractorInterface $extractor = null): AbstractIntacctReader
    {
        $extractor ??= $this->getExtractor($api);
        $loaderFactory = $loader ? $this->getLoader($loader) : self::getService('test.accounting_loader_factory');

        return new $class(self::getService('test.transaction_manager'), $extractor, $transformer, $loaderFactory); /* @phpstan-ignore-line */
    }

    abstract protected function getExtractor(IntacctApi $api): ExtractorInterface;

    private function getLoader(LoaderInterface $loader): AccountingLoaderFactory
    {
        $loaderFactory = Mockery::mock(AccountingLoaderFactory::class);
        $loaderFactory->shouldReceive('get')
            ->andReturn($loader);

        return $loaderFactory;
    }
}
