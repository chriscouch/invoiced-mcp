<?php

namespace App\Themes\Traits;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\Pdf\Exception\PdfException;
use App\Core\Pdf\PdfStreamer;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This trait can be added to API routes in order to be able to
 * generate PDF responses when application/pdf is requested as
 * the content type.
 */
trait PdfApiTrait
{
    protected function shouldReturnPdf(Request $request): bool
    {
        return in_array('application/pdf', $request->getAcceptableContentTypes());
    }

    /**
     * Builds a PDF in response to an application/pdf API request.
     *
     * @throws InvalidRequest
     */
    protected function buildResponsePdf(SendableDocumentInterface $document, string $locale): Response
    {
        $streamer = new PdfStreamer();
        $pdfBuilder = $document->getPdfBuilder();

        if (!$pdfBuilder) {
            throw new InvalidRequest('PDF not available');
        }

        try {
            return $streamer->stream($pdfBuilder, $locale);
        } catch (PdfException $e) {
            return new Response($e->getMessage());
        }
    }
}
