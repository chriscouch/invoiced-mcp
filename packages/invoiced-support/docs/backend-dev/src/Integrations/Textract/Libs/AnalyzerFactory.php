<?php

namespace App\Integrations\Textract\Libs;

use App\Integrations\Textract\Interfaces\ExpenseDocumentAnalyzerInterface;

class AnalyzerFactory
{
    public function __construct(
        private readonly ExpenseAnalyzer $expenseAnalyzer,
        private readonly AIAnalyzer $aiAnalyzer
    ) {
    }

    public function getByApi(string $api): ExpenseDocumentAnalyzerInterface
    {
        return 'StartExpenseAnalysis' === $api ? $this->expenseAnalyzer : $this->aiAnalyzer;
    }
}
