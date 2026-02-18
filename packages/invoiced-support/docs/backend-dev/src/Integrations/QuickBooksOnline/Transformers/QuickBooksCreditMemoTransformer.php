<?php

namespace App\Integrations\QuickBooksOnline\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ReadSync\AbstractCreditNoteTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksMapper;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Core\Orm\Model;

/**
 * @property QuickBooksOnlineSyncProfile $syncProfile
 */
class QuickBooksCreditMemoTransformer extends AbstractCreditNoteTransformer
{
    protected QuickBooksMapper $mapper;
    protected bool $importDrafts = false;

    public function __construct(
        protected QuickBooksApi $client,
    ) {
        $this->mapper = new QuickBooksMapper();
    }

    /**
     * @param QuickBooksAccount $account
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        parent::initialize($account, $syncProfile);
        $this->client->setAccount($account);
        $this->importDrafts = $this->syncProfile->read_invoices_as_drafts;
    }

    /**
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // enable draft mode for new documents
        if ($this->importDrafts) {
            $record['draft'] = true;
        }

        // Line Items
        [$record['discount'], $record['items']] = $this->mapper->buildDocumentLines($this->client, $input->document->Line);

        // Custom Fields
        foreach ($input->document->CustomField as $qboCustomField) {
            if (property_exists($qboCustomField, 'StringValue')) {
                $key = $this->mapper->buildMetadataKey($this->syncProfile, $qboCustomField);
                $record['metadata'] ??= [];
                $record['metadata'][$key] = $qboCustomField->StringValue;
            }
        }

        $record['tax'] ??= 0;

        $record['voided'] = 0 == $input->document->TotalAmt;

        return $record;
    }
}
