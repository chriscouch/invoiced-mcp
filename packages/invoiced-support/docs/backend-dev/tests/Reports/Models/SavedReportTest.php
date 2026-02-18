<?php

namespace App\Tests\Reports\Models;

use App\Companies\Models\Member;
use App\Reports\Models\SavedReport;
use App\Tests\AppTestCase;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;

class SavedReportTest extends AppTestCase
{
    private static SavedReport $savedReport;
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

    public function testCreateInvalidDefinition(): void
    {
        $savedReport = new SavedReport();
        $savedReport->name = 'Report name';
        $savedReport->definition = '{"version":1,"title":"My Report","sections":[{"title":"Section 1","object":"customer","type":"chart","multi_entity":false,"fields":[{},{}],"filter":[],"group":[{"name":null,"expanded":false}],"sort":[]}]}';
        $this->assertFalse($savedReport->save());
    }

    public function testCreate(): void
    {
        self::$savedReport = new SavedReport();
        self::$savedReport->name = 'Report name';
        self::$savedReport->definition = '{"version":1,"title":"My Report","sections":[{"title":"Section 1","object":"invoice","type":"chart","chart_type":"bar","multi_entity":false,"fields":[{"field":{"id":"customer.name"}},{"field":{"function":"sum","arguments":[{"id":"balance"}]}}],"filter":[{"operator":">","value":"0","field":{"id":"balance"}}],"group":[{"field":{"id":"customer.name"},"name":null,"expanded":false}],"sort":[]}]}';
        self::$savedReport->saveOrFail();
        $this->assertTrue(self::$savedReport->save());

        $this->assertEquals(self::$company->id(), self::$savedReport->tenant_id);
        $this->assertEquals(self::$requester->id(), self::$savedReport->creator_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $reports = SavedReport::all();
        $this->assertCount(1, $reports);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$savedReport->name = 'Test report';
        $this->assertTrue(self::$savedReport->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$savedReport->id,
            'name' => 'Test report',
            'definition' => '{"version":1,"title":"My Report","sections":[{"title":"Section 1","object":"invoice","type":"chart","chart_type":"bar","multi_entity":false,"fields":[{"field":{"id":"customer.name"}},{"field":{"function":"sum","arguments":[{"id":"balance"}]}}],"filter":[{"operator":">","value":"0","field":{"id":"balance"}}],"group":[{"field":{"id":"customer.name"},"name":null,"expanded":false}],"sort":[]}]}',
            'private' => true,
            'creator' => self::$requester->user,
            'created_at' => self::$savedReport->created_at,
            'updated_at' => self::$savedReport->updated_at,
        ];

        $this->assertEquals($expected, self::$savedReport->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$savedReport->delete());
    }
}
