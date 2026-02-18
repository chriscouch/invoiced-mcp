<?php

namespace App\Tests;

use App\Core\Orm\Model;
use App\ActivityLog\Libs\EventSpool;

/**
 * @template T
 */
abstract class ModelTestCase extends AppTestCase
{
    /** @var T */
    private static $modelToTest;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    protected function setUp(): void
    {
        parent::setUp();
        EventSpool::enable();
    }

    /**
     * @return T
     */
    abstract protected function getModelCreate(): Model;

    /**
     * @param T $model
     *
     * @return T
     */
    protected function getModelToArray($model): Model
    {
        return $model;
    }

    /**
     * @param T $model
     */
    abstract protected function getExpectedToArray($model, array &$output): array;

    /**
     * @param T $model
     *
     * @return T
     */
    abstract protected function getModelEdit($model): Model;

    /**
     * @param T $model
     *
     * @return T
     */
    protected function getModelDelete($model): Model
    {
        return $model;
    }

    public function testCreate(): void
    {
        self::$modelToTest = $this->getModelCreate();
        $this->assertTrue(self::$modelToTest->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $model = $this->getModelToArray(self::$modelToTest);
        $output = $model->toArray();
        $expected = $this->getExpectedToArray($model, $output);
        $this->assertEquals($expected, $output);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        $model = $this->getModelEdit(self::$modelToTest);
        $this->assertTrue($model->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $model = $this->getModelDelete(self::$modelToTest);
        $this->assertTrue($model->delete());
    }
}
