<?php

namespace App\Tests\Sending\Mail\Api;

use App\Core\RestApi\Libs\ApiCache;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\InfuseUtility as Utility;
use App\Core\Utils\SimpleCache;
use App\Sending\Mail\Api\ListLettersByDocumentRoute;
use App\Sending\Mail\Models\Letter;
use App\Tests\AppTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class ListLettersByDocumentRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    public function testEmptyResponse(): void
    {
        $response = $this->runRequest('invoice', 1);
        $this->assertEquals([], $response);
    }

    public function testNonEmptyResponse(): void
    {
        // create two letters for an invoice
        $this->buildLetter(ObjectType::Invoice, (int) self::$invoice->id());
        $this->buildLetter(ObjectType::Invoice, (int) self::$invoice->id());

        $response = $this->runRequest('invoice', (int) self::$invoice->id());
        $this->assertEquals(2, count($response));
    }

    private function runRequest(string $documentType, int $documentId): array
    {
        $request = new Request([], []);
        $request->attributes->set('document_type', $documentType);
        $request->attributes->set('document_id', $documentId);

        $route = new ListLettersByDocumentRoute(new ApiCache(new ArrayAdapter(), new SimpleCache(new ArrayAdapter())));
        $route->setModelClass(Letter::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);

        return $route->buildResponse($context);
    }

    private function buildLetter(ObjectType $documentType, int $documentId): Letter
    {
        $letter = new Letter();
        $letter->id = strtolower(Utility::guid(false));
        $letter->state = 'sent';
        $letter->to = 'Address';
        $letter->num_pages = 1;
        $letter->expected_delivery_date = 1629821216;
        $letter->lob_id = 'lob_id';
        $letter->related_to_type = $documentType->value;
        $letter->related_to_id = $documentId;
        $letter->saveOrFail();

        return $letter;
    }
}
