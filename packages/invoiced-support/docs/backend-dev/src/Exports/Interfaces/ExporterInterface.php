<?php

namespace App\Exports\Interfaces;

use App\Exports\Models\Export;

interface ExporterInterface
{
    /**
     * Builds the export.
     */
    public function build(Export $export, array $options): void;

    public static function getId(): string;
}
