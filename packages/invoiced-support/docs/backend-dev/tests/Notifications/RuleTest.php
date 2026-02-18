<?php

namespace App\Tests\Notifications;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\Notifications\ValueObjects\Condition;
use App\Notifications\ValueObjects\Evaluate;
use App\Notifications\ValueObjects\Rule;
use App\Tests\AppTestCase;

class RuleTest extends AppTestCase
{
    public function testUserId(): void
    {
        $condition1 = new Condition('user_id', 'equal', 50, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 4, 'whoa' => 'test'], 'user_id' => 50]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testUserIdFail(): void
    {
        $condition1 = new Condition('user_id', 'equal', 2, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 4, 'whoa' => 'test'], 'user_id' => 60]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testAll(): void
    {
        $condition1 = new Condition('object.test', 'equal', 4, false);
        $condition2 = new Condition('object.whoa', 'equal', 'test', false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 4, 'whoa' => 'test']]);

        $rule = new Rule('all', [$condition1, $condition2]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testAllFail(): void
    {
        $condition1 = new Condition('object.test', 'equal', 4, false);
        $condition2 = new Condition('object.whoa', 'equal', 'test', false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 2, 'whoa' => 'test123']]);

        $rule = new Rule('all', [$condition1, $condition2]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testPropertyComparison(): void
    {
        $condition1 = new Condition('object.test', 'equal', 'previous.test');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceUpdated->value, 'object' => (object) ['test' => 'invoiced'], 'previous' => (object) ['test' => 'invoiced']]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testPropertyComparisonFail(): void
    {
        $condition1 = new Condition('object.test', 'equal', 'previous.test');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceUpdated->value, 'object' => (object) ['test' => 'invoiced'], 'previous' => (object) ['test' => 'doesnotequal']]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testNesting(): void
    {
        $condition1 = new Condition('object.test.nesting.further', 'equal', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceUpdated->value, 'object' => (object) ['test' => ['nesting' => ['further' => 4]]]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testNestingFalse(): void
    {
        $condition1 = new Condition('object.test.nesting.further', 'equal', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceUpdated->value, 'object' => (object) ['test' => ['nesting' => ['further' => 2]]]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testAny(): void
    {
        $condition1 = new Condition('object.test', 'equal', 4, false);
        $condition2 = new Condition('object.whoa', 'equal', 'test', false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 4, 'whoa' => '123123']]);

        $rule = new Rule('any', [$condition1, $condition2]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testAnyFail(): void
    {
        $condition1 = new Condition('object.test', 'equal', 4, false);
        $condition2 = new Condition('object.whoa', 'equal', 'test', false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 2, 'whoa' => '123123']]);

        $rule = new Rule('any', [$condition1, $condition2]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testDoesNotEqual(): void
    {
        $condition1 = new Condition('object.test', 'doesNotEqual', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 2]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testDoesNotEqualFail(): void
    {
        $condition1 = new Condition('object.test', 'doesNotEqual', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 4]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testGreaterThan(): void
    {
        $condition1 = new Condition('object.test', 'greaterThan', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 6]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testGreaterThanFail(): void
    {
        $condition1 = new Condition('object.test', 'greaterThan', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 2]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testGreaterThanOrEqual(): void
    {
        $condition1 = new Condition('object.test', 'greaterThanOrEqualTo', 4, false);
        $condition2 = new Condition('object.test2', 'greaterThanOrEqualTo', 2, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 6, 'test2' => 2]]);

        $rule = new Rule('all', [$condition1, $condition2]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testGreaterThanOrEqualFail(): void
    {
        $condition1 = new Condition('object.test', 'greaterThanOrEqualTo', 4, false);
        $condition2 = new Condition('object.test2', 'greaterThanOrEqualTo', 2, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 2, 'test2' => 1]]);

        $rule = new Rule('all', [$condition1, $condition2]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testLessThan(): void
    {
        $condition1 = new Condition('object.test', 'lessThan', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 2]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testLessThanFail(): void
    {
        $condition1 = new Condition('object.test', 'lessThan', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 6]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testLessThanOrEqualTo(): void
    {
        $condition1 = new Condition('object.test', 'lessThanOrEqualTo', 4, false);
        $condition2 = new Condition('object.test2', 'lessThanOrEqualTo', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 2, 'test2' => 4]]);

        $rule = new Rule('all', [$condition1, $condition2]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testLessThanOrEqualToFail(): void
    {
        $condition1 = new Condition('object.test', 'lessThanOrEqualTo', 4, false);
        $condition2 = new Condition('object.test2', 'lessThanOrEqualTo', 4, false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 6, 'test2' => 5]]);

        $rule = new Rule('all', [$condition1, $condition2]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testContains(): void
    {
        $condition1 = new Condition('object.test', 'contains', 'in', false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 'invoiced']]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testContainsFail(): void
    {
        $condition1 = new Condition('object.test', 'contains', 'in', false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 'test123123123']]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testDoesNotContain(): void
    {
        $condition1 = new Condition('object.test', 'doesNotContain', 'doesnotcontainthis', false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 'invoiced']]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testDoesNotContainFail(): void
    {
        $condition1 = new Condition('object.test', 'doesNotContain', 'in', false);

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 'invoiced']]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testIsSet(): void
    {
        $condition1 = new Condition('object.test', 'isSet');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 'should be set']]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testIsSetFail(): void
    {
        $condition1 = new Condition('object.test', 'isSet');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) []]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testIsNotSet(): void
    {
        $condition1 = new Condition('object.test', 'isNotSet');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) []]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testIsNotSetFail(): void
    {
        $condition1 = new Condition('object.test', 'isNotSet');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => 'test']]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testIsSetNesting(): void
    {
        $condition1 = new Condition('object.test.nesting.further', 'isSet');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => ['nesting' => ['further' => '123']]]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testIsSetNestingFail(): void
    {
        $condition1 = new Condition('object.test.nesting.further', 'isSet');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => ['nesting' => ['test' => '123']]]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }

    public function testIsNotSetNesting(): void
    {
        $condition1 = new Condition('object.test.nesting.further', 'isNotSet');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => ['nesting' => []]]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertTrue($evaluate->evaluate());
    }

    public function testIsNotSetNestingFail(): void
    {
        $condition1 = new Condition('object.test.nesting.further', 'isNotSet');

        $event = new Event(['id' => uniqid(), 'type' => EventType::InvoiceCreated->value, 'object' => (object) ['test' => ['nesting' => ['further' => '123']]]]);

        $rule = new Rule('all', [$condition1]);
        $evaluate = new Evaluate($rule, $event);
        $this->assertFalse($evaluate->evaluate());
    }
}
