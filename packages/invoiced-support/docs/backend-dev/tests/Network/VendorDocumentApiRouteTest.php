<?php

namespace App\Tests\Network;

use App\AccountsPayable\Api\BillApproveApiRoute;
use App\AccountsPayable\Api\BillRejectApiRoute;
use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Libs\VendorDocumentResolver;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\BillApproval;
use App\AccountsPayable\Models\BillRejection;
use App\AccountsPayable\Operations\EditBill;
use App\Chasing\Models\Task;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\ACLModelRequester;
use App\Network\Command\TransitionDocumentStatus;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class VendorDocumentApiRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasVendor();
        self::hasBill();
    }

    public function testBuildResponse(): void
    {
        $operation = Mockery::mock(EditBill::class);
        $transition = Mockery::mock(TransitionDocumentStatus::class);
        $route = new BillRejectApiRoute($operation, $transition, new VendorDocumentResolver());
        $request = new Request();
        $route->setModel(self::$bill);
        $context = new ApiCallContext($request, [], [
            'description' => null,
        ], $route->getDefinition());

        $m = Mockery::mock(Member::class);
        $m->shouldReceive('relation')->andReturn(new Role());
        $m->shouldReceive('allowed')->andReturn(false);
        $m->shouldReceive('get', 'id');
        ACLModelRequester::set($m);
        try {
            $route->buildResponse($context);
            $this->assertTrue(false, 'No exception thrown');
        } catch (InvalidRequest $e) {
            $this->assertEquals('Only selected users/roles have permissions to do this', $e->getMessage());
        }

        $workflow = self::hasWorkflow();
        $path = self::hasPath($workflow);
        $step = self::hasStep($path);
        $member = self::hasMember('1');
        $member2 = Member::where('role', 'administrator')->one();
        /** @var Role $role */
        $role = Role::where('id', $member->role)->one();
        $role2 = Role::where('id', $member2->role)->one();
        self::$bill->approval_workflow_step = $step;
        self::$bill->saveOrFail();

        ACLModelRequester::set($member);

        BillRejection::query()->delete();
        try {
            $route->buildResponse($context);
            $this->assertTrue(false, 'No exception thrown');
        } catch (InvalidRequest $e) {
            $this->assertEquals('Only selected users/roles have permissions to do this', $e->getMessage());
        }

        $task = new Task();
        $task->name = 'test';
        $task->action = self::$bill->getTaskAction();
        $task->due_date = time();
        $task->user_id = $member->user_id;
        $task->bill = self::$bill;
        $task->saveOrFail();
        $operation->shouldReceive('edit')->once();
        BillRejection::query()->delete();
        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Bill::class, $response);
        $this->assertEquals(1, BillRejection::count());

        $task->delete();
        $role->bills_edit = true;
        $role->saveOrFail();
        $member->relation('role')->refresh();
        $member->role = $role->id;
        $operation->shouldReceive('edit')->once();
        BillRejection::query()->delete();
        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Bill::class, $response);
        $this->assertEquals(1, BillRejection::count());

        $operation->shouldReceive('edit')->once();
        BillRejection::query()->delete();
        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Bill::class, $response);
        $this->assertEquals(1, BillRejection::count());

        // wrong role wrong member
        $step->roles = [$role2->id];
        $step->members = [$member2->id];
        $step->minimum_approvers = 2;
        $step->saveOrFail();
        try {
            $route->buildResponse($context);
            $this->assertTrue(false, 'No exception thrown');
        } catch (InvalidRequest $e) {
            $this->assertEquals('Only selected users/roles have permissions to do this', $e->getMessage());
        }

        // only member match / only role match
        $step->roles = [$role->id];
        $step->members = [$member2->id];
        $step->saveOrFail();
        $operation->shouldReceive('edit')->once();
        BillRejection::query()->delete();
        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Bill::class, $response);
        $this->assertEquals(1, BillRejection::count());

        $step->roles = [$role2->id];
        $step->members = [$member->id];
        $step->saveOrFail();
        $operation->shouldReceive('edit')->once();
        BillRejection::query()->delete();
        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Bill::class, $response);
        $this->assertEquals(1, BillRejection::count());

        $route = new BillApproveApiRoute($operation, $transition, new VendorDocumentResolver());
        $route->setModel(self::$bill);

        BillApproval::query()->delete();
        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Bill::class, $response);
        $this->assertEquals(1, BillApproval::count());

        $networkDocument = self::getTestDataFactory()->createNetworkDocument(self::$company, self::$company);
        self::$bill->network_document = $networkDocument;
        self::$bill->approval_workflow_step = $step;
        self::$bill->saveOrFail();
        $route->setModel(self::$bill);
        $transition->shouldReceive('isTransitionAllowed')->andReturn(true)->once();
        $transition->shouldReceive('performTransition')->once();
        $step->minimum_approvers = 1;
        $step->saveOrFail();
        $operation->shouldReceive('edit')->withArgs(fn ($_, $data) => PayableDocumentStatus::Approved == $data['status'] && null == $data['approval_workflow_step'])->once();
        BillApproval::query()->delete();
        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Bill::class, $response);
        $this->assertEquals(1, BillApproval::count());

        $task = new Task();
        $task->name = 'test1';
        $task->action = self::$bill->getTaskAction();
        $task->due_date = time();
        $task->user_id = $member->user_id;
        $task->bill = self::$bill;
        $task->saveOrFail();

        $task2 = new Task();
        $task2->name = 'test2';
        $task2->action = self::$bill->getTaskAction();
        $task2->due_date = time();
        $task2->user_id = $member2->user_id;
        $task2->bill = self::$bill;
        $task2->saveOrFail();

        $step2 = self::hasStep($path, 2);
        BillApproval::query()->delete();
        $operation->shouldReceive('edit')->withArgs(fn ($_, $data) => $data['approval_workflow_step']->id == $step2->id())->once();
        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Bill::class, $response);
        $this->assertEquals(1, BillApproval::count());
        $this->assertTrue($task->refresh()->complete);
        $this->assertFalse($task2->refresh()->complete);
    }
}
