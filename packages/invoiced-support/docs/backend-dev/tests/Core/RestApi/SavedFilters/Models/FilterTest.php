<?php

namespace App\Tests\Core\RestApi\SavedFilters\Models;

use App\Companies\Models\Member;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Exception\DriverException;
use App\Core\RestApi\SavedFilters\Models\Filter;
use App\Core\Utils\Enums\ObjectType;
use App\Tests\AppTestCase;

class FilterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testModel(): void
    {
        $filter = new Filter();

        $requester = ACLModelRequester::get();
        $user = self::getService('test.user_context')->get();
        $memberAdmin = Member::where('user_id', $user->id())->one();
        ACLModelRequester::set($memberAdmin);
        $this->assertFalse($filter->save());
        $this->assertEquals([
            'Creator is missing',
            'Name is missing',
            'Settings is missing',
            'Type is missing',
        ], $filter->getErrors()->all());

        $filter->creator = 0;
        $filter->name = '';
        $filter->settings = (object) ['test' => true];
        $filter->type = 0;

        $this->assertFalse($filter->save());
        $this->assertEquals([
            'Name must be a string between 1 and 255 characters.',
        ], $filter->getErrors()->all());

        $filter->name = 'a';
        $filter->type = ObjectType::Customer->value;
        try {
            $filter->save();
            $this->assertFalse(true, 'No error thrown');
        } catch (DriverException $e) {
        }

        $filter->creator = $memberAdmin->id;
        $this->assertTrue($filter->save());

        $filters = Filter::execute();

        $this->assertCount(1, $filters);
        $filter = $filters[0];

        $this->assertEquals([
            'creator' => $memberAdmin->id,
            'id' => $filter->id,
            'name' => 'a',
            'private' => false,
            'settings' => (object) ['test' => true],
            'type' => 'customer',
        ], $filter->toArray());

        $filter->settings = (object) ['test' => 'test'];
        $this->assertTrue($filter->save());

        $filters = Filter::execute();

        $this->assertCount(1, $filters);
        $filter = $filters[0];
        unset($filter['id']);

        $this->assertEquals([
            'creator' => $memberAdmin->id,
            'id' => $filter->id,
            'name' => 'a',
            'private' => false,
            'settings' => (object) ['test' => 'test'],
            'type' => 'customer',
        ], $filter->toArray());

        $filter->delete();
        $filters = Filter::execute();
        $this->assertCount(0, $filters);
        ACLModelRequester::set($requester);
    }
}
