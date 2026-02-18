<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\Core\RestApi\Exception\InvalidRequest;
use App\PaymentProcessing\Libs\AdyenDisputeFileValidator;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

class AdyenDisputeFileValidatorTest extends TestCase
{
    private AdyenDisputeFileValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AdyenDisputeFileValidator();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testValidateFilesWithValidPdf(): void
    {
        $file = $this->createMockUploadedFile('application/pdf', 1024 * 1024); // 1MB
        $fileBag = $this->createFileBag([$file]);

        $this->validator->validateFiles($fileBag);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidateFilesWithValidJpegImage(): void
    {
        $file = $this->createMockUploadedFile('image/jpeg', 5 * 1024 * 1024); // 5MB
        $fileBag = $this->createFileBag([$file]);

        $this->validator->validateFiles($fileBag);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidateFilesWithValidJpgImage(): void
    {
        $file = $this->createMockUploadedFile('image/jpg', 8 * 1024 * 1024); // 8MB
        $fileBag = $this->createFileBag([$file]);

        $this->validator->validateFiles($fileBag);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidateFilesWithValidTiffImage(): void
    {
        $file = $this->createMockUploadedFile('image/tiff', 10 * 1024 * 1024); // 10MB (max allowed)
        $fileBag = $this->createFileBag([$file]);

        $this->validator->validateFiles($fileBag);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidateFilesWithMultipleValidFiles(): void
    {
        $files = [
            $this->createMockUploadedFile('application/pdf', 1024 * 1024), // 1MB PDF
            $this->createMockUploadedFile('image/jpeg', 5 * 1024 * 1024), // 5MB JPEG
            $this->createMockUploadedFile('image/tiff', 8 * 1024 * 1024), // 8MB TIFF
        ];
        $fileBag = $this->createFileBag($files);

        $this->validator->validateFiles($fileBag);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidateFilesThrowsExceptionForInvalidMimeType(): void
    {
        $file = $this->createMockUploadedFile('text/plain', 1024); // Invalid MIME type
        $fileBag = $this->createFileBag([$file]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Invalid file type. Only JPG, JPEG, TIFF images and PDF documents are allowed.');

        $this->validator->validateFiles($fileBag);
    }

    public function testValidateFilesThrowsExceptionForUnsupportedImageType(): void
    {
        $file = $this->createMockUploadedFile('image/png', 1024 * 1024); // PNG not supported
        $fileBag = $this->createFileBag([$file]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Invalid file type. Only JPG, JPEG, TIFF images and PDF documents are allowed.');

        $this->validator->validateFiles($fileBag);
    }

    public function testValidateFilesThrowsExceptionForPdfTooLarge(): void
    {
        $file = $this->createMockUploadedFile('application/pdf', 3 * 1024 * 1024); // 3MB (exceeds 2MB limit)
        $fileBag = $this->createFileBag([$file]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('File is too large. Maximum allowed file size for application/pdf is 2MB.');

        $this->validator->validateFiles($fileBag);
    }

    public function testValidateFilesThrowsExceptionForImageTooLarge(): void
    {
        $file = $this->createMockUploadedFile('image/jpeg', 11 * 1024 * 1024); // 11MB (exceeds 10MB limit)
        $fileBag = $this->createFileBag([$file]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('File is too large. Maximum allowed file size for image/jpeg is 10MB.');

        $this->validator->validateFiles($fileBag);
    }

    public function testValidateFilesThrowsExceptionForTiffImageTooLarge(): void
    {
        $file = $this->createMockUploadedFile('image/tiff', 11 * 1024 * 1024); // 11MB (exceeds 10MB limit)
        $fileBag = $this->createFileBag([$file]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('File is too large. Maximum allowed file size for image/tiff is 10MB.');

        $this->validator->validateFiles($fileBag);
    }

    public function testValidateFilesWithPdfAtExactSizeLimit(): void
    {
        $file = $this->createMockUploadedFile('application/pdf', 2 * 1024 * 1024); // Exactly 2MB
        $fileBag = $this->createFileBag([$file]);

        $this->validator->validateFiles($fileBag);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidateFilesWithImageAtExactSizeLimit(): void
    {
        $file = $this->createMockUploadedFile('image/jpeg', 10 * 1024 * 1024); // Exactly 10MB
        $fileBag = $this->createFileBag([$file]);

        $this->validator->validateFiles($fileBag);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidateFilesThrowsExceptionForPdfJustOverLimit(): void
    {
        $file = $this->createMockUploadedFile('application/pdf', (2 * 1024 * 1024) + 1); // 2MB + 1 byte
        $fileBag = $this->createFileBag([$file]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('File is too large. Maximum allowed file size for application/pdf is 2MB.');

        $this->validator->validateFiles($fileBag);
    }

    public function testValidateFilesThrowsExceptionForImageJustOverLimit(): void
    {
        $file = $this->createMockUploadedFile('image/jpeg', (10 * 1024 * 1024) + 1); // 10MB + 1 byte
        $fileBag = $this->createFileBag([$file]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('File is too large. Maximum allowed file size for image/jpeg is 10MB.');

        $this->validator->validateFiles($fileBag);
    }

    public function testValidateFilesWithEmptyFileArray(): void
    {
        $fileBag = $this->createFileBag([]);

        $this->validator->validateFiles($fileBag);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testValidateFilesThrowsExceptionForMixedValidAndInvalidFiles(): void
    {
        $files = [
            $this->createMockUploadedFile('application/pdf', 1024 * 1024), // Valid PDF
            $this->createMockUploadedFile('text/plain', 1024), // Invalid MIME type
        ];
        $fileBag = $this->createFileBag($files);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Invalid file type. Only JPG, JPEG, TIFF images and PDF documents are allowed.');

        $this->validator->validateFiles($fileBag);
    }

    public function testValidateFilesStopsOnFirstInvalidFile(): void
    {
        $files = [
            $this->createMockUploadedFile('text/plain', 1024), // Invalid MIME type (should trigger exception)
            $this->createMockUploadedFile('application/pdf', 5 * 1024 * 1024), // Valid PDF but won't be reached
        ];
        $fileBag = $this->createFileBag($files);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Invalid file type. Only JPG, JPEG, TIFF images and PDF documents are allowed.');

        $this->validator->validateFiles($fileBag);
    }

    /**
     * Create a mock UploadedFile with specified MIME type and size
     */
    private function createMockUploadedFile(string $mimeType, int $size): UploadedFile
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getMimeType')->andReturn($mimeType);
        $file->shouldReceive('getSize')->andReturn($size);

        return $file;
    }

    /**
     * Create a FileBag with the given files
     */
    private function createFileBag(array $files): FileBag
    {
        $fileBag = Mockery::mock(FileBag::class);
        $fileBag->shouldReceive('get')->with('files')->andReturn($files);

        return $fileBag;
    }
}