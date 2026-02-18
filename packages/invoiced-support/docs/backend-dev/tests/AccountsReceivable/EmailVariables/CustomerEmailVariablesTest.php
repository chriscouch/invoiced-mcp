<?php

namespace App\Tests\AccountsReceivable\EmailVariables;

use App\AccountsReceivable\EmailVariables\CustomerEmailVariables;
use App\AccountsReceivable\Models\Customer;
use App\Sending\Email\Models\EmailTemplate;
use App\Tests\AppTestCase;

class CustomerEmailVariablesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testGenerate(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->name = 'Name';
        $customer->number = 'Number';
        $customer->attention_to = 'Jared King';
        $customer->address1 = 'Address';
        $customer->address2 = 'Address 2';
        $customer->country = 'US';
        $generator = new CustomerEmailVariables($customer);

        $expected = [
            'customer_name' => 'Name',
            'customer_contact_name' => 'Jared King',
            'customer_number' => 'Number',
            'customer_address' => "Address\nAddress 2",
            'customer' => [
                'metadata' => [],
                'id' => $customer->id,
            ],
        ];

        $template = new EmailTemplate();
        $variables = $generator->generate($template);
        $this->assertEquals($expected, $variables);
    }
}
