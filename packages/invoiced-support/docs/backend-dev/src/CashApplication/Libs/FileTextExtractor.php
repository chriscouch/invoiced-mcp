<?php

namespace App\CashApplication\Libs;

use Exception;
use Imagick;
use ImagickException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;

class FileTextExtractor implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function getPdfText(string $filePath, string $fileContents): string
    {
        try {
            $pdfParser = new Parser();
            $pdf = $pdfParser->parseContent($fileContents);
            if ($rawText = $pdf->getText()) {
                return $rawText;
            }
        } catch (Exception $e) {
            $this->logger->info('There was a problem parsing the PDF text.', ['exception' => $e]);

            return '';
        }

        $imagick = new Imagick();
        $imagick->pingImage($filePath);
        $pages = $imagick->getNumberImages();

        for ($x = 0; $x < $pages; ++$x) {
            $tempFilepath = $filePath.'-'.$x;
            $rawText = $this->getImageText($filePath.'['.$x.']', $tempFilepath);
            if ($rawText) {
                return $rawText;
            }
        }

        return '';
    }

    public function getImageText(string $readFilepath, string $writeFilepath): string
    {
        try {
            $imagick = new Imagick();
            $imagick->readImage($readFilepath);
            $imagick->setImageFormat('png');
            $imagick->setImageType(Imagick::IMGTYPE_GRAYSCALE);
            $imagick->contrastImage(true);
            $imagick->scaleImage($imagick->getImageWidth() * 4, $imagick->getImageHeight() * 4);
            $imagick->setImageDepth(24);
            $imagick->writeImage($writeFilepath);
        } catch (ImagickException $e) {
            $this->logger->info('There was a problem transforming the image.', ['exception' => $e]);

            return '';
        }

        try {
            $ocr = new TesseractOCR($writeFilepath);

            return $ocr->run();
        } catch (TesseractOcrException $e) {
            $this->logger->info('An error occurred during OCR processing.', ['exception' => $e]);

            return '';
        }
    }
}
