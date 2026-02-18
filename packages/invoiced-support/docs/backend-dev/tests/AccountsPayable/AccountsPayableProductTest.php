<?php

namespace App\Tests\AccountsPayable;

use App\AccountsPayable\AccountsPayableProduct;
use App\AccountsPayable\Models\AccountsPayableSettings;
use App\AccountsPayable\Models\Vendor;
use App\Companies\Models\AutoNumberSequence;
use App\Companies\Models\Company;
use App\Companies\Models\Role;
use App\Core\Entitlements\Models\Product;
use App\Sending\Email\Models\Inbox;
use App\Tests\AppTestCase;

class AccountsPayableProductTest extends AppTestCase
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

        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Payable Free')->one(), $company);

        // verify the settings were created
        $settings = AccountsPayableSettings::oneOrNull();
        $this->assertInstanceOf(AccountsPayableSettings::class, $settings);

        // verify the roles were created
        foreach (AccountsPayableProduct::DEFAULT_ROLES as $row) {
            $role = Role::findById($row['id']);
            $this->assertInstanceOf(Role::class, $role, "Role {$row['name']} does not exist");
            foreach ($row['permissions'] as $k) {
                $this->assertTrue($role->$k, "Role {$row['name']} does not have $k permission");
            }
        }

        // verify auto numbering sequences were created
        foreach (AccountsPayableProduct::AUTO_NUMBER_SEQUENCES as $type => $template) {
            $sequence = AutoNumberSequence::find([$company->id(), $type]);
            $this->assertInstanceOf(AutoNumberSequence::class, $sequence, $type.' numbering sequence does not exist');
        }

        // verify the ledger is created
        $id = self::getService('test.database')->fetchOne('SELECT id FROM Ledgers WHERE name="Accounts Payable - '.$company->id.'"');
        $this->assertGreaterThan(0, $id);

        // test inbox
        $inbox = Inbox::where('tenant_id', $company->id)->oneOrNull();
        $this->assertInstanceOf(Inbox::class, $inbox);
        $this->assertEquals($inbox->id(), $company->accounts_payable_settings->inbox_id);

        // create a new vendor
        self::$vendor = new Vendor();
        self::$vendor->name = 'Test';
        self::$vendor->saveOrFail();

        // installing a second time should not cause any error
        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Payable Free')->one(), $company);
    }
}
