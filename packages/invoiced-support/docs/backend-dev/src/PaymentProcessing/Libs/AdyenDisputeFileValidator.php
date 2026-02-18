<?php

namespace App\PaymentProcessing\Libs;

use App\Core\RestApi\Exception\InvalidRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

class AdyenDisputeFileValidator
{
    private const string PDF_MIME_TYPE = 'application/pdf';
    private const array ALLOWED_MIME_TYPES = [
        self::PDF_MIME_TYPE,
        'image/jpg',
        'image/jpeg',
        'image/tiff',
    ];
    private const int MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024; // 10MB
    private const int MAX_PDF_SIZE_BYTES = 2 * 1024 * 1024;    // 2MB

    /**
     * @throws InvalidRequest if any file is invalid
     */
    public function validateFiles(FileBag $fileBag): void
    {
        $files = $fileBag->get('files');
        /** @var UploadedFile $file */
        foreach ($files as $file) {
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
                throw new InvalidRequest(
                    'Invalid file type. Only JPG, JPEG, TIFF images and PDF documents are allowed.'
                );
            }

            $fileSize = $file->getSize();
            $maxSize = ($mimeType === self::PDF_MIME_TYPE)
                ? self::MAX_PDF_SIZE_BYTES
                : self::MAX_IMAGE_SIZE_BYTES;

            if ($fileSize > $maxSize) {
                $maxSizeMB = ($mimeType === self::PDF_MIME_TYPE) ? '2MB' : '10MB';
                throw new InvalidRequest(
                    "File is too large. Maximum allowed file size for {$mimeType} is {$maxSizeMB}."
                );
            }
        }
    }
}