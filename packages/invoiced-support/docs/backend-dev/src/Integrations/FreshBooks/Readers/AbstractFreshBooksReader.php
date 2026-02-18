<?php

namespace App\Integrations\FreshBooks\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ReadSync\AbstractFactoryReader;
use App\Integrations\FreshBooks\Extractors\FreshBooksExtractorFactory;
use App\Integrations\FreshBooks\Transformers\FreshBooksTransformerFactory;

abstract class AbstractFreshBooksReader extends AbstractFactoryReader
{
    public function __construct(
        TransactionManager $transactionManager,
        FreshBooksExtractorFactory $extractorFactory,
        FreshBooksTransformerFactory $transformerFactory,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractorFactory, $transformerFactory, $loaderFactory);
    }

    public function getId(): string
    {
        return 'freshbooks_'.$this->invoicedObjectType()->typeName();
    }
}
