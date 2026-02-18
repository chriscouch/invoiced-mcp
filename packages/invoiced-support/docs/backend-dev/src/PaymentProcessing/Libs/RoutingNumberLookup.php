<?php

namespace App\PaymentProcessing\Libs;

use App\Core\Orm\Exception\ModelException;
use App\PaymentProcessing\Models\RoutingNumber;
use ICanBoogie\Inflector;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Looks up bank information given an ABA routing number.
 * (U.S. only).
 */
class RoutingNumberLookup
{
    private const API_URL = 'https://www.routingnumbers.info/api/data.json';

    private const TEST_BANKS = [
        '110000000' => 'Invoiced Test Bank',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $environment,
    ) {
    }

    /**
     * Looks up details about a given routing number.
     */
    public function lookup(string $routingNumber): ?RoutingNumber
    {
        if (!preg_match('/^[\d]{9}$/', $routingNumber)) {
            return null;
        }

        // check test account numbers
        if (isset(self::TEST_BANKS[$routingNumber])) {
            return new RoutingNumber([
                'routing_Number' => $routingNumber,
                'bank_name' => self::TEST_BANKS[$routingNumber],
            ]);
        }

        // check for a memorized routing number
        $routingNumberInfo = RoutingNumber::where('routing_Number', $routingNumber)->oneOrNull();
        if ($routingNumberInfo) {
            return $routingNumberInfo;
        }

        // query the API
        $result = $this->queryApi($routingNumber);
        if (!$result) {
            return null;
        }

        // format issuing bank
        if (isset($result['customer_name'])) {
            $inflector = Inflector::get();
            $bankName = $inflector->titleize($result['customer_name']);
        } else {
            $bankName = '';
        }

        // memorize the result in the database
        $routingNumberInfo = new RoutingNumber();
        $routingNumberInfo->routing_number = $routingNumber;
        $routingNumberInfo->bank_name = $bankName;

        try {
            $routingNumberInfo->saveOrFail();
        } catch (ModelException) {
            // if there is a unique constraint violation then
            // that means the routing number data is already cached
            // and can be safely ignored
        }

        return $routingNumberInfo;
    }

    /**
     * Queries a routing number using the routingnumbers.info API.
     */
    private function queryApi(string $routingNumber): ?array
    {
        if ('test' == $this->environment) {
            return null;
        }

        $query = [
            'rn' => $routingNumber,
        ];

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => $query,
            ]);

            $result = $response->toArray();
        } catch (Throwable) {
            return null;
        }

        // something went wrong
        if (isset($result['error'])) {
            return null;
        }

        return $result;
    }
}
