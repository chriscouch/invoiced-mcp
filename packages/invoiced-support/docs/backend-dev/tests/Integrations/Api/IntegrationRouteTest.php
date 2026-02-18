<?php

namespace App\Tests\Integrations\Api;

use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\Orm\ACLModelRequester;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

abstract class IntegrationRouteTest extends AppTestCase
{
    abstract protected function getRoute(Request $request): AbstractApiRoute;

    public function testPermissions(): void
    {
        self::hasCompany();
        $role = new Role();
        $role->name = 'test';
        $role->saveOrFail();
        $member = Member::query()->one();
        $member->role = $role->id;
        $member->saveOrFail();
        $requester = ACLModelRequester::get();
        ACLModelRequester::set($member);

        $request = new Request();
        $request->attributes->set('id', 'test');

        $runner = self::getService('test.api_runner');
        $route = $this->getRoute($request);
        $definition = $route->getDefinition();
        try {
            $runner->checkPermissions($definition);
            $context = $runner->validateRequest($request, $route->getDefinition());
            $route->buildResponse($context);
            $this->assertTrue(false, 'No exception thrown');
        } catch (InvalidRequest $e) {
            $this->assertEquals('You do not have permission to do that', $e->getMessage());
        }
        $role->settings_edit = true;
        $role->saveOrFail();
        $member->role = $role->id;
        $member->setRelation('role', $role);
        $route = $this->getRoute($request);
        try {
            $runner->checkPermissions($definition);
            $context = $runner->validateRequest($request, $route->getDefinition());
            $route->buildResponse($context);
            $this->assertTrue(false, 'No exception thrown');
        } catch (\Exception $e) {
            $this->assertNotEquals('You do not have permission to do that', $e->getMessage());
        }

        ACLModelRequester::set($requester);
        $this->assertTrue(true);
    }
}
