<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\Tests\AppTestCase;

class ChasingEventTest extends AppTestCase
{
    public function testChasingEvent(): void
    {
        $customer = new Customer();
        $customer->tenant_id = -1;
        $customer->client_id = 'test';
        $company = new Company();
        $company->username = 'test';
        $company->sso_key = RandomString::generate(63, RandomString::CHAR_ALNUM);
        $customer->setRelation('tenant_id', $company);
        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 500);
        $invoices = [new Invoice()];
        $step = new ChasingCadenceStep();
        $event = new ChasingEvent($customer, $balance, $pastDueBalance, $invoices, $step);

        $this->assertEquals($customer, $event->getCustomer());
        $this->assertEquals($balance, $event->getBalance());
        $this->assertEquals($pastDueBalance, $event->getPastDueBalance());
        $this->assertEquals($invoices, $event->getInvoices());
        $this->assertEquals($step, $event->getStep());
        $this->assertNull($event->getNextStep());
        $this->assertNotEmpty($event->getClientUrl());
        $this->assertStringStartsWith('http', $event->getClientUrl());
    }
}
