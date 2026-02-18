<?php

namespace App\Core\Orm\Relation;

use App\Core\Orm\Model;
use App\Core\Orm\Query;

/**
 * Represents a belongs-to relationship.
 */
final class BelongsTo extends AbstractRelation
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
        $model->saveOrFail();
        $this->attach($model);

        return $model;
    }

    public function create(array $values = []): Model
    {
        $class = $this->foreignModel;
        /** @var Model $model */
        $model = new $class();
        $model->create($values);

        $this->attach($model);

        return $model;
    }

    /**
     * Attaches this model to an owning model.
     *
     * @param Model $model owning model
     *
     * @return $this
     */
    public function attach(Model $model): self
    {
        $this->localModel->{$this->localKey} = $model->{$this->foreignKey};
        $this->localModel->saveOrFail();

        return $this;
    }

    /**
     * Detaches this model from the owning model.
     *
     * @return $this
     */
    public function detach(): self
    {
        $this->localModel->{$this->localKey} = null;
        $this->localModel->saveOrFail();

        return $this;
    }
}
