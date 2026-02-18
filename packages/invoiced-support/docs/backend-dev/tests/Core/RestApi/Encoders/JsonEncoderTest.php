<?php

namespace App\Tests\Core\RestApi\Encoders;

use App\Core\RestApi\Encoders\JsonEncoder;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class JsonEncoderTest extends AppTestCase
{
    private function getEncoder(Request $request): JsonEncoder
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new JsonEncoder($requestStack);
    }

    public function testGetJsonParams(): void
    {
        $encoder = $this->getEncoder(new Request());
        $this->assertEquals(JSON_INVALID_UTF8_SUBSTITUTE, $encoder->getJsonParams());
        $encoder->prettyPrint();
        $this->assertEquals(JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, $encoder->getJsonParams());
        $encoder->compactPrint();
        $this->assertEquals(JSON_INVALID_UTF8_SUBSTITUTE, $encoder->getJsonParams());
    }

    public function testEncode(): void
    {
        $encoder = $this->getEncoder(new Request());

        $response = new Response();

        $result = [
            'answer' => 42,
            'nested' => [
                'id' => 10,
                'name' => 'John Appleseed',
            ],
        ];

        $response = $encoder->encode($result, $response);

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        // JSON should be compact by default
        $expected = '{"answer":42,"nested":{"id":10,"name":"John Appleseed"}}';
        $this->assertEquals($expected, $response->getContent());
    }

    public function testEncodePrettyCurl(): void
    {
        $encoder = $this->getEncoder(Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'curl/7.47.0']));

        $response = new Response();

        $result = [
            'answer' => 42,
            'nested' => [
                'id' => 10,
                'name' => 'John Appleseed',
            ],
        ];

        $response = $encoder->encode($result, $response);

        // JSON should be pretty printed
        $expected = '{
    "answer": 42,
    "nested": {
        "id": 10,
        "name": "John Appleseed"
    }
}';
        $this->assertEquals($expected, $response->getContent());
    }

    public function testEncodeError(): void
    {
        $encoder = $this->getEncoder(new Request());

        $response = new Response();

        // An invalid UTF8 sequence
        $text = "\xB1\x31";
        $response = $encoder->encode([$text], $response);

        $this->assertEquals('["\ufffd1"]', $response->getContent());
    }
}
