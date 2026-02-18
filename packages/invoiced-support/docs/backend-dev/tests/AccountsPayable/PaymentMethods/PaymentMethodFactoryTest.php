<?php

namespace App\Tests\AccountsPayable\PaymentMethods;

use App\AccountsPayable\PaymentMethods\AchPaymentMethod;
use App\AccountsPayable\PaymentMethods\CreditCardPaymentMethod;
use App\AccountsPayable\PaymentMethods\ECheckPaymentMethod;
use App\AccountsPayable\PaymentMethods\PrintCheckPaymentMethod;
use App\Tests\AppTestCase;

class PaymentMethodFactoryTest extends AppTestCase
{
    public function testGetAch(): void
    {
        $factory = self::getService('test.payment_method_factory');
        $this->assertInstanceOf(AchPaymentMethod::class, $factory->get('ach'));
    }

    public function testGetCreditCard(): void
    {
        $factory = self::getService('test.payment_method_factory');
        $this->assertInstanceOf(CreditCardPaymentMethod::class, $factory->get('credit_card'));
    }

    public function testGetEcheck(): void
    {
        $factory = self::getService('test.payment_method_factory');
        $this->assertInstanceOf(ECheckPaymentMethod::class, $factory->get('echeck'));
    }

    public function testGetPrintCheck(): void
    {
        $factory = self::getService('test.payment_method_factory');
        $this->assertInstanceOf(PrintCheckPaymentMethod::class, $factory->get('print_check'));
    }
}
