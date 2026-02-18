<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace App\Tests\Core\Orm\Relation;

use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Orm\Relation\AbstractRelation;
use App\Tests\Core\Orm\Models\TestModel;

class TestAbstractRelation extends AbstractRelation
{
    protected function initQuery(Query $query): Query
    {
        $query->where('test', true);

        return $query;
    }

    public function getResults(): mixed
    {
        // do nothing
        return null;
    }

    public function save(Model $model): Model
    {
        // do nothing
        return $model;
    }

    public function create(array $values = []): Model
    {
        // do nothing
        return new TestModel();
    }
}
