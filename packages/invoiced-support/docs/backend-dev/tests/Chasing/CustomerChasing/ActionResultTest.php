<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\Chasing\ValueObjects\ActionResult;
use App\Tests\AppTestCase;

class ActionResultTest extends AppTestCase
{
    public function testActionResult(): void
    {
        $actionResult = new ActionResult(true);
        $this->assertNull($actionResult->getMessage());
        $this->assertTrue($actionResult->isSuccessful());

        $actionResult = new ActionResult(false, 'fail');
        $this->assertFalse($actionResult->isSuccessful());
        $this->assertEquals('fail', $actionResult->getMessage());
    }
}
