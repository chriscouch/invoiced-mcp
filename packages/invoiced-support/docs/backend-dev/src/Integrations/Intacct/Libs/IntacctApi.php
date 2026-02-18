<?php

namespace App\Integrations\Intacct\Libs;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TransferException;
use Intacct\ClientConfig;
use Intacct\Exception\IntacctException;
use Intacct\Exception\ResponseException;
use Intacct\Functions\AbstractFunction;
use Intacct\Functions\AccountsReceivable\CustomerCreate;
use Intacct\Functions\AccountsReceivable\CustomerUpdate;
use Intacct\Functions\Common\NewQuery\Query;
use Intacct\Functions\Common\NewQuery\QueryFilter\Filter;
use Intacct\Functions\Common\NewQuery\QuerySelect\Field;
use Intacct\Functions\Common\Query\QueryString;
use Intacct\Functions\Common\Read;
use Intacct\Functions\Common\ReadByQuery;
use Intacct\Functions\Common\ReadMore;
use Intacct\OnlineClient;
use Intacct\Xml\Response\Result;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

class IntacctApi implements StatsdAwareInterface
{
    use IntegrationLogAwareTrait;
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private const OBJECT_GL_ACCOUNT = 'GLACCOUNT';
    private const OBJECT_CHECKING_ACCOUNT = 'CHECKINGACCOUNT';
    private const OBJECT_CUSTOMER = 'CUSTOMER';
    private const OBJECT_AR_INVOICE_ITEM = 'ARINVOICEITEM';
    private const OBJECT_ORDER_ENTRY_TRANSACTION_DEFINITION = 'SODOCUMENTPARAMS';
    private const OBJECT_ORDER_ENTRY_TRANSACTION = 'SODOCUMENT';
    private const OBJECT_AR_ADJUSTMENT = 'ARADJUSTMENT';
    private const OBJECT_AR_ADJUSTMENT_ITEM = 'ARADJUSTMENTITEM';
    private const OBJECT_AR_PAYMENT = 'arpayment';
    private const OBJECT_ENTITY = 'LOCATIONENTITY';

    private IntacctAccount $account;
    private OnlineClient $intacctClient;
    private OnlineClient $intacctClientTopLevel;

    public function __construct(
        private string $intacctSenderId,
        private string $intacctSenderPassword,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext
    ) {
    }

    public function setAccount(IntacctAccount $account): void
    {
        $this->account = $account;

        // clear local state
        unset($this->intacctClient);
        unset($this->intacctClientTopLevel);
    }

    /**
     * Returns the Intacct account used by this API client.
     */
    public function getAccount(): IntacctAccount
    {
        return $this->account;
    }

