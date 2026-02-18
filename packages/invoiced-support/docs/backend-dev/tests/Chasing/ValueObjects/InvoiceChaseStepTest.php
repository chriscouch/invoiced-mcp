<?php

namespace App\Tests\Chasing\ValueObjects;

use App\Chasing\ValueObjects\InvoiceChaseStep;
use App\Tests\AppTestCase;

class InvoiceChaseStepTest extends AppTestCase
{
    public function testHash(): void
    {
        $step = new InvoiceChaseStep(0, [
            'hour' => 7,
            'email' => true,
            'sms' => false,
            'letter' => false,
        ], '1');

        $expected = '(trigger:0)-(email:1)-(hour:7)-(letter:)-(sms:)';
        $this->assertEquals($expected, $step->hash());
    }

    public function testHashConsistency(): void
    {
        // use chase schedules with options that
        // have different key orders
        $step1 = new InvoiceChaseStep(0, [
            'hour' => 7,
            'email' => true,
            'sms' => true,
            'letter' => true,
        ], '1');
        $step2 = new InvoiceChaseStep(0, [
            'sms' => true,
            'email' => true,
            'letter' => true,
            'hour' => 7,
        ], '1');

        $this->assertEquals($step1->hash(), $step2->hash());
    }
}
