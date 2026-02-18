<?php

namespace App\Tests\Core\Entitlements;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\Entitlements\Api\EditFeatureRoute;
use App\Tests\Integrations\Api\IntegrationRouteTest;
use Symfony\Component\HttpFoundation\Request;

class EditFeatureRouteTest extends IntegrationRouteTest
{
    protected function getRoute(Request $request): AbstractApiRoute
    {
        $request->request->set('enabled', true);

        return new EditFeatureRoute(self::getService('test.tenant'));
    }
}
