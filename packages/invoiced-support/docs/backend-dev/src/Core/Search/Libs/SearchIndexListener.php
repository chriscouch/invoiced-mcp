<?php

namespace App\Core\Search\Libs;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Model;
use App\Core\Orm\Event\AbstractEvent;

class SearchIndexListener
{
    private static SearchIndexListener $listener;
    private static bool $enabled = true;

    /**
     * Performs a create operation on the search index for a model.
     */
    public function onCreated(AbstractEvent $event): void
    {
        if (!self::$enabled) {
            return;
        }

        /** @var MultitenantModel $model */
        $model = $event->getModel();
        $search = $this->getSearch();
        $index = $search->getIndex($model->tenant(), $model::class);

        // add the model to the search index
        $index->insertDocument((string) $model->id(), (new SearchDocumentFactory())->make($model));
    }

    /**
     * Performs an update operation on the search index for a model.
     */
    public function onUpdated(AbstractEvent $event): void
    {
        if (!self::$enabled) {
            return;
        }

        /** @var MultitenantModel $model */
        $model = $event->getModel();
        $search = $this->getSearch();
        $index = $search->getIndex($model->tenant(), $model::class);

        $index->updateDocument((string) $model->id(), (new SearchDocumentFactory())->make($model));
    }

    /**
     * Performs a delete operation on the search index for a model.
     */
    public function onDeleted(AbstractEvent $event): void
    {
        if (!self::$enabled) {
            return;
        }

        /** @var MultitenantModel $model */
        $model = $event->getModel();
        $search = $this->getSearch();
        $index = $search->getIndex($model->tenant(), $model::class);

        $index->deleteDocument((string) $model->id());
    }

    /**
     * Installs the search listeners for a model.
     */
    public static function add(Model $model): void
    {
        if (!isset(self::$listener)) {
            self::$listener = new self();
        }

        $model::created([self::$listener, 'onCreated']);
        $model::updated([self::$listener, 'onUpdated']);
        $model::deleted([self::$listener, 'onDeleted']);
    }

    /**
     * Disables model search indexing.
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Enables model search indexing (on by default).
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    private function getSearch(): Search
    {
        return SearchFacade::get();
    }
}
