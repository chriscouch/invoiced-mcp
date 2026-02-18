<?php

namespace App\Integrations\QuickBooksOnline\Libs;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthConnectionManager;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

/**
 * Makes calls to the QuickBooks Online REST API.
 */
class QuickBooksApi implements StatsdAwareInterface
{
    use IntegrationLogAwareTrait;
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    // more info: https://developer.intuit.com/docs/00_quickbooks_online/2_build/20_explore_the_quickbooks_online_api/80_minor_versions
    const MINOR_VERSION = '75';

    // QuickBooks Online object types
    const CUSTOMER = 'Customer';
    const CREDIT_NOTE = 'CreditMemo';
    const INVOICE = 'Invoice';
    const ITEM = 'Item';
    const PAYMENT = 'Payment';
    private QuickBooksAccount $account;
    private ?GuzzleClient $httpClient = null;
    private array $paymentMethods = [];
    private array $items = [];
    private array $terms = [];

    public function __construct(
        private OAuthConnectionManager $oauthManager,
        private QuickBooksOAuth $oauth,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
        private string $environment
    ) {
    }

    public function setAccount(QuickBooksAccount $account): void
    {
        $this->account = $account;

        // reset the local state
        $this->httpClient = null;
        $this->paymentMethods = [];
        $this->items = [];
        $this->terms = [];
    }

    /**
     * Returns the QB account used by this API client.
     */
    public function getAccount(): QuickBooksAccount
    {
        return $this->account;
    }

    /**
     * Builds an HTTP client.
     */
    public function getHttpClient(): GuzzleClient
    {
        if ($this->httpClient) {
            return $this->httpClient;
        }

        $retryDecider = function (
            $retries,
            Request $request,
            ?Response $response = null,
            ?Throwable $exception = null
        ) {
            // Limit the number of retries to 3
            if ($retries >= 3) {
                return false;
            }

            // Retry connection exceptions
            if ($exception instanceof ConnectException) {
                return true;
            }

            // Retry on server errors
            if ($exception instanceof ServerException) {
                return true;
            }

            // Retry on rate-limited requests
            if ($response && 429 == $response->getStatusCode()) {
                return true;
            }

            return false;
        };

        $retryDelay = fn ($numberOfRetries) => 30000 * $numberOfRetries;

        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($retryDecider, $retryDelay));

        $logFormatter = new MessageFormatter(MessageFormatter::DEBUG);
        $handlerStack->push(Middleware::log($this->getLogger(), $logFormatter));

        $this->httpClient = new Client([
            'handler' => $handlerStack,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        return $this->httpClient;
    }

    public function getLogger(): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = $this->makeIntegrationLogger('quickbooks', $this->account->tenant(), $this->cloudWatchLogsClient, $this->debugContext);
        }

