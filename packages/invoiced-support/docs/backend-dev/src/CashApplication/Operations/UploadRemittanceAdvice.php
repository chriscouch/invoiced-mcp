<?php

namespace App\CashApplication\Operations;

use App\CashApplication\Models\RemittanceAdvice;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Core\Orm\Exception\ModelException;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\DocVerifyClient;
use Carbon\CarbonImmutable;

class UploadRemittanceAdvice
{
    public function __construct(
        private DocVerifyClient $docVerify,
        private CreateRemittanceAdvice $create,
    ) {
    }

    /**
     * @throws ModelException|IntegrationApiException
     */
    public function upload(File $file): RemittanceAdvice
    {
        $handle = fopen($file->url, 'r');
        if (!$handle) {
            throw new IntegrationApiException('Could not open file');
        }

        // Upload the document to doc-verify for extraction
        $result = $this->docVerify->extract($handle);

        $extractionResult = $result['extraction_result'];
        if ('ADVE001' != $extractionResult['code']['label']) {
            throw new IntegrationApiException($extractionResult['code']['message']);
        }

        $extractedData = $extractionResult['data'];

        $lines = [];
        foreach ($extractedData['line_items'] as $line) {
            $type = $line['type'];
            if ('invoice_payment' == $type) {
                $lines[] = [
                    'document_number' => $line['invoice_id'],
                    'description' => $line['document_number'],
                    'gross_amount_paid' => $line['gross_amount'],
                    'discount' => $line['discount_amount'],
                    'net_amount_paid' => $line['net_amount'],
                ];
            }
        }

        $params = [
            'payment_method' => $extractedData['payment']['method'] ?? null,
            'currency' => $extractedData['payment']['currency'] ?? null,
            'payment_reference' => $extractedData['payment']['reference'] ?? null,
            'payment_date' => $extractedData['payment']['remittance_date'] ?? CarbonImmutable::now()->toDateString(),
            'lines' => $lines,
        ];

        $advice = $this->create->create($params);

        // save the uploaded file as an attachment
        $attachment = new Attachment();
        $attachment->setParent($advice);
        $attachment->setFile($file);
        $attachment->saveOrFail();

        return $advice;
    }
}
