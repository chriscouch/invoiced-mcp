<?php

namespace App\Integrations\QuickBooksOnline\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ReadSync\AbstractInvoiceTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksMapper;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Core\Orm\Model;

/**
 * @property QuickBooksOnlineSyncProfile $syncProfile
 */
class QuickBooksInvoiceTransformer extends AbstractInvoiceTransformer
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

        // Payment Terms
        if (isset($record['payment_terms'])) {
            try {
                $record['payment_terms'] = $this->client->getTerm($record['payment_terms'])->Name;
            } catch (IntegrationApiException) {
                // Do nothing if terms cannot be retrieved.
                // We do not want to block the invoice if we don't have the terms.
                unset($record['payment_terms']);
            }
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

        // Ship To
        $hasShipTo = false;
        foreach ($record['ship_to'] as $name => $value) {
            if ('name' != $name && null !== $value) {
                $hasShipTo = true;
            }
        }
        if (!$hasShipTo) {
            unset($record['ship_to']);
        }

        $record['tax'] ??= 0;

        $record['voided'] = 0 == $input->document->TotalAmt;

        return $record;
    }
}
