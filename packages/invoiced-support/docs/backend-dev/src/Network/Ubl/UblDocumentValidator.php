<?php

namespace App\Network\Ubl;

use App\Network\Exception\UblValidationException;
use DOMDocument;
use Greenter\Ubl\Resolver\UblPathResolver;
use Greenter\Ubl\SchemaValidator;
use Greenter\Ubl\UblValidator;
use XSLTProcessor;

/**
 * Validates UBL v2.3 documents using the two-phase validation
 * process described in [1].
 *
 * [1] http://docs.oasis-open.org/ubl/os-UBL-2.3/UBL-2.3.html#A-UBL-2.3-CODE-LISTS-AND-TWO-PHASE-VALIDATION
 */
class UblDocumentValidator
{
    private SchemaValidator $schemaValidator;

    public function __construct(private string $projectDir)
    {
    }

    /**
     * @throws UblValidationException
     */
    public function validate(string $data): void
    {
        $document = new DOMDocument();
        @$document->loadXML($data);
        if (empty($document->documentElement)) {
            throw new UblValidationException('Invalid XML Document');
        }

        $this->validateSchema($document);
        $this->validateCodeLists($document);
    }

    /**
     * Performs the first phase of validation.
     *
     * @throws UblValidationException
     */
    private function validateSchema(DOMDocument $document): void
    {
        $validator = new UblValidator();
        $this->schemaValidator = new SchemaValidator();
        $validator->schemaValidator = $this->schemaValidator;
        $validator->pathResolver = new UblPathResolver();
        $validator->pathResolver->baseDirectory = $this->projectDir.'/assets/ubl-xsd';
        $validator->pathResolver->version = '2.3';

        if (!$validator->isValid($document)) {
            $error = $validator->getError();
            if (str_starts_with($error, 'XSD Path: :')) {
                // Hide file path error
                throw new UblValidationException('XSD not supported.');
            }

            throw new UblValidationException($error ?: 'The UBL schema is not valid.');
        }
    }

    /**
     * @throws UblValidationException
     */
    private function validateCodeLists(DOMDocument $document): void
    {
        $xsldoc = new DOMDocument();
        $xsldoc->load($this->projectDir.'/assets/ubl-code-lists/UBL-DefaultDTQ-2.3.xsl');

        libxml_use_internal_errors(true);
        $xsl = new XSLTProcessor();

        if (false === $xsl->importStyleSheet($xsldoc)) {
            throw new UblValidationException($this->getCodeListErrorMessage());
        }

        if (false === $xsl->transformToXML($document)) {
            throw new UblValidationException($this->getCodeListErrorMessage());
        }

        libxml_use_internal_errors(false);
    }

    private function getCodeListErrorMessage(): string
    {
        $errors = iterator_to_array($this->schemaValidator->extractErrors());

        $lines = [];
        foreach ($errors as $error) {
            $lines[] = (string) $error;
        }

        $error = join(PHP_EOL, $lines);

        return $error ?: 'The document did not pass code list validation.';
    }
}
