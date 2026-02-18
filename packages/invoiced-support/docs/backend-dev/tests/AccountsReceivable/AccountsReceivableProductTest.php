<?php

namespace App\Tests\AccountsReceivable;

use App\AccountsReceivable\AccountsReceivableProduct;
use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\AutoNumberSequence;
use App\Companies\Models\Company;
use App\Companies\Models\Role;
use App\Core\Entitlements\Models\Product;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Sending\Email\Models\Inbox;
use App\Tests\AppTestCase;

class AccountsReceivableProductTest extends AppTestCase
{
    public function testInstall(): void
    {
        $this->getService('test.tenant')->clear();

        $company = new Company();
        $company->name = 'TEST';
        $company->username = 'test'.time().rand();
        $company->country = 'US';
        $company->email = 'test@example.com';
        $company->creator_id = $this->getService('test.user_context')->get()->id();
        $company->saveOrFail();
        self::$company = $company;

        $this->getService('test.tenant')->set($company);

        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Receivable Free')->one(), $company);

        // verify the settings were created
        $settings = AccountsReceivableSettings::oneOrNull();
        $this->assertInstanceOf(AccountsReceivableSettings::class, $settings);

        // verify the roles were created
        foreach (AccountsReceivableProduct::DEFAULT_ROLES as $row) {
            $role = Role::findById($row['id']);
            $this->assertInstanceOf(Role::class, $role, "Role {$row['name']} does not exist");
            foreach ($row['permissions'] as $k) {
                $this->assertTrue($role->$k, "Role {$row['name']} does not have $k permission");
            }
        }

        // verify payment methods were created
        foreach (PaymentMethod::METHODS as $methodType) {
            $type = $methodType->toString();
            $method = PaymentMethod::find([$company->id(), $type]);
            $this->assertInstanceOf(PaymentMethod::class, $method, $type.' payment method does not exist');
        }

        // verify auto numbering sequences were created
        foreach (AccountsReceivableProduct::AUTO_NUMBER_SEQUENCES as $type => $template) {
            $sequence = AutoNumberSequence::find([$company->id(), $type]);
            $this->assertInstanceOf(AutoNumberSequence::class, $sequence, $type.' numbering sequence does not exist');
        }

        // create a new customer
        self::$customer = new Customer();
        self::$customer->name = 'Test';
        self::$customer->email = self::getService('test.user_context')->get()->email;
        $this->assertTrue(self::$customer->save());

        // test inbox
        $inbox = Inbox::where('tenant_id', $company->id)->oneOrNull();
        $this->assertInstanceOf(Inbox::class, $inbox);
        $this->assertEquals($inbox->id(), $company->accounts_receivable_settings->inbox_id);
        $this->assertEquals($inbox->id(), $company->accounts_receivable_settings->reply_to_inbox_id);

        // installing a second time should not cause any error
        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Receivable Free')->one(), $company);
    }
}
