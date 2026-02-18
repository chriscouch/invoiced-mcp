<?php

namespace App\Integrations\Intacct\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Traits\PaymentReaderTrait;
use App\Integrations\Intacct\Extractors\IntacctPaymentExtractor;
use App\Integrations\Intacct\Transformers\IntacctPaymentTransformer;

class IntacctPaymentReader extends AbstractIntacctReader
{
    use PaymentReaderTrait;

    public function __construct(
        TransactionManager $transactionManager,
        IntacctPaymentExtractor $extractor,
        IntacctPaymentTransformer $transformer,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractor, $transformer, $loaderFactory);
    }

    public function getId(): string
    {
        return 'intacct_ar_payment';
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'A/R Payments from Intacct';
    }
}
