<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace App\Tests\Core\Orm;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Model;

class ModelEventTest extends MockeryTestCase
{
    public function testGetModel(): void
    {
        $model = Mockery::mock(Model::class);
        $event = new ModelCreated($model);
        $this->assertEquals($model, $event->getModel());
    }
}
