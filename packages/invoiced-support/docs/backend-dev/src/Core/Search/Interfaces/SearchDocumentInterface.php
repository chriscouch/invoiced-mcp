<?php

namespace App\Core\Search\Interfaces;

interface SearchDocumentInterface
{
    /**
     * Generates the document to be inserted into the search index.
     */
    public function toSearchDocument(): array;
}
