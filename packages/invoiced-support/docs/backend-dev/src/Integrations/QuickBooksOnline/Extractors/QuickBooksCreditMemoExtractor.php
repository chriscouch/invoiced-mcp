<?php

namespace App\Integrations\QuickBooksOnline\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use Generator;
use App\Core\Orm\Model;

class QuickBooksCreditMemoExtractor extends AbstractQuickBooksExtractor
{
    private bool $readPdfs;

    /**
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        parent::initialize($account, $syncProfile);
        $this->readPdfs = $syncProfile->read_pdfs;
    }

    /**
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        $queryParts = [];
        if ($lastSynced = $query->lastSynced) {
            $sinceDate = $lastSynced->toAtomString();
            $queryParts[] = "MetaData.LastUpdatedTime > '$sinceDate'";
        }
        if ($date = $query->startDate) {
            $date = $date->toDateString();
            $queryParts[] = "TxnDate >= '$date'";
        }
        if ($date = $query->endDate) {
            $date = $date->toDateString();
            $queryParts[] = "TxnDate <= '$date'";
        }
        if ($query->openItemsOnly) {
            $queryParts[] = "Balance > '0'";
        }
        $query = implode(' AND ', $queryParts);

        try {
            $page = 1;
            do {
                $objects = $this->quickBooksApi->query(QuickBooksApi::CREDIT_NOTE, $page, $query);
                foreach ($objects as $object) {
                    yield $this->buildResult($object);
                }
                ++$page;
            } while (0 < count($objects));
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function quickBooksObjectType(): string
    {
        return QuickBooksApi::CREDIT_NOTE;
    }

    public function buildResult(object $transaction): AccountingJsonRecord
    {
        $pdf = $this->readPdfs ? $this->getPdf($transaction->Id) : null;

        return new AccountingJsonRecord($transaction, (object) ['pdf' => $pdf]);
    }

    /**
     * Retrieve the credit memo PDF from QuickBooks Online.
     */
    private function getPdf(string $id): string
    {
        try {
            return $this->quickBooksApi->getCreditMemoAsPdf($id);
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param AccountingJsonRecord $accountingObject
     */
    public function getObjectId(object $accountingObject): string
    {
        return $accountingObject->document->Id;
    }
}
