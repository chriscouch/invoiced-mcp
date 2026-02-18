<?php

namespace App\Integrations\Intacct\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Traits\CreditNoteReaderTrait;
use App\Integrations\Intacct\Extractors\IntacctAdjustmentExtractor;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Transformers\IntacctAdjustmentTransformer;

class IntacctAdjustmentReader extends AbstractIntacctReader
{
    use CreditNoteReaderTrait;

    public function __construct(
        TransactionManager $transactionManager,
        IntacctAdjustmentExtractor $extractor,
        IntacctAdjustmentTransformer $transformer,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractor, $transformer, $loaderFactory);
    }

    public function getId(): string
    {
        return 'intacct_ar_adjustment';
    }

    /**
     * @param IntacctSyncProfile $syncProfile
     */
    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->read_ar_adjustments;
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'A/R Adjustments from Intacct';
    }
}
