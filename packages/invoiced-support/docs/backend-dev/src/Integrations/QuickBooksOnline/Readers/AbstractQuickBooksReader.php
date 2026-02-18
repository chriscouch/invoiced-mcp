<?php

namespace App\Integrations\QuickBooksOnline\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ReadSync\AbstractFactoryReader;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksExtractorFactory;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksTransformerFactory;

abstract class AbstractQuickBooksReader extends AbstractFactoryReader
{
    public function __construct(
        TransactionManager $transactionManager,
        QuickBooksExtractorFactory $extractorFactory,
        QuickBooksTransformerFactory $transformerFactory,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractorFactory, $transformerFactory, $loaderFactory);
    }

    public function getId(): string
    {
        return 'quickbooks_online_'.$this->invoicedObjectType()->typeName();
    }
}
