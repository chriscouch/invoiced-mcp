<?php

namespace App\Integrations\BusinessCentral\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ReadSync\AbstractFactoryReader;
use App\Integrations\BusinessCentral\Extractors\BusinessCentralExtractorFactory;
use App\Integrations\BusinessCentral\Transformers\BusinessCentralTransformerFactory;

abstract class AbstractBusinessCentralReader extends AbstractFactoryReader
{
    public function __construct(
        TransactionManager $transactionManager,
        BusinessCentralExtractorFactory $extractorFactory,
        BusinessCentralTransformerFactory $transformerFactory,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractorFactory, $transformerFactory, $loaderFactory);
    }

    public function getId(): string
    {
        return 'business_central_'.$this->invoicedObjectType()->typeName();
    }
}
