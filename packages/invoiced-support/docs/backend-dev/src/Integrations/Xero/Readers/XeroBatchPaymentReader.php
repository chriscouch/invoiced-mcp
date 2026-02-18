<?php

namespace App\Integrations\Xero\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Traits\PaymentReaderTrait;
use App\Integrations\Xero\Extractors\XeroBatchPaymentExtractor;
use App\Integrations\Xero\Transformers\XeroBatchPaymentTransformer;

/**
 * This syncs the BatchPayment object type. On Xero payments can
 * be represented as a Payment or BatchPayment object.
 */
class XeroBatchPaymentReader extends AbstractXeroReader
{
    use PaymentReaderTrait;

    public function __construct(XeroBatchPaymentExtractor $extractor, XeroBatchPaymentTransformer $transformer, TransactionManager $transactionManager, AccountingLoaderFactory $loaderFactory)
    {
        $this->extractor = $extractor;
        $this->transformer = $transformer;
        $this->transactionManager = $transactionManager;
        $this->loaderFactory = $loaderFactory;
    }

    public function getId(): string
    {
        return 'xero_batch_payment';
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'Batch Payments from Xero';
    }
}
