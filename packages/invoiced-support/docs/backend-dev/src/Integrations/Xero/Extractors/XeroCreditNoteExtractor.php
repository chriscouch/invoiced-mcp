<?php

namespace App\Integrations\Xero\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Xero\Models\XeroSyncProfile;
use Generator;
use App\Core\Orm\Model;

class XeroCreditNoteExtractor extends AbstractXeroExtractor
{
    private bool $readPdfs;

    protected function xeroObjectType(): string
    {
        return 'CreditNotes';
    }

    /**
     * @param XeroSyncProfile $syncProfile
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        parent::initialize($account, $syncProfile);
        $this->readPdfs = $syncProfile->read_pdfs;
    }

    /**
     * @param XeroSyncProfile $syncProfile
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        $headers = [];
        if ($lastSynced = $query->lastSynced) {
            $headers['If-Modified-Since'] = $lastSynced->toDateTimeLocalString();
        }

        $where = ['Type=="ACCRECCREDIT"'];
        if ($date = $query->startDate) {
            $date = $date->format('Y, m, d');
            $where[] = "Date >= DateTime($date)";
        }
        if ($date = $query->endDate) {
            $date = $date->format('Y, m, d');
            $where[] = "Date <= DateTime($date)";
        }
        if ($query->openItemsOnly) {
            $where[] = 'Status=="AUTHORISED"';
        }

        $params = [
            'where' => implode(' AND ', $where),
        ];

        // Xero by default always returns unit cost as 2 digits. There is a query parameter
        // to get a 4 digit unit cost from Xero.
        $unitCostPrecision = $syncProfile->tenant()->accounts_receivable_settings->unit_cost_precision;
        if (4 == $unitCostPrecision) {
            $params['unitdp'] = 4;
        }

        try {
            $page = 1;
            do {
                $params['page'] = $page;
                $objects = $this->xeroApi->getMany('CreditNotes', $params, $headers);
                foreach ($objects as $object) {
                    yield $this->buildResult($object);
                }
                ++$page;
            } while (0 < count($objects));
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getObject(string $objectId): AccountingJsonRecord
    {
        return $this->buildResult($this->getXeroObject($objectId));
    }

    public function buildResult(object $transaction): AccountingJsonRecord
    {
        $pdf = $this->readPdfs ? $this->getPdf($transaction->CreditNoteID) : null;

        return new AccountingJsonRecord($transaction, (object) ['pdf' => $pdf]);
    }

    /**
     * Retrieves the credit note PDF from Xero.
     */
    private function getPdf(string $id): string
    {
        try {
            return $this->xeroApi->getPdf('CreditNotes', $id);
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param AccountingJsonRecord $accountingObject
     */
    public function getObjectId(AccountingRecordInterface $accountingObject): string
    {
        return $accountingObject->document->CreditNoteID;
    }
}
