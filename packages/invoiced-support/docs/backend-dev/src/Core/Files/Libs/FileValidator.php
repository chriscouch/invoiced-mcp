<?php

namespace App\Core\Files\Libs;

use App\Core\Files\Exception\UploadException;

class FileValidator
{
    /** @var array<string, list<string>> List of allowed file extensions with mime types for file upload */
    private const EXT_MIME_MAP = [
        // Images
        'png'  => ['image/png'],
        'jpeg' => ['image/jpeg'],
        'jpg'  => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'heic' => ['image/heic'],
        'heif' => ['image/heif'],
        'tiff' => ['image/tiff'],

        // MS Word
        'doc'  => [
            'application/msword',
            'application/vnd.ms-word',
            'application/vnd.ms-word.document.macroEnabled.12',
            'application/vnd.ms-word.template.macroEnabled.12',
        ],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        ],

        // MS Excel
        'xls'  => [
            'application/msexcel',
            'application/vnd.ms-excel',
            'application/vnd.ms-excel.sheet.macroEnabled.12',
            'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
            'application/vnd.ms-excel.template.macroEnabled.12',
            'application/vnd.ms-excel.addin.macroEnabled.12',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        ],

        // MS PowerPoint
        'ppt'  => [
            'application/mspowerpoint',
            'application/vnd.ms-powerpoint',
            'application/vnd.ms-powerpoint.addin.macroEnabled.12',
            'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
            'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
            'application/vnd.ms-powerpoint.template.macroEnabled.12',
        ],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.presentationml.template',
            'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        ],

        // iWork
        'pages'   => ['application/x-iwork-pages-sffpages'],
        'keynote' => ['application/x-iwork-keynote-sffkey'],
        'numbers' => ['application/x-iwork-numbers-sffnumbers'],

        // Misc
        'txt' => ['text/plain', 'text/x-plain'],
        'rtf' => ['text/rtf', 'application/rtf', 'application/x-rtf'],
        'pdf' => ['application/pdf'],
        'zip' => ['application/x-compressed', 'application/zip', 'application/x-zip-compressed'],
        'csv' => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
    ];

    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20 MB

    private const FILE_SIGNATURES = [
        'jpg' => ["\xFF\xD8\xFF"], // JPEG
        'jpeg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89PNG\r\n\x1A\n"], // PNG
        'gif' => ["GIF87a", "GIF89a"], // GIF
        'pdf' => ["%PDF"], // PDF
        'zip' => ["PK\x03\x04"], // ZIP, DOCX, XLSX, PPTX
        'docx' => ["PK\x03\x04"], // Office files (OpenXML)
        'xlsx' => ["PK\x03\x04"],
        'pptx' => ["PK\x03\x04"],
        'doc' => ["\xD0\xCF\x11\xE0"], // Older Office formats (OLE2)
        'xls' => ["\xD0\xCF\x11\xE0"],
        'ppt' => ["\xD0\xCF\x11\xE0"],
    ];

    /**
     * Checks if the file size is within the allowed limit (20 MB).
     */
    public static function validateFileSize(string $filePath): bool
    {
        return filesize($filePath) <= self::MAX_FILE_SIZE;
    }

    /**
     * Validates that the file's MIME type matches its extension strictly.
     */
    public static function validateFileStrict(string $file, string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = mime_content_type($file);

        if (!$mime || !isset(self::EXT_MIME_MAP[$ext])) {
            return false;
        }

        return in_array($mime, self::EXT_MIME_MAP[$ext]);
    }

    /**
     * Sanitizes a filename by keeping only alphanumeric characters, dashes, and underscores,
     * preserving the original extension if it is allowed.
     */
    public static function sanitizeFilename(string $filename): ?string {
        // get the file extension (last part after dot)
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // check if extension is allowed
        if (!isset(self::EXT_MIME_MAP[$ext])) {
            return null;
        }

        // get the filename without extension
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // replace all non-alphanumeric characters with underscore
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);

        // return sanitized filename with original extension
        return $name . '.' . $ext;
    }

    public static function validateFileSignature(string $file, string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset(self::FILE_SIGNATURES[$ext])) {
            // If we don't have a defined signature, skip
            return true;
        }

        $handle = fopen($file, 'rb');
        if (!$handle) {
            return false;
        }

        // Read the first 8 bytes
        $bytes = fread($handle, 8);
        fclose($handle);

        if ($bytes === false) {
            return false;
        }

        foreach (self::FILE_SIGNATURES[$ext] as $signature) {
            if (str_starts_with($bytes, $signature)) {
                return true;
            }
        }

        return false;
    }
}
