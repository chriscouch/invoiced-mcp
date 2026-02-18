<?php

namespace App\Tests\Core\Orm;

use Mockery;
use App\Core\Orm\Model;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Driver\DriverInterface;
use App\Tests\Core\Orm\Models\Person;

class ACLModelRequesterTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $driver = Mockery::mock(DriverInterface::class);
        Model::setDriver($driver);
        ACLModelRequester::clear();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        ACLModelRequester::clear();
    }

    public function testGet(): void
    {
        $requester = new Person(['id' => 2]);
        ACLModelRequester::set($requester);
        $this->assertEquals($requester, ACLModelRequester::get());

        $requester2 = new Person(['id' => 3]);
        ACLModelRequester::set($requester2);
        $this->assertEquals($requester2, ACLModelRequester::get());
    }

    public function testGetCallable(): void
    {
        $i = 3;
        ACLModelRequester::setCallable(function () use (&$i) {
            ++$i;

            return new Person(['id' => $i]);
        });

        // callable should only fire once
        for ($j = 0; $j < 5; ++$j) {
            $requester = ACLModelRequester::get();
            $this->assertInstanceOf(Person::class, $requester);
            $this->assertEquals(4, $requester->id());
        }

        // set should override the callable
        $requester = new Person(['id' => 2]);
        ACLModelRequester::set($requester);
        $this->assertEquals($requester, ACLModelRequester::get());

        ACLModelRequester::clear();

        for ($j = 0; $j < 5; ++$j) {
            $requester = ACLModelRequester::get();
            $this->assertInstanceOf(Person::class, $requester);
            $this->assertEquals(5, $requester->id());
        }
    }
}
