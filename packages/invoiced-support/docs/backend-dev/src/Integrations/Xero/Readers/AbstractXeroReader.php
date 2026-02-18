<?php

namespace App\Integrations\Xero\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ReadSync\AbstractFactoryReader;
use App\Integrations\Xero\Extractors\XeroExtractorFactory;
use App\Integrations\Xero\Transformers\XeroTransformerFactory;

abstract class AbstractXeroReader extends AbstractFactoryReader
{
    public function __construct(
        TransactionManager $transactionManager,
        XeroExtractorFactory $extractorFactory,
        XeroTransformerFactory $transformerFactory,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractorFactory, $transformerFactory, $loaderFactory);
    }

    public function getId(): string
    {
        return 'xero_'.$this->invoicedObjectType()->typeName();
    }
}
