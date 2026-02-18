<?php

namespace App\Integrations\BusinessCentral\Writers;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\PaymentRoute;
use App\Integrations\AccountingSync\Writers\AbstractPaymentWriter;
use App\Integrations\AccountingSync\WriteSync\PaymentAccountMatcher;
use App\Integrations\BusinessCentral\BusinessCentralApi;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\OAuth\Models\OAuthAccount;
use App\Core\Orm\Model;

class BusinessCentralPaymentWriter extends AbstractPaymentWriter
{
    public function __construct(
        private BusinessCentralApi $businessCentralApi,
    ) {
    }

    /**
     * @param OAuthAccount $account
     */
    protected function performCreate(Payment $payment, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $integration = $syncProfile->integration;
        $customerMapping = AccountingCustomerMapping::findForCustomer($customer, $integration);
        if (!$customerMapping) {
            return;
        }

        $baseParams = [
            'customerId' => $customerMapping->accounting_id,
            'customerNumber' => $customer->number,
            'postingDate' => date('Y-m-d', $payment->date),
            'documentNumber' => (string) $payment->id,
            'externalDocumentNumber' => substr((string) $payment->reference, 0, 20),
            'comment' => $this->buildPaymentComment($payment),
        ];

        $toCreate = [];
        $appliedTo = $this->buildEnrichedAppliedTo($payment, $integration);
        foreach ($appliedTo as $lineItem) {
            // Only applying invoice line items are supported by Business Central
            if (PaymentItemType::Invoice == $lineItem['type']) {
                $toCreate = array_merge($toCreate, $this->makeJournalLineInvoices($lineItem, $baseParams));
            }
        }

        if (!$toCreate) {
            return;
        }

        $firstResultId = null;
        $journalId = $this->getJournalId($syncProfile, $payment);
        foreach ($toCreate as $params) {
            try {
                $params['journalId'] = $journalId;
                $result = $this->businessCentralApi->createCustomerPayment($account, $params);

                if (!$firstResultId) {
                    $firstResultId = $result->id;
                }
            } catch (IntegrationApiException $e) {
                throw new SyncException($e->getMessage());
            }
        }

        if ($firstResultId) {
            $this->savePaymentMapping($payment, $integration, $firstResultId);
        }
    }

    /**
     * @param OAuthAccount $account
     */
    protected function performUpdate(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void
    {
        $customer = $payment->customer();
        if (!$customer) {
            return;
        }

        // This only works if the customer payment journal is not posted yet.
        $customerPayments = $this->getCustomerPayments($account, $syncProfile, $payment);
        if (!$customerPayments) {
            return;
        }

        // Remove existing customer payments
        foreach ($customerPayments as $customerPayment) {
            try {
                $this->businessCentralApi->deleteCustomerPayment($account, $customerPayment->journalId, $customerPayment->id);
            } catch (IntegrationApiException $e) {
                throw new SyncException($e->getMessage());
            }
        }

        // Delete the mapping
        $paymentMapping->deleteOrFail();

        // Re-create the payment
        $this->performCreate($payment, $customer, $account, $syncProfile);
    }

    /**
     * @param OAuthAccount $account
     */
    protected function performVoid(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void
    {
        // This only works if the customer payment journal is not posted yet.
        try {
            $customerPayments = $this->getCustomerPayments($account, $syncProfile, $payment);
            foreach ($customerPayments as $customerPayment) {
                $this->businessCentralApi->deleteCustomerPayment($account, $customerPayment->journalId, $customerPayment->id);
            }
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @throws IntegrationApiException|SyncException
     */
    private function getCustomerPayments(OAuthAccount $account, AccountingSyncProfile $syncProfile, Payment $payment): array
    {
        $journalId = $this->getJournalId($syncProfile, $payment);

        return $this->businessCentralApi->getCustomerPayments($account, $journalId, ['$filter' => "documentNumber eq '".$payment->id."'"]);
    }

    private function buildPaymentComment(Payment $payment): string
    {
        $comment = 'Method: '.$payment->method;

        if ($charge = $payment->charge) {
            $comment .= "\nGateway: ".$charge->gateway;
            if ($source = $charge->payment_source) {
                $comment .= "\nSource: ".$source->toString();
            }
        }

        if ($paymentNotes = $payment->notes) {
            $comment .= "\nNotes: $paymentNotes";
        }

        return substr(trim($comment), 0, 250);
    }

    /**
     * @throws SyncException
     */
    private function getJournalId(AccountingSyncProfile $syncProfile, Payment $payment): string
    {
        $route = PaymentRoute::fromPayment($payment);
        $router = new PaymentAccountMatcher($syncProfile->payment_accounts);
        $result = $router->match($route);

        return $result->account;
    }

    private function makeJournalLineInvoices(array $lineItem, array $baseParams): array
    {
        if (!$lineItem['invoiceMapping']) {
            return [];
        }

        return [
            array_merge($baseParams, [
                'amount' => $lineItem['amount']->negated()->toDecimal(),
                'appliesToInvoiceId' => $lineItem['invoiceMapping']->accounting_id,
                'appliesToInvoiceNumber' => $lineItem['invoice']->number,
            ]),
        ];
    }
}
