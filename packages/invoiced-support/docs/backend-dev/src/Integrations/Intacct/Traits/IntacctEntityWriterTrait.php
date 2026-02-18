<?php

namespace App\Integrations\Intacct\Traits;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use Intacct\Functions\AbstractFunction;

trait IntacctEntityWriterTrait
{
    /**
     * @param AbstractFunction $request
     * @param IntacctAccount $intacctAccount
     * @param IntacctSyncProfile $syncProfile
     * @param Invoice|Payment|CreditNote $model
     * @return string
     * @throws IntegrationApiException
     */
    protected function createObjectWithEntityHandling(
        AbstractFunction $request,
        IntacctAccount $intacctAccount,
        IntacctSyncProfile $syncProfile,
        Invoice|Payment|CreditNote $model
    ): string {
        if ($intacctAccount->sync_all_entities) {
            $entity = $this->getIntacctEntity($model);
            if ($entity) {
                return $this->intacctApi->createObjectInEntity($request, $entity);
            }

            return $this->intacctApi->createTopLevelObject($request);
        }

        return $this->intacctApi->createObject($request);
    }
}