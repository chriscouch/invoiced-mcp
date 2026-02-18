<?php

namespace App\Integrations\Textract\Interfaces;

use App\Core\Files\Models\File;
use App\Integrations\Textract\Models\TextractImport;

interface ExpenseDocumentAnalyzerInterface
{
    /**
     * Sends document to Textract
     * returns unique job id.
     */
    public function send(File $file): string;

    /**
     * Validates the job to prevent multiply calls.
     */
    public function validate(TextractImport $job): bool;

    /**
     * Parses Job result.
     */
    public function analyze(TextractImport $job): void;

    /**
     * chain of responsibility implementation
     * with fallback analyzer.
     */
    public function setNext(self $next): void;
}
