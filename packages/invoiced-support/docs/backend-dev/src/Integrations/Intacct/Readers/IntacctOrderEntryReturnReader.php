<?php

namespace App\Integrations\Intacct\Readers;

use App\Core\Database\TransactionManager;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Traits\CreditNoteReaderTrait;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Intacct\Extractors\IntacctOrderEntryTransactionExtractor;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Transformers\IntacctOrderEntryReturnTransformer;
use App\Core\Orm\Model;

class IntacctOrderEntryReturnReader extends AbstractIntacctReader
{
    use CreditNoteReaderTrait;

    private string $documentType;

    public function __construct(
        TransactionManager $transactionManager,
        IntacctOrderEntryTransactionExtractor $extractor,
        IntacctOrderEntryReturnTransformer $transformer,
        AccountingLoaderFactory $loaderFactory,
    ) {
        parent::__construct($transactionManager, $extractor, $transformer, $loaderFactory);
    }

    public function getId(): string
    {
        return 'intacct_order_entry_return';
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'Order Entry Returns ('.$this->documentType.') from Intacct';
    }

    protected function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->extractor->setDocumentType($this->documentType); /* @phpstan-ignore-line */
        $this->transformer->setDocumentType($this->documentType); /* @phpstan-ignore-line */
        parent::initialize($account, $syncProfile);
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    public function syncAll(Model $account, AccountingSyncProfile $syncProfile, ReadQuery $query): void
    {
        foreach ($syncProfile->credit_note_types as $type) {
            $this->documentType = $type;
            parent::syncAll($account, $syncProfile, $query);
        }
    }

    public function syncOne(Model $account, AccountingSyncProfile $syncProfile, string $objectId): void
    {
        // TODO: the document type is not stored or known here. As a result retrying this transaction will fail.
        parent::syncOne($account, $syncProfile, $objectId);
    }
}
