<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Companies\Models\Company;
use App\Core\Orm\Iterator;
use App\Core\Orm\Query;
use App\Core\Pdf\Pdf;
use App\Core\Pdf\PdfMerger;
use App\Exports\Models\Export;
use mikehaertl\tmp\File;

/**
 * @template T of ReceivableDocument
 */
abstract class DocumentPdfExporter extends AbstractExporter
{
    private Company $company;

    abstract public function getClass(): string;

    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get($this->getClass(), $this->company, $options);
        $listQueryBuilder->setSort('date ASC');

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getFileName(Export $export): string
    {
        return parent::getFileName($export).'.pdf';
    }

    public function build(Export $export, array $options): void
    {
        $this->company = $export->tenant();
        $documents = $this->getQuery($options)->all();

        // save the total # of records
        $export->incrementTotalRecords(count($documents));

        $file = $this->buildPdf($export, $documents);

        $this->persist($export, $file->getFileName());
        $this->finish($export);
    }

    private function buildPdf(Export $export, Iterator $documents): File
    {
        if (0 === count($documents)) {
            $pdf = Pdf::make();
            $pdf->addPage('<html><body>No matching documents.</body></html>');

            return $pdf->getTempFile();
        }

        $files = [];
        $locale = $export->tenant()->getLocale();
        foreach ($documents as $document) {
            $builder = $document->getPdfBuilder();
            if ($builder) {
                $files[] = new File($builder->build($locale), 'pdf');
            }

            // update position
            $export->incrementPosition();
        }

        // merge the documents into a single PDF
        return (new PdfMerger())->merge($files);
    }
}
