<?php

namespace App\Core\Orm\Relation;

use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Core\Orm\Query;

/**
 * Represents a has-one relationship.
 */
final class HasOne extends AbstractRelation
{
    protected function initQuery(Query $query): Query
    {
        $id = $this->localModel->{$this->localKey};

        if (null === $id) {
            $this->empty = true;
        }

        $query->where($this->foreignKey, $id)
            ->limit(1);

        return $query;
    }

    public function getResults(): ?Model
    {
        $query = $this->getQuery();
        if ($this->empty) {
            return null;
        }

        return $query->oneOrNull();
    }

    public function save(Model $model): Model
    {
        $model->{$this->foreignKey} = $this->localModel->{$this->localKey};
        $model->saveOrFail();

        return $model;
    }

    public function create(array $values = []): Model
    {
        $class = $this->foreignModel;
        /** @var Model $model */
        $model = new $class();
        $model->{$this->foreignKey} = $this->localModel->{$this->localKey};
        $model->create($values);

        return $model;
    }

    /**
     * Attaches a child model to this model.
     *
     * @param Model $model child model
     *
     * @throws ModelException when the operation fails
     *
     * @return $this
     */
    public function attach(Model $model): self
    {
        $model->{$this->foreignKey} = $this->localModel->{$this->localKey};
        $model->saveOrFail();

        return $this;
    }

    /**
     * Detaches the child model from this model.
     *
     * @throws ModelException when the operation fails
     *
     * @return $this
     */
    public function detach(): self
    {
        $model = $this->getResults();

        if ($model) {
            $model->{$this->foreignKey} = null;
            $model->saveOrFail();
        }

        return $this;
    }
}
