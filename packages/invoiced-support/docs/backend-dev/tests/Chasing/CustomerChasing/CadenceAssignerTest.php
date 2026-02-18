<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\CustomerChasing\CustomerCadenceAssigner;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Tests\AppTestCase;

class CadenceAssignerTest extends AppTestCase
{
    private static ChasingCadence $defaultCadence;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::$company->features->enable('smart_chasing');

        // add a cadence with invalid syntax
        $cadence5 = new ChasingCadence();
        $cadence5->name = 'Invalid Syntax';
        $cadence5->time_of_day = 7;
        $cadence5->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence5->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_CONDITIONAL;
        $cadence5->assignment_conditions = 'customer.name.includes ("two roads")';
        $cadence5->saveOrFail();

        // add a cadence with valid syntax that produces a type error
        $cadence6 = new ChasingCadence();
        $cadence6->name = 'Invalid Syntax';
        $cadence6->time_of_day = 7;
        $cadence6->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence6->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_CONDITIONAL;
        $cadence6->assignment_conditions = 'customer.name in customer.name';
        $cadence6->saveOrFail();

        $cadence1 = new ChasingCadence();
        $cadence1->name = 'Payment Terms';
        $cadence1->time_of_day = 7;
        $cadence1->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence1->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_CONDITIONAL;
        $cadence1->assignment_conditions = 'customer.payment_terms == "NET 30"';
        $cadence1->saveOrFail();

        $cadence2 = new ChasingCadence();
        $cadence2->name = 'Metadata';
        $cadence2->time_of_day = 7;
        $cadence2->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence2->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_CONDITIONAL;
        $cadence2->assignment_conditions = 'customer.metadata.entity_id == "100"';
        $cadence2->saveOrFail();

        $cadence3 = new ChasingCadence();
        $cadence3->name = 'None';
        $cadence3->time_of_day = 7;
        $cadence3->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence3->saveOrFail();

        $cadence4 = new ChasingCadence();
        $cadence4->name = 'Default';
        $cadence4->time_of_day = 7;
        $cadence4->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence4->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_DEFAULT;
        $cadence4->saveOrFail();
        self::$defaultCadence = $cadence4;
    }

    private function getAssigner(): CustomerCadenceAssigner
    {
        return new CustomerCadenceAssigner(self::$company);
    }

    public function testAssignConditionMatch(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->metadata = (object) [
            'entity_id' => 100,
        ];

        $assigner = $this->getAssigner();

        /** @var ChasingCadence $cadence */
        $cadence = $assigner->assign($customer);

        $this->assertInstanceOf(ChasingCadence::class, $cadence);
        $this->assertEquals('Metadata', $cadence->name);

        $customer->payment_terms = 'NET 30';

        /** @var ChasingCadence $cadence */
        $cadence = $assigner->assign($customer);

        $this->assertInstanceOf(ChasingCadence::class, $cadence);
        $this->assertEquals('Payment Terms', $cadence->name);
    }

    public function testAssignDefaultMatch(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();

        $assigner = $this->getAssigner();

        /** @var ChasingCadence $cadence */
        $cadence = $assigner->assign($customer);

        $this->assertInstanceOf(ChasingCadence::class, $cadence);
        $this->assertEquals('Default', $cadence->name);
    }

    public function testAssignNoMatch(): void
    {
        self::$defaultCadence->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_NONE;
        self::$defaultCadence->saveOrFail();

        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();

        $assigner = $this->getAssigner();
        $this->assertNull($assigner->assign($customer));
    }

    public function testCadenceMatches(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $cadence = new ChasingCadence();
        $cadence->assignment_conditions = 'customer.payment_terms == "NET 30" and customer.country in ["US"]';

        $customer->payment_terms = 'NET 29';
        $customer->country = 'US';

        $assigner = $this->getAssigner();
        $this->assertFalse($assigner->cadenceMatches($cadence, $customer));

        $customer->payment_terms = 'NET 30';
        $this->assertTrue($assigner->cadenceMatches($cadence, $customer));

        // try with a syntax error
        $cadence->assignment_conditions = 'customer.name = "invalid"';
        $this->assertFalse($assigner->cadenceMatches($cadence, $customer));
    }

    public function testCreatingCustomer(): void
    {
        self::$defaultCadence->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_DEFAULT;
        self::$defaultCadence->saveOrFail();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->saveOrFail();

        $this->assertEquals('Default', $customer->chasingCadence()->name); /* @phpstan-ignore-line */
        $this->assertTrue($customer->chase);
        $this->assertNotNull($customer->next_chase_step);

        $customer2 = new Customer();
        $customer2->name = 'Test';
        $customer2->country = 'US';
        $customer2->payment_terms = 'NET 30';
        $customer2->saveOrFail();

        $this->assertEquals('Payment Terms', $customer2->chasingCadence()->name); /* @phpstan-ignore-line */
        $this->assertTrue($customer2->chase);
        $this->assertNotNull($customer2->next_chase_step);

        $customer3 = new Customer();
        $customer3->name = 'Test';
        $customer3->country = 'US';
        $customer3->metadata = (object) ['entity_id' => '100'];
        $customer3->saveOrFail();

        $this->assertEquals('Metadata', $customer3->chasingCadence()->name); /* @phpstan-ignore-line */
        $this->assertTrue($customer3->chase);
        $this->assertNotNull($customer3->next_chase_step);

        // test an update
        self::$defaultCadence->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_NONE;
        self::$defaultCadence->saveOrFail();

        $customer4 = new Customer();
        $customer4->name = 'Test';
        $customer4->country = 'US';
        $customer4->metadata = (object) ['entity_id' => '120'];
        $customer4->saveOrFail();

        $this->assertNull($customer4->chasingCadence());
        $this->assertTrue($customer4->chase);

        $customer4->metadata = (object) ['entity_id' => '100'];
        $customer4->saveOrFail();
        $this->assertEquals('Metadata', $customer4->chasingCadence()->name); /* @phpstan-ignore-line */
        $this->assertTrue($customer4->chase);
        $this->assertNotNull($customer4->next_chase_step);

        // disabling chasing should not assign cadence
        $customer5 = new Customer();
        $customer5->name = 'Test';
        $customer5->country = 'US';
        $customer5->chase = false;
        $customer5->payment_terms = 'NET 30';
        $customer5->saveOrFail();

        $this->assertNull($customer5->chasingCadence());
        $this->assertFalse($customer5->chase);
    }
}
