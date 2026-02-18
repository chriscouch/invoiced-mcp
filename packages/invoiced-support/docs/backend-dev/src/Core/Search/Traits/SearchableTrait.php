<?php

namespace App\Core\Search\Traits;

use App\Core\Search\Libs\SearchIndexListener;

trait SearchableTrait
{
    /**
     * Installs the search index listener.
     */
    protected function autoInitializeSearchable(): void
    {
        // install the search index listener for this model
        SearchIndexListener::add($this);
    }
}
