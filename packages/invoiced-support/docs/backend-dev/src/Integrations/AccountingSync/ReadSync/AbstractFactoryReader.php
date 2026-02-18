<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Interfaces\ExtractorFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerFactoryInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;

abstract class AbstractFactoryReader extends AbstractReader
{
    public function __construct(
        TransactionManager $transactionManager,
        ExtractorFactoryInterface $extractorFactory,
        TransformerFactoryInterface $transformerFactory,
        AccountingLoaderFactory $loaderFactory,
    ) {
        $objectType = $this->invoicedObjectType();
        $extractor = $extractorFactory->get($objectType);
        $transformer = $transformerFactory->get($objectType);
        parent::__construct($transactionManager, $extractor, $transformer, $loaderFactory);
    }
}
