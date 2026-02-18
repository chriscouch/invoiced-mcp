<?php

namespace App\Tests\Core\Orm\Driver;

/*
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use App\Core\Orm\Driver\DbalDriver;
use App\Core\Orm\Exception\DriverException;
use App\Core\Orm\Query;
use App\Tests\Core\Orm\Models\Group;
use App\Tests\Core\Orm\Models\Person;

class DbalDriverTest extends MockeryTestCase
{
    use SerializeValueTestTrait;

    private function getDriver(?Connection $connection = null): DbalDriver
    {
        $connection = $connection ?: Mockery::mock(Connection::class);

        return new DbalDriver($connection);
    }

    public function testGetConnection(): void
    {
        $db = Mockery::mock(Connection::class);
        $driver = $this->getDriver($db);
        $this->assertEquals($db, $driver->getConnection(null));
    }

    public function testGetConnectionFromManagerMissing(): void
    {
        $this->expectException(DriverException::class);
        $this->getDriver()->getConnection('not_supported');
    }

    public function testCreateModel(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('executeStatement')
            ->withArgs(['INSERT INTO `People` (`answer`, `array`) VALUES (?, ?)', [0 => 42, 1 => '{"test":true}']])
            ->once();

        $driver = $this->getDriver($db);

        $model = new Person();
        $this->assertTrue($driver->createModel($model, ['answer' => 42, 'array' => ['test' => true]]));
    }

    public function testCreateModelFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver when creating the Person: error');
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('executeStatement')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $model = new Person();
        $driver->createModel($model, []);
    }

    public function testGetCreatedID(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('lastInsertId')
            ->andReturn('1');

        $driver = $this->getDriver($db);

        $model = new Person();
        $this->assertEquals(1, $driver->getCreatedId($model, 'id'));
    }

    public function testGetCreatedIDFail(): void
    {
        $this->expectException(DriverException::class);
        $this->matchesRegularExpression('An error occurred in the database driver when getting the ID of the new Person: error');
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('lastInsertId')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $model = new Person();
        $driver->getCreatedId($model, 'id');
    }

    public function testLoadModel(): void
    {
        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchAssociative')
            ->withArgs(['SELECT * FROM `People` WHERE `id` = ?', [0 => '12']])
            ->andReturn(['name' => 'John']);

        $driver = $this->getDriver($db);

        $model = new Person(['id' => 12]);
        $this->assertEquals(['name' => 'John'], $driver->loadModel($model));
    }

    public function testLoadModelNotFound(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchAssociative')
            ->andReturn(null);
        $driver = $this->getDriver($db);

        $model = new Person(['id' => 12]);
        $this->assertNull($driver->loadModel($model));
    }

    public function testLoadModelFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver when loading an instance of Person: error');
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchAssociative')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $model = new Person(['id' => 12]);
        $driver->loadModel($model);
    }

    public function testQueryModels(): void
    {
        $query = new Query(Person::class);
        $query->where('id', 50, '>')
            ->where(['city' => 'Austin'])
            ->where('RAW SQL')
            ->where('People.alreadyDotted', true)
            ->where('name', ['Alice', 'Bob'])
            ->join(Group::class, 'group', 'id')
            ->sort('name asc')
            ->limit(5)
            ->start(10);

        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchAllAssociative')
            ->withArgs(['SELECT `People`.* FROM `People` JOIN `Groups` ON People.group=Groups.id WHERE `People`.`id` > ? AND `People`.`city` = ? AND RAW SQL AND `People`.`alreadyDotted` = ? AND `People`.`name` IN (?,?) ORDER BY `People`.`name` asc LIMIT 10,5', [0 => 50, 1 => 'Austin', 2 => true, 'Alice', 'Bob']])
            ->andReturn([['test' => true]]);

        $driver = $this->getDriver($db);

        $this->assertEquals([['test' => true]], $driver->queryModels($query));
    }

    public function testQueryModelsFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver while performing the Person query: error');
        $query = new Query(new Person());
        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchAllAssociative')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $driver->queryModels($query);
    }

    public function testUpdateModel(): void
    {
        // update query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('executeStatement')
            ->withArgs(['UPDATE `People` SET `name` = ?, `array` = ? WHERE `id` = ?', [0 => 'John', 1 => '{"test":true}', 2 => '11']])
            ->once();

        $driver = $this->getDriver($db);

        $model = new Person(['id' => 11]);

        $this->assertTrue($driver->updateModel($model, []));

        $parameters = ['name' => 'John', 'array' => ['test' => true]];
        $this->assertTrue($driver->updateModel($model, $parameters));
    }

    public function testUpdateModelFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver when updating the Person: error');
        // update query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('executeStatement')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $model = new Person(['id' => 11]);
        $driver->updateModel($model, ['name' => 'John']);
    }

    public function testDeleteModel(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('executeStatement')
            ->withArgs(['DELETE FROM `People` WHERE `id` = ?', [0 => '10']])
            ->once();

        $driver = $this->getDriver($db);

        $model = new Person(['id' => 10]);
        $this->assertTrue($driver->deleteModel($model));
    }

    public function testDeleteModelFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver while deleting the Person: error');
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('executeStatement')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $model = new Person(['id' => 10]);
        $driver->deleteModel($model);
    }

    public function testCount(): void
    {
        $query = new Query(Person::class);

        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->withArgs(['SELECT COUNT(*) FROM `People`', []])
            ->andReturn(1);

        $driver = $this->getDriver($db);

        $this->assertEquals(1, $driver->count($query));
    }

    public function testCountFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver while getting the value of Person.count: error');
        $query = new Query(new Person());
        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $driver->count($query);
    }

    public function testSum(): void
    {
        $query = new Query(Person::class);

        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->withArgs(['SELECT SUM(People.balance) FROM `People`', []])
            ->andReturn(123.45);

        $driver = $this->getDriver($db);

        $this->assertEquals(123.45, $driver->sum($query, 'balance'));
    }

    public function testSumFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver while getting the value of Person.Person.balance: error');
        $query = new Query(new Person());
        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $driver->sum($query, 'Person.balance');
    }

    public function testAverage(): void
    {
        $query = new Query(Person::class);

        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->withArgs(['SELECT AVG(People.balance) FROM `People`', []])
            ->andReturn(123.45);

        $driver = $this->getDriver($db);

        $this->assertEquals(123.45, $driver->average($query, 'balance'));
    }

    public function testAverageFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver while getting the value of Person.balance: error');
        $query = new Query(new Person());
        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $driver->average($query, 'balance');
    }

    public function testMax(): void
    {
        $query = new Query(Person::class);

        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->withArgs(['SELECT MAX(People.balance) FROM `People`', []])
            ->andReturn(123.45);

        $driver = $this->getDriver($db);

        $this->assertEquals(123.45, $driver->max($query, 'balance'));
    }

    public function testMaxFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver while getting the value of Person.balance: error');
        $query = new Query(new Person());
        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $driver->max($query, 'balance');
    }

    public function testMin(): void
    {
        $query = new Query(Person::class);

        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->withArgs(['SELECT MIN(People.balance) FROM `People`', []])
            ->andReturn(123.45);

        $driver = $this->getDriver($db);

        $this->assertEquals(123.45, $driver->min($query, 'balance'));
    }

    public function testMinFail(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('An error occurred in the database driver while getting the value of Person.balance: error');
        $query = new Query(new Person());
        // select query mock
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('fetchOne')
            ->andThrow(new DBALException('error'));
        $driver = $this->getDriver($db);
        $driver->min($query, 'balance');
    }

    public function testStartTransaction(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('beginTransaction')
            ->once();
        $driver = $this->getDriver($db);
        $driver->startTransaction(null);
    }

    public function testRollBackTransaction(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('rollBack')
            ->once();
        $driver = $this->getDriver($db);
        $driver->rollBackTransaction(null);
    }

    public function testCommitTransaction(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('commit')
            ->once();
        $driver = $this->getDriver($db);
        $driver->commitTransaction(null);
    }

    public function testNestedTransactions(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('beginTransaction')
            ->times(3);
        $db->shouldReceive('rollBack')
            ->once();
        $db->shouldReceive('commit')
            ->twice();
        $driver = $this->getDriver($db);

        $driver->startTransaction(null);
        $driver->startTransaction(null);
        $driver->startTransaction(null);
        $driver->commitTransaction(null);
        $driver->commitTransaction(null);
        $driver->rollBackTransaction(null);
    }
}
