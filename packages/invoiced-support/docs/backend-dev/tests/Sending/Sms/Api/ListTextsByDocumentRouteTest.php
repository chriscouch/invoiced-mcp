<?php

namespace App\Tests\Sending\Sms\Api;

use App\Core\RestApi\Libs\ApiCache;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\InfuseUtility as Utility;
use App\Core\Utils\SimpleCache;
use App\Sending\Sms\Api\ListTextsByDocumentRoute;
use App\Sending\Sms\Models\TextMessage;
use App\Tests\AppTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class ListTextsByDocumentRouteTest extends AppTestCase
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
        // create two texts for an invoice
        $this->buildTextMessage(ObjectType::Invoice, (int) self::$invoice->id());
        $this->buildTextMessage(ObjectType::Invoice, (int) self::$invoice->id());

        $response = $this->runRequest('invoice', (int) self::$invoice->id());
        $this->assertEquals(2, count($response));
    }

    private function runRequest(string $documentType, int $documentId): array
    {
        $request = new Request([], []);
        $request->attributes->set('document_type', $documentType);
        $request->attributes->set('document_id', $documentId);

        $route = new ListTextsByDocumentRoute(new ApiCache(new ArrayAdapter(), new SimpleCache(new ArrayAdapter())));
        $route->setModelClass(TextMessage::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);

        return $route->buildResponse($context);
    }

    private function buildTextMessage(ObjectType $documentType, int $documentId): TextMessage
    {
        $textMessage = new TextMessage();
        $textMessage->id = strtolower(Utility::guid(false));
        $textMessage->state = 'sent';
        $textMessage->to = '+12345678900';
        $textMessage->message = 'Test message';
        $textMessage->twilio_id = 'twilio_id';
        $textMessage->related_to_type = $documentType->value;
        $textMessage->related_to_id = $documentId;
        $textMessage->saveOrFail();

        return $textMessage;
    }
}