        return $this->logger;
    }

    //
    // GET API Endpoints
    //

    /**
     * Retrieves an object from QuickBooks Online.
     *
     * @throws IntegrationApiException
     */
    public function get(string $object, string $id): stdClass
    {
        $lower = strtolower($object);
        $result = $this->jsonRequest('GET', "/$lower/".$id);

        return $result->$object;
    }

    /**
     * Gets objects of a given type.
     *
     * @throws IntegrationApiException
     */
    public function query(string $object, int $page, string $queryWhere = ''): array
    {
        $query = "SELECT * FROM $object";
        if ($queryWhere) {
            $query .= " WHERE $queryWhere";
        }

        $perPage = 100;
        $start = 1 + (($page - 1) * $perPage);
        $query .= " STARTPOSITION $start MAXRESULTS $perPage";

        $result = $this->performQuery($query);

        return $result->QueryResponse->$object ?? [];
    }

    /**
     * Gets the connected company's name.
     *
     * @throws IntegrationApiException
     */
    public function getCompanyInfo(): stdClass
    {
        $result = $this->jsonRequest('GET', '/companyinfo/'.$this->account->realm_id);

        return $result->CompanyInfo;
    }

    /**
     * Gets a customer for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getCustomer(string $id): stdClass
    {
        $result = $this->jsonRequest('GET', '/customer/'.$id);

        return $result->Customer;
    }

    /**
     * Queries a QuickBooks customer object with a specific name.
     *
     * @throws IntegrationApiException
     */
    public function getCustomerByName(string $name): ?stdClass
    {
        $name = $this->escapeQueryValue($name);
        $query = "SELECT * FROM Customer WHERE DisplayName='$name'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->Customer[0] ?? null;
    }

    /**
     * Gets an invoice for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getInvoice(string $id): stdClass
    {
        $result = $this->jsonRequest('GET', '/invoice/'.$id);

        return $result->Invoice;
    }

    /**
     * Gets an invoice as a PDF for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getInvoiceAsPdf(string $id): string
    {
        $response = $this->request('GET', '/invoice/'.$id.'/pdf', [], [], 'application/pdf');

        return $response->getBody();
    }

    /**
     * Gets a credit memo as a PDF for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getCreditMemoAsPdf(string $id): string
    {
        $response = $this->request('GET', '/creditmemo/'.$id.'/pdf', [], [], 'application/pdf');

        return $response->getBody();
    }

    /**
     * Gets the chart of accounts for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getChartOfAccounts(int $startPosition = 1, int $perPage = 1000): array
    {
        $query = "SELECT * FROM Account WHERE Active = true STARTPOSITION $startPosition MAXRESULTS $perPage";
        $result = $this->performQuery($query);

        return $result->QueryResponse->Account ?? [];
    }

    /**
     * Attempts to retrieve a QBO credit memo object from QBO.
     *
     * @throws IntegrationApiException
     */
    public function getCreditMemo(string $id): stdClass
    {
        $result = $this->jsonRequest('GET', '/creditmemo/'.$id);

        return $result->CreditMemo;
    }

    /**
     * Queries QuickBooks for a credit memo with a specific number.
     *
     * @throws IntegrationApiException
     */
    public function getCreditMemoByNumber(string $number): ?stdClass
    {
        $query = "SELECT * FROM CreditMemo WHERE DocNumber = '$number'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->CreditMemo[0] ?? null;
    }

    /**
     * Gets the tax codes for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getTaxCodes(int $perPage = 1000): array
    {
        $query = "SELECT * FROM TaxCode STARTPOSITION 1 MAXRESULTS $perPage";
        $result = $this->performQuery($query);

        return $result->QueryResponse->TaxCode ?? [];
    }

    /**
     * Queries QuickBooks for an invoice with a specific number.
     *
     * @throws IntegrationApiException
     */
    public function getInvoiceByNumber(string $number): ?stdClass
    {
        $query = "SELECT * FROM Invoice WHERE DocNumber = '$number'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->Invoice[0] ?? null;
    }

    /**
     * Queries QuickBooks for a tax code with a specific name.
     *
     * @throws IntegrationApiException
     */
    public function getTaxCode(string $name): ?stdClass
    {
        $name = $this->escapeQueryValue($name);
        $query = "SELECT * FROM TaxCode WHERE Name = '$name'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->TaxCode[0] ?? null;
    }

    /**
     * Queries a QuickBooks Class object by name.
     *
     * @throws IntegrationApiException
     */
    public function getClass(string $name): ?stdClass
    {
        $name = $this->escapeQueryValue($name);
        $query = "SELECT * FROM Class WHERE Name = '$name'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->Class[0] ?? null;
    }

    /**
     * Queries a QuickBooks Department object by name.
     *
     * @throws IntegrationApiException
     */
    public function getDepartment(string $name): ?stdClass
    {
        $name = $this->escapeQueryValue($name);
        $query = "SELECT * FROM Department WHERE Name = '$name'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->Department[0] ?? null;
    }

    /**
     * Queries a QuickBooks exchange rate object.
     *
     * @throws IntegrationApiException
     */
    public function getExchangeRate(string $currency, string $date): stdClass
    {
        $query = [
            'sourcecurrencycode' => $currency,
            'asofdate' => $date,
        ];

        $response = $this->jsonRequest('GET', '/exchangerate', $query);

        return $response->ExchangeRate;
    }

    /**
     * Gets a term with a given ID.
     *
     * @throws IntegrationApiException
     */
    public function getTerm(string $id): stdClass
    {
        if (isset($this->terms[$id])) {
            return $this->terms[$id];
        }

        $result = $this->jsonRequest('GET', '/term/'.$id);

        // cache the result
        $this->terms[$id] = $result->Term;

        return $this->terms[$id];
    }

    /**
     * Queries a QuickBooks term object with a specific name.
     *
     * @throws IntegrationApiException
     */
    public function getTermByName(string $name): ?stdClass
    {
        $name = $this->escapeQueryValue($name);
        $query = "SELECT * FROM Term WHERE Name='$name'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->Term[0] ?? null;
    }

    /**
     * Queries a QuickBooks item object with a specific name.
     *
     * @throws IntegrationApiException
     */
    public function getItemByName(string $name): ?stdClass
    {
        $name = $this->escapeQueryValue($name);
        $query = "SELECT * FROM Item WHERE Name='$name'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->Item[0] ?? null;
    }

    /**
     * Queries a QuickBooks account object with a specific name.
     *
     * @throws IntegrationApiException
     */
    public function getAccountByName(string $name): ?stdClass
    {
        $name = $this->escapeQueryValue($name);
        $query = "SELECT * FROM Account WHERE Name='$name'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->Account[0] ?? null;
    }

    /**
     * Performs a query against QB.
     * Returns response in JSON -> stdClass.
     *
     * @throws IntegrationApiException
     */
    public function performQuery(string $query): stdClass
    {
        return $this->jsonRequest('GET', '/query', ['query' => $query]);
    }

    /**
     * Gets the preferences for a given QuickBooks account.
     *
     * @throws IntegrationApiException
     */
    public function getPreferences(): stdClass
    {
        $result = $this->jsonRequest('GET', '/preferences');

        return $result->Preferences;
    }

    /**
     * Gets a payment for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getPayment(string $id): stdClass
    {
        $result = $this->jsonRequest('GET', '/payment/'.$id);

        return $result->Payment;
    }

    /**
     * Gets a payment method for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getPaymentMethod(string $id): stdClass
    {
        if (isset($this->paymentMethods[$id])) {
            return $this->paymentMethods[$id];
        }

        $result = $this->jsonRequest('GET', '/paymentmethod/'.$id);

        // cache the result
        $this->paymentMethods[$id] = $result->PaymentMethod;

        return $this->paymentMethods[$id];
    }

    /**
     * Queries QBO for a PaymentMethod object with the given name.
     *
     * @throws IntegrationApiException
     */
    public function getPaymentMethodByName(string $name): ?stdClass
    {
        $name = $this->escapeQueryValue($name);
        $query = "SELECT * FROM PaymentMethod WHERE Name = '$name'";
        $result = $this->performQuery($query);

        return $result->QueryResponse->PaymentMethod[0] ?? null;
    }

    /**
     * Gets an item for a given quickbooks account.
     *
     * @throws IntegrationApiException
     */
    public function getItem(string $id): stdClass
    {
        if (isset($this->items[$id])) {
            return $this->items[$id];
        }

        $result = $this->jsonRequest('GET', '/item/'.$id);

        // cache the result
        $this->items[$id] = $result->Item;

        return $this->items[$id];
    }

    //
    // POST API Endpoints
    //

    /**
     * Creates a customer on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function createCustomer(array $params): stdClass
    {
        $result = $this->jsonRequest('POST', '/customer', [], $params);

        return $result->Customer;
    }

    /**
     * Creates a credit memo on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function createCreditMemo(array $params): stdClass
    {
        $result = $this->jsonRequest('POST', '/creditmemo', [], $params);

        return $result->CreditMemo;
    }

    /**
     * Creates an Invoice on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function createInvoice(array $params): stdClass
    {
        $result = $this->jsonRequest('POST', '/invoice', [], $params);

        return $result->Invoice;
    }

    /**
     * Creats a Term object on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function createTerm(array $params): stdClass
    {
        $result = $this->jsonRequest('POST', '/term', [], $params);

        return $result->Term;
    }

    /**
     * Creates an Item object in QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function createItem(array $params): stdClass
    {
        $result = $this->jsonRequest('POST', '/item', [], $params);

        return $result->Item;
    }

    /**
     * Creates a Payment object in QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function createPayment(array $params): ?stdClass
    {
        $result = $this->jsonRequest('POST', '/payment', [], $params);

        // Sometimes the create payment request is successful but the "Payment" element
        // is missing in the response. This appears to be a bug in the QuickBooks API.
        return $result->Payment ?? null;
    }

    /**
     * Creates a PaymentMethod object in QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function createPaymentMethod(array $params): stdClass
    {
        $result = $this->jsonRequest('POST', '/paymentmethod', [], $params);

        return $result->PaymentMethod;
    }

    /**
     * Updates a customer on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function updateCustomer(string $id, string $syncToken, array $params): stdClass
    {
        $params['Id'] = $id;
        $params['SyncToken'] = $syncToken;
        $result = $this->jsonRequest('POST', '/customer', [], $params);

        return $result->Customer;
    }

    /**
     * Updates a credit memo on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function updateCreditMemo(string $id, string $syncToken, array $params): stdClass
    {
        $params['Id'] = $id;
        $params['SyncToken'] = $syncToken;
        $result = $this->jsonRequest('POST', '/creditmemo', [], $params);

        return $result->CreditMemo;
    }

    /**
     * Updates an invoice on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function updateInvoice(string $id, string $syncToken, array $params): stdClass
    {
        $params['Id'] = $id;
        $params['SyncToken'] = $syncToken;
        $result = $this->jsonRequest('POST', '/invoice', [], $params);

        return $result->Invoice;
    }

    /**
     * Updates a payment on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function updatePayment(string $id, string $syncToken, array $params): stdClass
    {
        $params['Id'] = $id;
        $params['SyncToken'] = $syncToken;
        $result = $this->jsonRequest('POST', '/payment', [], $params);

        return $result->Payment;
    }

    /**
     * Voids an invoice on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function voidInvoice(string $id, string $syncToken): ?stdClass
    {
        $query = ['operation' => 'void'];
        $body = [
            'Id' => $id,
            'SyncToken' => $syncToken,
        ];

        $result = $this->jsonRequest('POST', '/invoice', $query, $body);

        return $result->Invoice;
    }

    /**
     * Voids a payment on QuickBooks.
     *
     * @throws IntegrationApiException
     */
    public function voidPayment(string $id, string $syncToken): ?stdClass
    {
        $query = [
            'operation' => 'update',
            'include' => 'void',
        ];
        $body = [
            'Id' => $id,
            'SyncToken' => $syncToken,
            'sparse' => true,
        ];

        $result = $this->jsonRequest('POST', '/payment', $query, $body);

        return $result->Payment;
    }

    //
    // Helpers
    //

    /**
     * Gets the QuickBooks API endpoint.
     */
    private function getApiUrl(): string
    {
        $realmId = $this->account->realm_id;
        if ('production' !== $this->environment) {
            return "https://sandbox-quickbooks.api.intuit.com/v3/company/$realmId";
        }

        return "https://quickbooks.api.intuit.com/v3/company/$realmId";
    }

    /**
     * Performs an authenticated request to the QuickBooks API and returns JSON response.
     *
     * @throws IntegrationApiException
     */
    private function jsonRequest(string $method, string $endpoint, array $queryParams = [], array $body = []): stdClass
    {
        $response = $this->request($method, $endpoint, $queryParams, $body, 'application/json');

        if ($error = $this->parseResponseError($response)) {
            throw new IntegrationApiException($error);
        }

        return json_decode((string) $response->getBody());
    }

    /**
     * Adds backslashes to special characters in string for
     * escaping in query request.
     */
    private function escapeQueryValue(string $value): string
    {
        return str_replace(['\'', '-'], ['\\\'', '\\-'], $value);
    }

    /**
     * Performs an authenticated request to the QuickBooks API.
     *
     * @throws IntegrationApiException when the call fails
     */
    private function request(string $method, string $endpoint, array $queryParams = [], array $body = [], string $accept = 'application/json'): ResponseInterface
    {
        $client = $this->getHttpClient();

        $options = [];

        $queryParams['minorversion'] = self::MINOR_VERSION;

        if ('POST' == $method) {
            $options['json'] = $body;
        }

        $url = $this->getApiUrl().$endpoint;
        $url .= '?'.http_build_query($queryParams);

        try {
            $this->oauthManager->refresh($this->oauth, $this->account);
        } catch (OAuthException $e) {
            throw new IntegrationApiException($e->getMessage(), $e->getCode(), $e);
        }

        $options['headers'] = [
            'Accept' => $accept,
            'Authorization' => 'Bearer '.$this->account->access_token,
        ];

        $this->statsd->increment('quickbooks_online.api_call');

        try {
            $response = $client->request($method, $url, $options);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();

            $statusCode = $response->getStatusCode();
            if (401 == $statusCode) {
                throw new IntegrationApiException('The credentials we have were rejected by QuickBooks. Please reconnect your account and try again.', 0, $e);
            } elseif (429 == $statusCode) {
                throw new IntegrationApiException('We were unable to process your request because of the QuickBooks Online API rate limit. Please retry your request again later.');
            }

            $errorDetail = $this->parseResponseError($response, true) ?? 'An unknown error has occurred when communicating with the QuickBooks Online API.';

            throw new IntegrationApiException($errorDetail, 0, $e);
        } catch (Exception $e) {
            $this->logger->error('Exception when communicating with QBO', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error has occurred when communicating with the QuickBooks Online API.');
        }

        return $response;
    }

    /**
     * Parses error detail from a bad QBO response.
     */
    private function parseResponseError(ResponseInterface $response, bool $isBadResponse = false): ?string
    {
        $body = null;
        $contentType = $response->getHeader('Content-Type')[0] ?? '';
        $contentTypeValues = array_map('trim', explode(';', $contentType));

        // parse response body
        if ('application/json' === $contentTypeValues[0]) {
            $body = json_decode((string) $response->getBody());
        } elseif ('application/xml' === $contentTypeValues[0]) {
            $body = simplexml_load_string((string) $response->getBody());
        }

        // verify QBO error structure
        $errors = $body->Fault->Error ?? null;
        if (!$errors) {
            return $isBadResponse ? 'An unknown error has occurred when parsing the response from QuickBooks Online' : null;
        }

        // build errors
        $parsedErrors = [];
        foreach ($errors as $error) {
            $message = (string) $error->Message;
            $field = (string) ($error->element ?? null);
            $detail = (string) $error->Detail;
            $code = (string) $error->code;

            $formattedError = $message."\n";
            $formattedError .= $field ? "Field: $field\n" : '';
            $formattedError .= "Detail: $detail\n";
            $formattedError .= "Code: $code";

            $parsedErrors[] = $formattedError;
        }

        return implode("\n\n", $parsedErrors);
    }
}
