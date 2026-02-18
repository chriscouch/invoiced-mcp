<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentInstruction;
use App\Tests\AppTestCase;

class PaymentMethodDecoratorTest extends AppTestCase
{
    public function testDecorate(): void
    {
        $decoratpor = self::getService('test.payment_method_decorator');
        self::hasCompany();
        /** @var PaymentMethod $method */
        $method = PaymentMethod::find([self::$company->id, PaymentMethod::CASH]);
        $meta = $method->meta;

        $method = $decoratpor->decorate($method, 'US');
        $this->assertEquals($meta, $method->meta);

        $instruction = new PaymentInstruction();
        $instruction->payment_method_id = $method->id;
        $instruction->meta = 'test2';
        $instruction->country = 'GB';
        $instruction->saveOrFail();

        $method = $decoratpor->decorate($method, 'US');
        $this->assertEquals($meta, $method->meta);
        $method = $decoratpor->decorate($method, 'GB');
        $this->assertEquals('test2', $method->meta);
    }
}
