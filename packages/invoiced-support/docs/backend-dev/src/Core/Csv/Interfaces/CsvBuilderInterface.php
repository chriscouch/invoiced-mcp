<?php

namespace App\Core\Csv\Interfaces;

interface CsvBuilderInterface
{
    /**
     * Generates the CSV filename for this object.
     */
    public function filename(string $locale): string;

    /**
     * Generates a CSV string.
     */
    public function build(string $locale): string;
}
