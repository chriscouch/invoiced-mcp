<?php

namespace App\Tests\Reports\Models;

use App\Companies\Models\Member;
use App\Reports\Models\Dashboard;
use App\Tests\AppTestCase;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;

class DashboardTest extends AppTestCase
{
    private static Dashboard $dashboard;
    private static ?Model $originalRequester;
    private static Member $requester;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$originalRequester = ACLModelRequester::get();
        self::$requester = Member::one();
        ACLModelRequester::set(self::$requester);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ACLModelRequester::set(self::$originalRequester);
    }

    public function testCreate(): void
    {
        self::$dashboard = new Dashboard();
        self::$dashboard->name = 'My Custom Dashboard';
        self::$dashboard->definition = (object) ['name' => 'My Custom Dashboard', 'rows' => []];
        self::$dashboard->saveOrFail();
        $this->assertTrue(self::$dashboard->save());

        $this->assertEquals(self::$company->id(), self::$dashboard->tenant_id);
        $this->assertEquals(self::$requester->id(), self::$dashboard->creator?->id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $dashboards = Dashboard::all();
        $this->assertCount(1, $dashboards);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$dashboard->name = 'Test Dashboard';
        $this->assertTrue(self::$dashboard->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$dashboard->id,
            'name' => 'Test Dashboard',
            'private' => true,
            'definition' => (object) ['name' => 'My Custom Dashboard', 'rows' => []],
            'created_at' => self::$dashboard->created_at,
            'updated_at' => self::$dashboard->updated_at,
        ];

        $this->assertEquals($expected, self::$dashboard->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$dashboard->delete());
    }
}
