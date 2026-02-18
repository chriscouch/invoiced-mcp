<?php

namespace App\Tests\Sending\Email\Api;

use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\Api\RetrieveInboxThreadByDocumentRoute;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\Inbox;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class RetrieveInboxThreadByDocumentRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasEstimate();
        self::hasCreditNote();
    }

    public function testRun(): void
    {
        $inbox = Inbox::one();

        $result = $this->runRequest($inbox, 'invoice', self::$invoice->id);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('{}', $result->getContent(), '{}');
        $result = $this->runRequest($inbox, 'estimate', self::$estimate->id);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('{}', $result->getContent());
        $result = $this->runRequest($inbox, 'credit_note', self::$creditNote->id);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('{}', $result->getContent());

        $threadInvoice = new EmailThread();
        $threadInvoice->inbox = $inbox;
        $threadInvoice->name = 'Thread Invoice';
        $threadInvoice->related_to_type = ObjectType::Invoice;
        $threadInvoice->related_to_id = self::$invoice->id;
        $threadInvoice->saveOrFail();

        $threadNote = new EmailThread();
        $threadNote->inbox = $inbox;
        $threadNote->name = 'Thread Credit Note';
        $threadNote->related_to_type = ObjectType::CreditNote;
        $threadNote->related_to_id = self::$creditNote->id;
        $threadNote->saveOrFail();

        $threadEstimate = new EmailThread();
        $threadEstimate->inbox = $inbox;
        $threadEstimate->name = 'Thread Estimate';
        $threadEstimate->related_to_type = ObjectType::Estimate;
        $threadEstimate->related_to_id = self::$estimate->id;
        $threadEstimate->saveOrFail();

        /** @var EmailThread $result */
        $result = $this->runRequest($inbox, 'invoice', self::$invoice->id);
        $this->assertEquals(self::$invoice->id, $result->related_to_id);

        /** @var EmailThread $result */
        $result = $this->runRequest($inbox, 'estimate', self::$estimate->id);
        $this->assertEquals(self::$estimate->id, $result->related_to_id);

        /** @var EmailThread $result */
        $result = $this->runRequest($inbox, 'credit_note', self::$creditNote->id);
        $this->assertEquals(self::$creditNote->id, $result->related_to_id);
    }

    /**
     * @return EmailThread|JsonResponse
     */
    private function runRequest(Inbox $inbox, string $documentType, int $documentId): object
    {
        $request = new Request([], []);
        $request->attributes->set('inbox_id', $inbox->id);
        $request->attributes->set('document_type', $documentType);
        $request->attributes->set('document_id', $documentId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $route = new RetrieveInboxThreadByDocumentRoute();
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        return $route->buildResponse($context);
    }
}
