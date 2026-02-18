<?php

namespace App\Tests\Core\Orm;

use App\Core\Orm\Driver\DriverInterface;
use App\Core\Orm\Errors;
use App\Core\Orm\Interfaces\TranslatorInterface;
use App\Core\Orm\Model;
use Mockery\Adapter\Phpunit\MockeryTestCase;

abstract class ModelTestCase extends MockeryTestCase
{
    private static DriverInterface $originalDriver;
    private static TranslatorInterface $originalTranslator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$originalDriver = Model::getDriver();
        self::$originalTranslator = (new Errors())->getTranslator();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        Model::setDriver(self::$originalDriver);
        Errors::setTranslator(self::$originalTranslator);
    }
}
