<?php

namespace App\Integrations\SageAccounting\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ReadSync\AbstractFactoryReader;
use App\Integrations\SageAccounting\Extractors\SageAccountingExtractorFactory;
use App\Integrations\SageAccounting\Transformers\SageAccountingTransformerFactory;

abstract class AbstractSageAccountingReader extends AbstractFactoryReader
{
    public function __construct(
        TransactionManager $transactionManager,
        SageAccountingExtractorFactory $extractorFactory,
        SageAccountingTransformerFactory $transformerFactory,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractorFactory, $transformerFactory, $loaderFactory);
    }

    public function getId(): string
    {
        return 'sage_accounting_'.$this->invoicedObjectType()->typeName();
    }
}
