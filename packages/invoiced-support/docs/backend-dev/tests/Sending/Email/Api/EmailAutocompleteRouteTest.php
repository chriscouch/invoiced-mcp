<?php

namespace App\Tests\Sending\Email\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\Search\Driver\Elasticsearch\ElasticsearchDriver;
use App\Sending\Email\Api\EmailAutocompleteRoute;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class EmailAutocompleteRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testRunFail(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->runRequest('');
    }

    public function testRunFail2(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->runRequest('te');
    }

    private function runRequest(string $term): void
    {
        $request = new Request([], []);
        $request->query->set('term', $term);
        $tenant = self::getService('test.tenant');

        $route = new EmailAutocompleteRoute($tenant, Mockery::mock(ElasticsearchDriver::class));
        self::getService('test.api_runner')->run($route, $request);
    }
}
