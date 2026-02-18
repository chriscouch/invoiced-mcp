<?php

namespace App\Core\Orm\Relation;

use App\Core\Orm\Model;
use App\Core\Orm\Query;

final class Polymorphic extends AbstractRelation
{
    private string $localTypeKey;
    private string $localIdKey;
    /** @var class-string<Model>[] */
    private array $modelMapping;

    /**
     * @param class-string<Model>[] $modelMapping
     */
    public function __construct(Model $localModel, string $localTypeKey, string $localIdKey, array $modelMapping, string $foreignKey)
    {
        $this->localModel = $localModel;
        $this->localTypeKey = $localTypeKey;
        $this->localIdKey = $localIdKey;
        $this->modelMapping = $modelMapping;
        $this->foreignKey = $foreignKey;
    }

    public function getLocalTypeKey(): string
    {
        return $this->localTypeKey;
    }

    public function getLocalIdKey(): string
    {
        return $this->localIdKey;
    }

    public function getModelMapping(): array
    {
        return $this->modelMapping;
    }

    /**
     * Returns the query instance for this relation.
     */
    public function getQuery(): Query
    {
        $type = $this->localModel->{$this->localTypeKey};
        if (null === $type) {
            $this->empty = true;

            return new Query(Model::class);
        }

        $foreignModel = $this->modelMapping[$type];
        $query = new Query(new $foreignModel());

        $id = $this->localModel->{$this->localIdKey};

        if (null === $id) {
            $this->empty = true;
        }

        $query->where($this->foreignKey, $id)
            ->limit(1);

        return $query;
    }

    protected function initQuery(Query $query): Query
    {
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
        $class = $this->modelMapping[$this->localModel->{$this->localTypeKey}];
        $model = new $class();
        $model->create($values);

        $this->attach($model);

        return $model;
    }

    /**
     * Attaches this model to an owning model.
     *
     * @param Model $model owning model
     */
    public function attach(Model $model): void
    {
        $type = array_search(get_class($model), $this->modelMapping);
        $this->localIdKey->{$this->localTypeKey} = $type;
        $this->localModel->{$this->localIdKey} = $model->{$this->foreignKey};
        $this->localModel->saveOrFail();
    }

    /**
     * Detaches this model from the owning model.
     */
    public function detach(): void
    {
        $this->localModel->{$this->localTypeKey} = null;
        $this->localModel->{$this->localIdKey} = null;
        $this->localModel->saveOrFail();
    }
}
