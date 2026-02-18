<?php

namespace App\Integrations\Intacct\Extractors;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use Carbon\CarbonImmutable;
use Generator;
use Intacct\Xml\Response\Result;
use App\Core\Orm\Model;

abstract class AbstractIntacctExtractor implements ExtractorInterface
{
    public function __construct(
        protected IntacctApi $intacctApi
    ) {
    }

    /**
     * @param IntacctAccount $account
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->intacctApi->setAccount($account);
    }

    /**
     * @param AccountingXmlRecord $accountingObject
     */
    public function getObjectId(AccountingRecordInterface $accountingObject): string
    {
        return (string) $accountingObject->document->{'RECORDNO'};
    }

    /**
     * Sets the API client (for testing).
     */
    public function setClient(IntacctApi $client): void
    {
        $this->intacctApi = $client;
    }

    protected function applyReadCursor(array $queryParts, IntacctSyncProfile $syncProfile, CarbonImmutable $lastSynced): array
    {
        // INVD-3240: We cannot trust the WHENMODIFIED value from Intacct
        // in the case that there is a batch import or invoice run. The reason
        // is these records are committed inside a database transaction that
        // may run for several minutes. However, the WHENMODIFIED timestamp is
        // not set to when the record is committed. It is set to the timestamp
        // when it was generated within the transaction. This can result in us
        // missing some transactions created at the beginning of the batch.
        // In order to counteract this we are going to push the read cursor
        // back 1 hour (but not before sync profile created date). The downside
        // of this approach is that we are potentially reprocessing data that
        // was already synced by the integration.
        $lastModified = $lastSynced->subHour()
            ->max(CarbonImmutable::createFromTimestamp($syncProfile->created_at))
            ->format('m/d/Y H:i:s');
        $queryParts[] = "WHENMODIFIED >= '$lastModified'";

        return $queryParts;
    }

    /**
     * Given a result will fetch any additional pages
     * of data from Intacct and emit it using a generator.
     *
     * @param callable|null $resultFn conversion function called to convert each individual result into a generator
     *
     * @throws IntegrationApiException
     */
    protected function resultToGenerator(Result $result, ?callable $resultFn = null): Generator
    {
        if (!$resultFn) {
            $resultFn = function (Result $result) {
                foreach ($result->getData() as $row) {
                    yield new AccountingXmlRecord($row);
                }
            };
        }

        // Fetch the first page
        yield from $resultFn($result);

        // Fetch additional pages
        while ($result->getNumRemaining()) {
            $result = $this->intacctApi->getMore($result->getResultId());
            yield from $resultFn($result);
        }
    }
}