    public function getLogger(): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = $this->makeIntegrationLogger('intacct', $this->account->tenant(), $this->cloudWatchLogsClient, $this->debugContext);
        }

        return $this->logger;
    }

    /**
     * Builds an Intacct client to connect at the entity level, if used, or
     * the top level if single entity.
     */
    public function getIntacctClient(): OnlineClient
    {
        if (isset($this->intacctClient)) {
            return $this->intacctClient;
        }

        $this->intacctClient = new OnlineClient($this->makeClientConfig($this->account->entity_id));

        return $this->intacctClient;
    }

    /**
     * Builds an Intacct client to connect to the top level.
     */
    public function getIntacctClientTopLevel(): OnlineClient
    {
        if (isset($this->intacctClientTopLevel)) {
            return $this->intacctClientTopLevel;
        }

        $this->intacctClientTopLevel = new OnlineClient($this->makeClientConfig(null));

        return $this->intacctClientTopLevel;
    }

    /**
     * Builds an Intacct client to connect to the top level.
     */
    public function getIntacctClientForEntity(string $entity): OnlineClient
    {
        return new OnlineClient($this->makeClientConfig($entity));
    }

    /**
     * Gets the parameters for the intacct client.
     */
    private function makeClientConfig(?string $entity): ClientConfig
    {
        $config = new ClientConfig();

        // If the user has supplied their own sender ID then we will
        // use that instead of our sender ID.
        if ($senderId = $this->account->sender_id) {
            $config->setSenderId($senderId);
            $config->setSenderPassword((string) $this->account->sender_password);
        } else {
            $config->setSenderId($this->intacctSenderId);
            $config->setSenderPassword($this->intacctSenderPassword);
        }

        // Add in multi-entity support as per:
        // https://github.com/Intacct/intacct-sdk-php/issues/135
        $companyID = $this->account->intacct_company_id;
        if ($entity) {
            $companyID .= '|'.$entity;
        }
        $config->setCompanyId($companyID);

        $config->setUserId($this->account->user_id);
        $config->setUserPassword($this->account->user_password);
        $config->setLogger($this->getLogger());

        return $config;
    }

    //
    // API Endpoints
    //

    /**
     * Gets the customer list.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getCustomers(bool $topLevel, array $fields = [], string $query = ''): Result
    {
        $query = new QueryString($query);

        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_CUSTOMER);
        $readByQuery->setQuery($query);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        if ($topLevel) {
            return $this->executeFunctionTopLevel($readByQuery);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets a customer by number.
     *
     * @param string[] $fields
     *
     * @throws IntegrationApiException
     */
    public function getCustomerByNumber(string $number, bool $topLevel, array $fields, string $entity = null, bool $syncAllEntities = false): ?SimpleXMLElement
    {
        $query = new Query();
        $query->setSelect(array_map(fn (string $field) => new Field($field), $fields));
        $query->setFrom(self::OBJECT_CUSTOMER);
        $query->setFilter((new Filter('CUSTOMERID'))->equalTo($number));

        if ($syncAllEntities) {
            if ($entity) {
                $result = $this->executeFunctionForEntity($query, $entity);
            } else {
                $result = $this->executeFunctionTopLevel($query);
            }
        } elseif ($topLevel) {
            $result = $this->executeFunctionTopLevel($query);
        } else {
            $result = $this->executeFunction($query);
        }

        return $result->getData()[0] ?? null;
    }

    /**
     * Gets a customer for a given customer record no. Can
     * retrieve from entity or top level.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getCustomer(string $id, bool $topLevel, array $fields = []): SimpleXMLElement
    {
        $query = new QueryString("RECORDNO = '$id'");

        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_CUSTOMER);
        $readByQuery->setQuery($query);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        if ($topLevel) {
            $result = $this->executeFunctionTopLevel($readByQuery);
        } else {
            $result = $this->executeFunction($readByQuery);
        }

        if (1 != $result->getCount()) {
            throw new IntegrationApiException('Could not load Intacct customer: '.$id);
        }

        return $result->getData()[0];
    }

    /**
     * Gets the line items for an Intacct A/R invoice.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getArInvoiceLines(string $invoiceId, array $fields = []): Result
    {
        $query = new QueryString("RECORDKEY = '$invoiceId'");

        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_AR_INVOICE_ITEM);
        $readByQuery->setQuery($query);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets the types of order entry transaction definitions.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getOrderEntryTransactionDefinitions(array $fields = []): Result
    {
        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_ORDER_ENTRY_TRANSACTION_DEFINITION);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets order entry transactions.
     *
     * @param string $documentType document type, e.g. Sales Invoice
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getOrderEntryTransactions(string $documentType, array $fields = [], string $queryStr = ''): Result
    {
        $query = new QueryString($queryStr);

        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_ORDER_ENTRY_TRANSACTION);
        $readByQuery->setDocParId($documentType);
        $readByQuery->setQuery($query);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets an order entry transaction.
     *
     * @param string $documentType document type, e.g. Sales Invoice
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getOrderEntryTransaction(string $documentType, string $id, array $fields = []): SimpleXMLElement
    {
        $result = $this->getOrderEntryTransactionsByIds($documentType, [$id], $fields);
        if (1 != $result->getCount()) {
            throw new IntegrationApiException('Could not load Intacct order entry transaction: '.$documentType.'-'.$id);
        }

        return $result->getData()[0];
    }

    /**
     * Gets multiple order entry transactions by a given list of IDs.
     *
     * @param string $documentType document type, e.g. Sales Invoice
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getOrderEntryTransactionsByIds(string $documentType, array $ids, array $fields = []): Result
    {
        $read = new Read();
        $read->setObjectName(self::OBJECT_ORDER_ENTRY_TRANSACTION);
        $read->setDocParId($documentType);
        $read->setKeys($ids);

        if (count($fields) > 0) {
            $read->setFields($fields);
        }

        return $this->executeFunction($read);
    }

    /**
     * Gets the PR Record Key (usually the A/R invoice record number) for a
     * given Intacct order entry transaction.
     *
     * @param string $documentType document type, e.g. Sales Invoice
     * @param string $number       document number, e.g. INV-00001
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getOrderEntryTransactionPrRecordKey(string $documentType, string $number): string
    {
        $query = new QueryString("DOCNO = '$number'");

        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_ORDER_ENTRY_TRANSACTION);
        $readByQuery->setDocParId($documentType);
        $readByQuery->setQuery($query);
        $readByQuery->setFields(['PRRECORDKEY']);

        $result = $this->executeFunction($readByQuery);
        if (1 != $result->getCount()) {
            throw new IntegrationApiException("Could not find Intacct order entry document: $documentType-$number");
        }

        return (string) $result->getData()[0]->{'PRRECORDKEY'};
    }

    /**
     * Gets the PDF for an Intacct order entry invoice.
     *
     * @param string $documentType document type, e.g. Sales Invoice
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getOrderEntryPdf(string $documentType, string $documentId): string
    {
        $retrievePdf = new RetrievePdf();
        $retrievePdf->setDocId("$documentType-$documentId");

        $result = $this->executeFunction($retrievePdf);

        // The result is a base64 encoded PDF in an XML element
        return base64_decode($result->getData()[0]->{'pdfdata'});
    }

    /**
     * Gets A/R adjustments from Intacct.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getArAdjustments(array $fields = [], string $queryStr = ''): Result
    {
        $query = new QueryString($queryStr);
        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_AR_ADJUSTMENT);
        $readByQuery->setQuery($query);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets A/R adjustment from Intacct.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getArAdjustment(string $id, array $fields = []): SimpleXMLElement
    {
        $query = new QueryString("RECORDNO = $id");

        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_AR_ADJUSTMENT);
        $readByQuery->setQuery($query);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        $result = $this->executeFunction($readByQuery);
        if (1 != $result->getCount()) {
            throw new IntegrationApiException('Could not load Intacct adjustment: '.$id);
        }

        return $result->getData()[0];
    }

    /**
     * Obtains A/R Adjustment line items for an adjustment.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getArAdjustmentLines(string $adjustmentId, array $fields = []): Result
    {
        $queryStr = "RECORDKEY = '".$adjustmentId."'";
        $query = new QueryString($queryStr);

        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_AR_ADJUSTMENT_ITEM);
        $readByQuery->setQuery($query);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets the posted payments.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getPayments(array $fields = [], string $queryAddon = '', int $pageSize = 1000): Result
    {
        $queryString = "RECORDTYPE = 'rp'";
        if ($queryAddon) {
            $queryString .= ' AND '.$queryAddon;
        }
        $query = new QueryString($queryString);

        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_AR_PAYMENT);
        $readByQuery->setPageSize($pageSize);
        $readByQuery->setQuery($query);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets a payment.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getPayment(string $id, array $fields = []): SimpleXMLElement
    {
        $result = $this->getPaymentsByIds([$id], $fields);
        if (1 != $result->getCount()) {
            throw new IntegrationApiException('Could not load Intacct payment: '.$id);
        }

        return $result->getData()[0];
    }

    /**
     * Gets multiple payment given a list of IDs.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getPaymentsByIds(array $ids, array $fields = []): Result
    {
        $read = new Read();
        $read->setObjectName(self::OBJECT_AR_PAYMENT);
        $read->setKeys($ids);

        if (count($fields) > 0) {
            $read->setFields($fields);
        }

        return $this->executeFunction($read);
    }

    /**
     * Gets the chart of accounts.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getChartOfAccounts(array $fields = []): Result
    {
        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_GL_ACCOUNT);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets the checking accounts.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getCheckingAccounts(array $fields = []): Result
    {
        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_CHECKING_ACCOUNT);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets the entities in a multi-entity Sage account.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getEntities(array $fields = []): Result
    {
        $readByQuery = new ReadByQuery();
        $readByQuery->setObjectName(self::OBJECT_ENTITY);

        if (count($fields) > 0) {
            $readByQuery->setFields($fields);
        }

        return $this->executeFunction($readByQuery);
    }

    /**
     * Gets the next page of a previous query result.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function getMore(string $resultId): Result
    {
        $readMore = new ReadMore();
        $readMore->setResultId($resultId);

        return $this->executeFunction($readMore);
    }

    //
    // Writes
    //

    /**
     * Creates an object given an Intacct function object. The
     * result is the created object record number.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function createObject(AbstractFunction $function): string
    {
        $result = $this->executeFunction($function);

        return $result->getKey();
    }

    /**
     * Creates an object on the top level given an Intacct function object. The
     * result is the created object record number.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function createTopLevelObject(AbstractFunction $function): string
    {
        $result = $this->executeFunctionTopLevel($function);

        if ($function instanceof CustomerCreate || $function instanceof CustomerUpdate) {
            return $result->getData()[0]->{'RECORDNO'};
        }

        return $result->getKey();
    }

    /**
     * Creates an object on the specified entity given an Intacct function object. The
     * result is the created object record number.
     *
     * @throws IntegrationApiException when the request fails
     */
    public function createObjectInEntity(AbstractFunction $function, string $entity): string
    {
        $result = $this->executeFunctionForEntity($function, $entity);

        if ($function instanceof CustomerCreate || $function instanceof CustomerUpdate) {
            return $result->getData()[0]->{'RECORDNO'};
        }

        return $result->getKey();
    }

    //
    // Helpers
    //

    /**
     * Executes an Intacct API function on the entity-level if multi-entity or
     * top level if single entity.
     *
     * @throws IntegrationApiException
     */
    private function executeFunction(AbstractFunction $function): Result
    {
        return $this->execute($this->getIntacctClient(), $function);
    }

    /**
     * Executes an Intacct API function on the top level.
     *
     * @throws IntegrationApiException
     */
    private function executeFunctionTopLevel(AbstractFunction $function): Result
    {
        return $this->execute($this->getIntacctClientTopLevel(), $function);
    }

    /**
     * Executes an Intacct API function on the specified entity.
     *
     * @throws IntegrationApiException
     */
    private function executeFunctionForEntity(AbstractFunction $function, string $entity): Result
    {
        return $this->execute($this->getIntacctClientForEntity($entity), $function);
    }

    /**
     * Executes an Intacct API function.
     *
     * @throws IntegrationApiException
     */
    private function execute(OnlineClient $intacct, AbstractFunction $function): Result
    {
        $this->statsd->increment('intacct.api_call');

        try {
            return $intacct->execute($function)->getResult();
        } catch (BadResponseException $e) {
            throw $this->handleBadResponse($e);
        } catch (ResponseException $e) {
            throw $this->handleResponseException($e);
        } catch (IntacctException $e) {
            throw $this->handleIntacctException($e);
        } catch (TransferException $e) {
            throw $this->handleTransferException($e);
        }
    }

    /**
     * Generates an Intacct API exception from an Intacct exception.
     */
    private function handleIntacctException(IntacctException $e): IntegrationApiException
    {
        return new IntegrationApiException($e->getMessage(), 0, $e);
    }

    /**
     * Generates an Intacct API exception from an Intacct response exception.
     * NOTE: this is distinct from the 4xx errors that can be thrown by Guzzle.
     */
    public function handleResponseException(ResponseException $e): IntegrationApiException
    {
        $msg = implode("\n", $e->getErrors());

        return new IntegrationApiException($msg, 0, $e);
    }

    /**
     * Generates an Intacct API exception from a guzzle bad response (status code > 400).
     */
    private function handleBadResponse(BadResponseException $e): IntegrationApiException
    {
        $response = $e->getResponse();
        if (401 == $response->getStatusCode()) {
            return new IntegrationApiException('Intacct could not authenticate the provided company ID. Please verify the credentials you have supplied are correct.');
        }

        return new IntegrationApiException($e->getMessage(), 0, $e);
    }

    /**
     * This is a catch-all Guzzle exception handler. It is a last resort used
     * in instances where there is no response body, like a connection error.
     */
    private function handleTransferException(TransferException $e): IntegrationApiException
    {
        $message = $e->getMessage() ?: 'An unknown error occurred when communicating with Intacct';

        return new IntegrationApiException($message, 0, $e);
    }
}
