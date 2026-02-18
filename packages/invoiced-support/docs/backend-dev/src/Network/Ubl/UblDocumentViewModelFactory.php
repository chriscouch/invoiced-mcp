<?php

namespace App\Network\Ubl;

use App\Network\Ubl\ViewModelFactory\CreditNoteViewModelFactory;
use App\Network\Ubl\ViewModelFactory\InvoiceViewModelFactory;
use App\Network\Enums\NetworkDocumentType;
use App\Network\Interfaces\UblDocumentViewModelFactoryInterface;
use App\Network\Ubl\ViewModel\DocumentViewModel;
use App\Network\Ubl\ViewModelFactory\GenericViewModelFactory;
use SimpleXMLElement;

final class UblDocumentViewModelFactory
{
    private const DOCUMENT_TRANSFORMERS = [
        'CreditNote' => CreditNoteViewModelFactory::class,
        'Invoice' => InvoiceViewModelFactory::class,
    ];

    public function make(string $data): DocumentViewModel
    {
        $xml = UblReader::parse($data);

        return $this->addCommonInformation(
            $this->getFactory($xml->getName())->make($xml),
            $xml
        );
    }

    private function addCommonInformation(DocumentViewModel $viewModel, SimpleXMLElement $xml): DocumentViewModel
    {
        $type = $xml->getName();
        $viewModel->setType(NetworkDocumentType::fromName($type));

        $viewModel->setReference((string) UblReader::xpathToString($xml, '/doc:'.$type.'/cbc:ID'));

        // Attachments
        foreach (UblReader::xpath($xml, '/doc:'.$type.'/cac:AdditionalDocumentReference') as $additionalDocument) {
            $id = UblReader::xpathToString($additionalDocument, 'cbc:ID');
            $filename = UblReader::xpathToString($additionalDocument, 'cac:Attachment/cbc:EmbeddedDocumentBinaryObject/@filename') ?? $id;
            $mimeCode = UblReader::xpathToString($additionalDocument, 'cac:Attachment/cbc:EmbeddedDocumentBinaryObject/@mimeCode');
            if (!$mimeCode) {
                continue;
            }

            $viewModel->addAttachment([
                'id' => $id,
                'name' => $filename,
                'type' => $mimeCode,
                'content' => function () use ($additionalDocument): string {
                    $binaryObject = UblReader::xpathToString($additionalDocument, 'cac:Attachment/cbc:EmbeddedDocumentBinaryObject');

                    return base64_decode((string) $binaryObject);
                },
            ]);
        }

        return $viewModel;
    }

    private function getFactory(string $type): UblDocumentViewModelFactoryInterface
    {
        if (!isset(self::DOCUMENT_TRANSFORMERS[$type])) {
            return new GenericViewModelFactory();
        }

        $class = self::DOCUMENT_TRANSFORMERS[$type];

        return new $class();
    }
}
