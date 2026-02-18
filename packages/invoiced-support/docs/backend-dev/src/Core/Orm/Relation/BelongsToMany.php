<?php

namespace App\Core\Orm\Relation;

use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Core\Orm\Query;

/**
 * Represents a belongs-to-many relationship.
 */
final class BelongsToMany extends AbstractRelation
{
    protected string $tablename;

    /**
     * @param string              $localKey     identifying key on local model
     * @param string              $tablename    pivot table name
     * @param class-string<Model> $foreignModel foreign model class
     * @param string              $foreignKey   identifying key on foreign model
     */
    public function __construct(Model $localModel, string $localKey, string $tablename, string $foreignModel, string $foreignKey)
    {
        $this->tablename = $tablename;

        parent::__construct($localModel, $localKey, $foreignModel, $foreignKey);
    }

    protected function initQuery(Query $query): Query
    {
        $pivot = new Pivot();
        $pivot->setTablename($this->tablename);

        $ids = $this->localModel->ids();
        /** @var Model $model */
        $model = $query->getModel();
        $foreignIds = $model->ids();
        // known issue - this will work only on single join  column
        if (1 !== count($foreignIds) && 1 != count($ids)) {
            $this->empty = true;

            return $query;
        }
        $id = array_shift($ids);
        $firstForeignKey = array_key_first($foreignIds);
        if (!$id || !is_string($firstForeignKey)) {
            $this->empty = true;

            return $query;
        }

        $query->where("$this->tablename.$this->localKey = $id");
        $query->join($pivot, $this->foreignKey, $firstForeignKey);

        return $query;
    }

    /**
     * @return Model[]|null
     */
    public function getResults(): ?array
    {
        $query = $this->getQuery();
        if ($this->empty) {
            return null;
        }

        return $query->execute();
    }

    /**
     * Gets the pivot tablename.
     */
    public function getTablename(): string
    {
        return $this->tablename;
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
     * Attaches a model to the relationship by creating
     * a pivot model.
     *
     * @throws ModelException when the operation fails
     *
     * @return $this
     */
    public function attach(Model $model): self
    {
        // create pivot relation
        $pivot = new Pivot();
        $pivot->setTablename($this->tablename);
        $pivot->setProperties($this->localKey, $this->foreignKey);

        // build the local side
        $ids = $this->localModel->ids();
        foreach ($ids as $id) {
            $pivot->{$this->localKey} = $id;
        }

        // build the foreign side
        $ids = $model->ids();
        foreach ($ids as $id) {
            $pivot->{$this->foreignKey} = $id;
        }

        $pivot->saveOrFail();
        $model->pivot = $pivot; /* @phpstan-ignore-line */

        return $this;
    }

    /**
     * Detaches a model from the relationship by deleting
     * the pivot model.
     *
     * @throws ModelException when the operation fails
     *
     * @return $this
     */
    public function detach(Model $model): self
    {
        $model->pivot->delete(); /* @phpstan-ignore-line */
        unset($model->pivot);

        return $this;
    }

    /**
     * Removes any relationships that are not included
     * in the list of IDs.
     *
     * @throws ModelException when the operation fails
     *
     * @return $this
     */
    public function sync(array $ids): self
    {
        $pivot = new Pivot();
        $pivot->setTablename($this->tablename);
        $query = new Query($pivot);

        $localIds = $this->localModel->ids();
        foreach ($localIds as $property => $id) {
            $query->where($this->localKey, $id);
        }

        if (count($ids) > 0) {
            $in = implode(',', $ids);
            $query->where("{$this->foreignKey} NOT IN ($in)");
        }

        $query->delete();

        return $this;
    }
}
