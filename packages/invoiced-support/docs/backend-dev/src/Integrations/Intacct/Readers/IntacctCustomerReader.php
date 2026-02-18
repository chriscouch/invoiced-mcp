<?php

namespace App\Integrations\Intacct\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Traits\CustomerReaderTrait;
use App\Integrations\Intacct\Extractors\IntacctCustomerExtractor;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Transformers\IntacctCustomerTransformer;

class IntacctCustomerReader extends AbstractIntacctReader
{
    use CustomerReaderTrait;

    public function __construct(
        TransactionManager $transactionManager,
        IntacctCustomerExtractor $extractor,
        IntacctCustomerTransformer $transformer,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractor, $transformer, $loaderFactory);
    }

    public function getId(): string
    {
        return 'intacct_customer';
    }

    /**
     * @param IntacctSyncProfile $syncProfile
     */
    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->read_customers && IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO != $syncProfile->customer_import_type;
    }
}
