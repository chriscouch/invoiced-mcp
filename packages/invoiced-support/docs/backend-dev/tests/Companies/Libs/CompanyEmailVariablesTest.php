<?php

namespace App\Tests\Companies\Libs;

use App\Companies\EmailVariables\CompanyEmailVariables;
use App\Companies\Models\Company;
use App\Sending\Email\Models\EmailTemplate;
use App\Tests\AppTestCase;

class CompanyEmailVariablesTest extends AppTestCase
{
    public function testGenerate(): void
    {
        $company = new Company();
        $company->name = 'Name';
        $company->username = 'Username';
        $company->address1 = 'Address';
        $company->address2 = 'Address 2';
        $company->email = 'Email';
        $company->country = 'US';
        $generator = new CompanyEmailVariables($company);

        $expected = [
            'company_name' => 'Name',
            'company_username' => 'Username',
            'company_address' => "Address\nAddress 2",
            'company_email' => 'Email',
        ];

        $template = new EmailTemplate();
        $variables = $generator->generate($template);
        $this->assertEquals($expected, $variables);
    }
}
