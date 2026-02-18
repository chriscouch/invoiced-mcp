<?php

namespace App\Tests\Integrations\AccountingSync\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Integrations\QuickBooksDesktop\Api\StopSyncRoute;
use App\Tests\Integrations\Api\IntegrationRouteTest;
use Symfony\Component\HttpFoundation\Request;

class StopSyncRouteTest extends IntegrationRouteTest
{
    protected function getRoute(Request $request): AbstractApiRoute
    {
        return new StopSyncRoute(self::getService('test.tenant'), self::getService('test.quickbooks_desktop_sync_manager'));
    }
}
