<?php

namespace App\PaymentProcessing\ValueObjects;

class DisputeDocument
{
    public function __construct(
        public string $content,
        public string $contentType,
        public string $defenseDocumentTypeCode,
    ) {
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'contentType' => $this->contentType,
            'defenseDocumentTypeCode' => $this->defenseDocumentTypeCode,
        ];
    }
}
