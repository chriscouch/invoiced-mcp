<?php

namespace App\Tests\Core\Files;

use App\Core\Files\Api\CreateAttachmentRoute;
use App\Core\Files\Models\Attachment;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CreateAttachmentRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasFile();
    }

    /**
     * Tests successful attachment creation.
     */
    public function testCreateAttachment(): void
    {
        $data = [
            'parent_id' => self::$invoice->id,
            'parent_type' => 'invoice',
            'file_id' => self::$file->id,
        ];

        $request = new Request([], $data);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $route = new CreateAttachmentRoute();
        $route->setModelClass(Attachment::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->buildResponse($context);

        $attachment = Attachment::where($data)->oneOrNull();
        $this->assertNotNull($attachment);
        $attachment->delete();
    }

    /**
     * Tests failure when the attachment
     * already exists.
     */
    public function testAlreadyExists(): void
    {
        $data = [
            'parent_id' => self::$invoice->id,
            'parent_type' => 'invoice',
            'file_id' => self::$file->id,
        ];

        $request = new Request([], $data);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $route = new CreateAttachmentRoute();
        $route->setModelClass(Attachment::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->buildResponse($context);

        $route = new CreateAttachmentRoute();
        $route->setModelClass(Attachment::class);
        self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        try {
            $route->buildResponse($context);
            throw new \Exception('buildResponse should throw an error.');
        } catch (\Exception $e) {
            $this->assertEquals('Attachment already exists.', $e->getMessage());
        }

        $attachment = Attachment::where($data)->oneOrNull();
        $this->assertNotNull($attachment);
        $attachment->delete();
    }

    /**
     * Tests failure when the parent
     * provided in the request
     * body doesn't exist.
     */
    public function testNoParent(): void
    {
        $data = [
            'parent_id' => -1,
            'parent_type' => 'invoice',
            'file_id' => self::$file->id,
        ];

        $request = new Request([], $data);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $route = new CreateAttachmentRoute();
        $route->setModelClass(Attachment::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        try {
            $route->buildResponse($context);
            throw new \Exception('buildResponse should throw an error.');
        } catch (\Exception $e) {
            $this->assertEquals("No such parent of type 'invoice' with ID '-1'", $e->getMessage());
        }

        $attachment = Attachment::where($data)->oneOrNull();
        $this->assertNull($attachment);
    }

    /**
     * Tests failure when the file
     * provided in the request
     * body doesn't exist.
     */
    public function testNoFile(): void
    {
        $data = [
            'parent_id' => self::$invoice->id,
            'parent_type' => 'invoice',
            'file_id' => -1,
        ];

        $request = new Request([], $data);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $route = new CreateAttachmentRoute();
        $route->setModelClass(Attachment::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        try {
            $route->buildResponse($context);
            throw new \Exception('buildResponse should throw an error.');
        } catch (\Exception $e) {
            $this->assertEquals('No such file: -1', $e->getMessage());
        }

        $attachment = Attachment::where($data)->oneOrNull();
        $this->assertNull($attachment);
    }

    /**
     * Tests failure when invalid value is
     * provided in the parent_type field of
     * the request body.
     */
    public function testInvalidParentType(): void
    {
        $expectedError = "The option 'parent_type' with value 'not_valid_object' is invalid. Accepted values are: 'credit_note', 'estimate', 'invoice', 'comment', 'payment', 'email', 'customer'.";

        $data = [
            'parent_id' => self::$invoice->id,
            'parent_type' => 'not_valid_object',
            'file_id' => self::$file->id,
        ];

        $request = new Request([], $data);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $route = new CreateAttachmentRoute();
        $route->setModelClass(Attachment::class);

        try {
            $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
            $route->buildResponse($context);
            throw new \Exception('validateRequest should throw an error.');
        } catch (\Exception $e) {
            $this->assertEquals($expectedError, $e->getMessage());
        }

        $attachment = Attachment::where($data)->oneOrNull();
        $this->assertNull($attachment);
    }

    /**
     * Tests failure when invalid value is
     * provided in the location field of
     * the request body.
     */
    public function testInvalidLocation(): void
    {
        $expectedError = "The option 'location' with value 'invalid_location' is invalid. Accepted values are: 'attachment', 'pdf'.";

        $query = [
            'parent_id' => self::$invoice->id,
            'parent_type' => 'invoice',
            'file_id' => self::$file->id,
        ];

        $data = [
            'parent_id' => self::$invoice->id,
            'parent_type' => 'invoice',
            'file_id' => self::$file->id,
            'location' => 'invalid_location',
        ];

        $request = new Request([], $data);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $route = new CreateAttachmentRoute();
        $route->setModelClass(Attachment::class);

        try {
            $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
            $route->buildResponse($context);
            throw new \Exception('buildResponse should throw an error.');
        } catch (\Exception $e) {
            $this->assertEquals($expectedError, $e->getMessage());
        }

        $attachment = Attachment::where($query)->oneOrNull();
        $this->assertNull($attachment);
    }
}
