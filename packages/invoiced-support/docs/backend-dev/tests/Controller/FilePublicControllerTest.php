<?php

namespace App\Tests\Controller;

use App\Core\Files\Api\RetrieveFileFromS3Route;
use App\Core\Files\Models\File;
use App\EntryPoint\Controller\FilePublicController;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilePublicControllerTest extends AppTestCase
{
    public function testFileRetrieveNoHashProvided(): void
    {
        self::hasCompany();

        $file = new File();
        $file->create([
            'name' => 'something.pdf',
            'size' => 100000,
            'type' => 'application/pdf',
            'url' => 'https://files.invoiced.com/somehash',
            'bucket_name' => 'bucket_name',
            'bucket_region' => 'us-1',
            'key' => 'somekey',
            's3_environment' => 'test',
        ]);

        $controller = new FilePublicController();
        $route = Mockery::mock(RetrieveFileFromS3Route::class);

        $request = new Request();
        $response = $controller->retrieveS3File($route, $request);
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->getContent());

        self::$company->delete();
    }

    public function testFileRetrieveNoFileExists(): void
    {
        $controller = new FilePublicController();
        $route = Mockery::mock(RetrieveFileFromS3Route::class);

        $request = new Request();
        $request->query->set('key', 'somekey');
        $this->expectException(\Exception::class);
        $controller->retrieveS3File($route, $request);

        self::$company->delete();
    }

    public function testFileRetrieve(): void
    {
        self::hasCompany();

        $file = new File();
        $file->create([
            'name' => 'something.pdf',
            'size' => 100000,
            'type' => 'application/pdf',
            'url' => 'https://files.invoiced.com/somehash',
            'bucket_name' => 'bucket_name',
            'bucket_region' => 'us-1',
            'key' => 'somekey',
            's3_environment' => 'test',
        ]);

        $mockedResponse = Mockery::mock(StreamedResponse::class);

        $controller = new FilePublicController();
        $route = Mockery::mock(RetrieveFileFromS3Route::class);
        $route->shouldReceive('getFile')->andReturn($mockedResponse);

        $request = new Request();
        $request->query->set('key', 'somekey');
        $response = $controller->retrieveS3File($route, $request);
        $this->assertInstanceOf(StreamedResponse::class, $response);

        self::$company->delete();
    }
}
