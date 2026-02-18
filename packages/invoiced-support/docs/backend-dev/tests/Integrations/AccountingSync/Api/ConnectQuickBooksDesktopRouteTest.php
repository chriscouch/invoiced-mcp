<?php

namespace App\Tests\Integrations\AccountingSync\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Integrations\QuickBooksDesktop\Api\ConnectQuickBooksDesktopRoute;
use App\Tests\Integrations\Api\IntegrationRouteTest;
use Symfony\Component\HttpFoundation\Request;

class ConnectQuickBooksDesktopRouteTest extends IntegrationRouteTest
{
    protected function getRoute(Request $request): AbstractApiRoute
    {
        return new ConnectQuickBooksDesktopRoute(self::getService('test.tenant'), self::getService('test.quickbooks_desktop_sync_manager'));
    }
}
