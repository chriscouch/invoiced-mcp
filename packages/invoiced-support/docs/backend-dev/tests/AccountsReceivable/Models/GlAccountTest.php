<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\GlAccount;
use App\Tests\AppTestCase;

class GlAccountTest extends AppTestCase
{
    private static GlAccount $glAccount;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCannotCreateInvalidParent(): void
    {
        $glAccount = new GlAccount();
        $glAccount->name = 'Test';
        $glAccount->code = '10023';
        $glAccount->parent_id = -1;
        $this->assertFalse($glAccount->save());
    }

    public function testCannotCreateInvalidCode(): void
    {
        $glAccount = new GlAccount();
        $glAccount->name = 'Test';
        $glAccount->code = '10';
        $this->assertFalse($glAccount->save());
    }

    public function testCreate(): void
    {
        self::$glAccount = new GlAccount();
        self::$glAccount->name = 'Test';
        self::$glAccount->code = '10023';
        $this->assertTrue(self::$glAccount->save());
        $this->assertEquals(self::$company->id(), self::$glAccount->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $glAccount = new GlAccount();
        $glAccount->name = 'Test';
        $glAccount->code = '10023';
        $this->assertFalse($glAccount->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$glAccount->name = 'Sales & Marketing';
        $this->assertTrue(self::$glAccount->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $glAccounts = GlAccount::all();

        $this->assertCount(1, $glAccounts);
        $this->assertEquals(self::$glAccount->id(), $glAccounts[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$glAccount->id(),
            'name' => 'Sales & Marketing',
            'code' => '10023',
            'parent_id' => null,
            'created_at' => self::$glAccount->created_at,
            'updated_at' => self::$glAccount->updated_at,
        ];

        $this->assertEquals($expected, self::$glAccount->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$glAccount->delete());
    }

    public function testCreateParentHierarchy(): void
    {
        // Sales & Marketing
        $account1 = new GlAccount();
        $account1->name = 'Sales & Marketing';
        $account1->code = '1001';
        $account1->saveOrFail();

        $account2 = new GlAccount();
        $account2->name = 'Advertising';
        $account2->code = '1002';
        $account2->parent_id = (int) $account1->id();
        $account2->saveOrFail();

        $account3 = new GlAccount();
        $account3->name = 'Prospect Meals';
        $account3->code = '1003';
        $account3->parent_id = (int) $account1->id();
        $account3->saveOrFail();

        $account4 = new GlAccount();
        $account4->name = 'Commissions';
        $account4->code = '1004';
        $account4->parent_id = (int) $account1->id();
        $account4->saveOrFail();

        $account5 = new GlAccount();
        $account5->name = 'South';
        $account5->code = '1005';
        $account5->parent_id = (int) $account4->id();
        $account5->saveOrFail();

        $account6 = new GlAccount();
        $account6->name = 'North';
        $account6->code = '1006';
        $account6->parent_id = (int) $account4->id();
        $account6->saveOrFail();

        $account7 = new GlAccount();
        $account7->name = 'East';
        $account7->code = '1007';
        $account7->parent_id = (int) $account4->id();
        $account7->saveOrFail();

        $account8 = new GlAccount();
        $account8->name = 'West';
        $account8->code = '1008';
        $account8->parent_id = (int) $account4->id();
        $account8->saveOrFail();

        $account16 = new GlAccount();
        $account16->name = 'West 1';
        $account16->code = '1009';
        $account16->parent_id = (int) $account8->id();
        $account16->saveOrFail();

        $account17 = new GlAccount();
        $account17->name = 'West 2';
        $account17->code = '10010';
        $account17->parent_id = (int) $account8->id();
        $account17->saveOrFail();

        $account18 = new GlAccount();
        $account18->name = 'West 3';
        $account18->code = '10011';
        $account18->parent_id = (int) $account8->id();
        $account18->saveOrFail();

        // Research & Development
        $account9 = new GlAccount();
        $account9->name = 'Research & Development';
        $account9->code = '2001';
        $account9->saveOrFail();

        $account10 = new GlAccount();
        $account10->name = 'Salaries';
        $account10->code = '2002';
        $account10->parent_id = (int) $account9->id();
        $account10->saveOrFail();

        $account11 = new GlAccount();
        $account11->name = 'Contractors';
        $account11->code = '2003';
        $account11->parent_id = (int) $account9->id();
        $account11->saveOrFail();

        $account12 = new GlAccount();
        $account12->name = 'Materials';
        $account12->code = '2004';
        $account12->parent_id = (int) $account9->id();
        $account12->saveOrFail();

        // Other Top Level Accounts
        $account13 = new GlAccount();
        $account13->name = 'Accounts Receivable';
        $account13->code = '3001';
        $account13->saveOrFail();

        $account14 = new GlAccount();
        $account14->name = 'Accounts Payable';
        $account14->code = '4001';
        $account14->saveOrFail();

        $account15 = new GlAccount();
        $account15->name = 'Capital Stock';
        $account15->code = '5001';
        $account15->saveOrFail();

        $this->assertTrue(true);
    }

    /**
     * @depends testCreateParentHierarchy
     */
    public function testSorting(): void
    {
        $expected = [
            'Accounts Payable',
            'Accounts Receivable',
            'Capital Stock',
            'Research & Development',
            '-- Contractors',
            '-- Materials',
            '-- Salaries',
            'Sales & Marketing',
            '-- Advertising',
            '-- Commissions',
            '-- -- East',
            '-- -- North',
            '-- -- South',
            '-- -- West',
            '-- -- -- West 1',
            '-- -- -- West 2',
            '-- -- -- West 3',
            '-- Prospect Meals',
        ];

        $this->assertEquals($expected, $this->getChartOfAccounts());
    }

    /**
     * @depends testSorting
     */
    public function testEditParent(): void
    {
        // Move the "Commissions" account to the top-level
        $account = GlAccount::where('code', '1004')->one();
        $account->parent_id = null;
        $account->saveOrFail();

        $expected = [
            'Accounts Payable',
            'Accounts Receivable',
            'Capital Stock',
            'Commissions',
            '-- East',
            '-- North',
            '-- South',
            '-- West',
            '-- -- West 1',
            '-- -- West 2',
            '-- -- West 3',
            'Research & Development',
            '-- Contractors',
            '-- Materials',
            '-- Salaries',
            'Sales & Marketing',
            '-- Advertising',
            '-- Prospect Meals',
        ];

        $this->assertEquals($expected, $this->getChartOfAccounts());
    }

    public function testDetectCircularDependency(): void
    {
        $account1 = new GlAccount();
        $account1->name = 'Circular 1';
        $account1->code = 'circular1';
        $account1->saveOrFail();

        $account2 = new GlAccount();
        $account2->name = 'Circular 2';
        $account2->code = 'circular2';
        $account2->parent_id = (int) $account1->id();
        $account2->saveOrFail();

        $account1->parent_id = (int) $account2->id();
        $this->assertFalse($account1->save());
        $this->assertEquals('Detected circular dependency in account hierarchy: circular1 -> circular2 -> circular1', $account1->getErrors()[0]['message']);

        $account1->parent_id = (int) $account1->id();
        $this->assertFalse($account1->save());
        $this->assertEquals('Detected circular dependency in account hierarchy: circular1 -> circular1', $account1->getErrors()[0]['message']);
    }

    public function testMaxLevelsConstraint(): void
    {
        $account1 = new GlAccount();
        $account1->name = 'Level 1';
        $account1->code = 'level1';
        $account1->saveOrFail();

        $account2 = new GlAccount();
        $account2->name = 'Level 2';
        $account2->code = 'level2';
        $account2->parent_id = (int) $account1->id();
        $account2->saveOrFail();

        $account3 = new GlAccount();
        $account3->name = 'Level 3';
        $account3->code = 'level3';
        $account3->parent_id = (int) $account2->id();
        $account3->saveOrFail();

        $account4 = new GlAccount();
        $account4->name = 'Level 4';
        $account4->code = 'level4';
        $account4->parent_id = (int) $account3->id();
        $account4->saveOrFail();

        $account5 = new GlAccount();
        $account5->name = 'Level 5';
        $account5->code = 'level5';
        $account5->parent_id = (int) $account4->id();
        $account5->saveOrFail();

        $account6 = new GlAccount();
        $account6->name = 'Level 6';
        $account6->code = 'level6';
        $account6->parent_id = (int) $account5->id();
        $this->assertFalse($account6->save());
        $this->assertEquals('The maximum number of sub-account levels (5) has been exceeded', $account6->getErrors()[0]['message']);
    }

    public function testCannotDeleteWithChildren(): void
    {
        $account = GlAccount::where('code', '1004')->one();
        $this->assertFalse($account->delete());
        $this->assertEquals('This account cannot be deleted because it has at least one sub-account. Please delete the sub-accounts first.', $account->getErrors()[0]['message']);
    }

    private function getChartOfAccounts(): array
    {
        $result = [];
        foreach (GlAccount::all() as $glAccount) {
            // determine the level
            $level = 0;
            $parent = $glAccount->relation('parent_id');
            while ($parent) {
                ++$level;
                $parent = $parent->relation('parent_id');
            }

            $name = str_repeat('-- ', $level).$glAccount->name;
            $result[] = $name;
        }

        return $result;
    }
}
