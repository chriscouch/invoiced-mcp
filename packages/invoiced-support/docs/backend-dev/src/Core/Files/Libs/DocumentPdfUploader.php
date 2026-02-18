<?php

namespace App\Core\Files\Libs;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Core\Orm\Exception\ModelException;

class DocumentPdfUploader extends AbstractS3Uploader
{
    public function getAllowedTypes(): array
    {
        return [
            'application/pdf',
        ];
    }

    /**
     * Attaches an uploaded PDF file object to a document
     * and replaces any existing PDF attachments.
     *
     * @throws ModelException
     */
    public function attachToDocument(ReceivableDocument $document, File $file): void
    {
        // delete any existing pdf attachments
        foreach (Attachment::allForObject($document, Attachment::LOCATION_PDF) as $attachment) {
            $attachment->delete();
        }

        // save the new pdf as an attachment
        $attachment = new Attachment();
        $attachment->setParent($document);
        $attachment->setFile($file);
        $attachment->location = Attachment::LOCATION_PDF;
        $attachment->saveOrFail();
    }
}
