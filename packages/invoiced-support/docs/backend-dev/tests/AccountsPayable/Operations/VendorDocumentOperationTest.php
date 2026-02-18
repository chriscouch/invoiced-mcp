<?php

namespace App\Tests\AccountsPayable\Operations;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\ApprovalWorkflow;
use App\AccountsPayable\Models\ApprovalWorkflowPath;
use App\AccountsPayable\Models\ApprovalWorkflowStep;
use App\AccountsPayable\Operations\CreateVendorCredit;
use App\AccountsPayable\Operations\EditVendorCredit;
use App\Chasing\Models\Task;
use App\Companies\Models\Member;
use App\Core\Ledger\Ledger;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Mockery;

class VendorDocumentOperationTest extends AppTestCase
{
    private static AccountsPayableLedger $ledger;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasVendor();
        self::$ledger = Mockery::mock(AccountsPayableLedger::class);
        self::$ledger->shouldReceive('getLedger')->andReturn(new Ledger(Mockery::mock(Connection::class), 0, 'usd'));
        self::$ledger->shouldReceive('syncVendorCredit');
    }

    private function makeWorkflow(bool $enabled = false): ApprovalWorkflow
    {
        return self::hasWorkflow($enabled);
    }

    private function makePath(ApprovalWorkflow $workflow, string $rules = ''): ApprovalWorkflowPath
    {
        return self::hasPath($workflow, $rules);
    }

    private function makeStep(ApprovalWorkflowPath $path, int $order = 1, array $members = []): ApprovalWorkflowStep
    {
        return self::hasStep($path, $order, $members);
    }

    private function makeMember(string $index): Member
    {
        return self::hasMember($index);
    }

    private function makeCreateOperation(): CreateVendorCredit
    {
        return new CreateVendorCredit(self::$ledger, self::getService('test.database'));
    }

    private function makeEditOperation(): EditVendorCredit
    {
        return new EditVendorCredit(self::$ledger, self::getService('test.database'));
    }

    private function makeParameters(): array
    {
        return [
            'vendor' => self::$vendor,
            'number' => 'INV-'.uniqid(),
            'date' => CarbonImmutable::now(),
            'currency' => 'usd',
            'total' => 1000,
            'status' => PayableDocumentStatus::PendingApproval,
        ];
    }

    public function testCreate(): void
    {
        $createOperation = $this->makeCreateOperation();
        $parameters = $this->makeParameters();

        $vendorCredit = $createOperation->create($parameters);
        $this->assertEquals(null, $vendorCredit->approval_workflow);
        $this->assertEquals(null, $vendorCredit->approval_workflow_step);

        $workflow = $this->makeWorkflow();
        $path1 = $this->makePath($workflow, 'document.total > 1000 ');
        $step1 = $this->makeStep($path1);
        $path2 = $this->makePath($workflow);
        $step3 = $this->makeStep($path2);
        $step4 = $this->makeStep($path2, 2);

        $parameters = $this->makeParameters();
        $vendorCredit = $createOperation->create($parameters);
        $this->assertEquals(null, $vendorCredit->approval_workflow);
        $this->assertEquals(null, $vendorCredit->approval_workflow_step);

        $workflow->default = true;
        $workflow->saveOrFail();
        $parameters = $this->makeParameters();
        $vendorCredit = $createOperation->create($parameters);
        $this->assertEquals(null, $vendorCredit->approval_workflow);
        $this->assertEquals(null, $vendorCredit->approval_workflow_step);

        $workflow->enabled = true;
        $workflow->saveOrFail();
        $parameters = $this->makeParameters();
        $vendorCredit = $createOperation->create($parameters);
        $this->assertEquals($workflow->id(), $vendorCredit->approval_workflow?->id());
        $this->assertEquals($step3->id(), $vendorCredit->approval_workflow_step?->id());

        $step3->order = 2;
        $step3->saveOrFail();
        $step4->order = 1;
        $step4->saveOrFail();
        $parameters = $this->makeParameters();
        $vendorCredit = $createOperation->create($parameters);
        $this->assertEquals($workflow->id(), $vendorCredit->approval_workflow?->id());
        $this->assertEquals($step4->id(), $vendorCredit->approval_workflow_step?->id());

        $parameters = $this->makeParameters();
        $parameters['total'] = 10000;
        $vendorCredit = $createOperation->create($parameters);
        $this->assertEquals($workflow->id(), $vendorCredit->approval_workflow?->id());
        $this->assertEquals($step1->id(), $vendorCredit->approval_workflow_step?->id());

        $workflow = $this->makeWorkflow();
        $path = $this->makePath($workflow);
        $step = $this->makeStep($path);
        self::$vendor->approval_workflow = $workflow;
        self::$vendor->saveOrFail();
        $parameters = $this->makeParameters();
        $vendorCredit = $createOperation->create($parameters);
        $this->assertEquals($workflow->id(), $vendorCredit->approval_workflow?->id());
        $this->assertEquals($step->id(), $vendorCredit->approval_workflow_step?->id());
    }

    public function testApprovalWorkflowStepNoWorkflow(): void
    {
        $createOperation = $this->makeCreateOperation();
        $parameters = $this->makeParameters();
        $countOfTasks = Task::query()->count();
        $createOperation->create($parameters);
        $this->assertEquals($countOfTasks, Task::query()->count());
    }

    public function testApprovalWorkflowStepNoMembers(): void
    {
        $createOperation = $this->makeCreateOperation();
        $parameters = $this->makeParameters();
        $workflow = $this->makeWorkflow();
        $path = $this->makePath($workflow);
        $step = $this->makeStep($path);
        $parameters['approval_workflow'] = $workflow->id();
        $parameters['approval_workflow_step'] = $step->id();
        $countOfTasks = Task::query()->count();
        $createOperation->create($parameters);
        $this->assertEquals($countOfTasks, Task::query()->count());
    }

    public function testApprovalWorkflowStep(): void
    {
        $createOperation = $this->makeCreateOperation();
        $editOperation = $this->makeEditOperation();
        $parameters = $this->makeParameters();

        $member1 = $this->makeMember('1');
        $member2 = $this->makeMember('2');
        $member3 = $this->makeMember('3');
        $workflow = $this->makeWorkflow(true);
        $path = $this->makePath($workflow);
        $step = $this->makeStep($path, 1, [$member1->id(), $member2->id()]);
        $step2 = $this->makeStep($path, 2, [$member3->id()]);

        $countOfTasks = Task::query()->count();
        $parameters['approval_workflow'] = $workflow->id();
        $parameters['approval_workflow_step'] = $step->id();
        $doc = $createOperation->create($parameters);
        $this->assertEquals($countOfTasks + 2, Task::query()->count());
        /** @var Task $task1 */
        $task1 = Task::queryWithTenant($member1->tenant())
            ->where('user_id', $member1->user_id)
            ->where('action', 'approve_vendor_credit')
            ->where('name', $step->approval_workflow_path->approval_workflow->name.' for '.$doc->number)
            ->one();
        Task::queryWithTenant($member1->tenant())
            ->where('user_id', $member2->user_id)
            ->where('action', 'approve_vendor_credit')
            ->where('name', $step->approval_workflow_path->approval_workflow->name.' for '.$doc->number)
            ->one();
        $task1->complete = true;
        $task1->saveOrFail();

        $parameters['approval_workflow_step'] = $step2;
        $editOperation->edit($doc, $parameters);
        $this->assertEquals($countOfTasks + 2, Task::query()->count());
        Task::findOrFail($task1->id());
        Task::queryWithTenant($member3->tenant())
            ->where('user_id', $member3->user_id)
            ->where('action', 'approve_vendor_credit')
            ->where('name', $step->approval_workflow_path->approval_workflow->name.' for '.$doc->number)
            ->one();

        $editOperation->edit($doc, [
            'total' => 500,
        ]);
        $this->assertEquals($countOfTasks + 2, Task::query()->count());

        $parameters['approval_workflow_step'] = null;
        $editOperation->edit($doc, $parameters);
        $this->assertEquals($countOfTasks + 1, Task::query()->count());
        Task::findOrFail($task1->id());
    }
}
