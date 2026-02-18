<?php

namespace App\Core\Authentication\Saml;

use App\Core\Authentication\LoginStrategy\AbstractSamlLoginStrategy;
use DOMDocument;
use Exception;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Utils;
use OneLogin\Saml2\ValidationError;

class SamlResponseSimplified extends Response
{
    const INVOICED_EMAIL_ATTRIBUTE = 'InvoicedEmail';

    /**
     * SamlResponseSimplified constructor.
     *
     * @throws ValidationError
     */
    public function __construct(string $response)
    {
        $document = null;
        try {
            $document = new DOMDocument();
            $document = Utils::loadXML($document, $response);
        } catch (Exception) {
        }
        if (!$document) {
            throw new ValidationError('SAML Response could not be processed', ValidationError::INVALID_XML_FORMAT);
        }
        $this->document = $document;

        $encryptedIdDataEntries = $this->_queryAssertion('/saml:Subject/saml:EncryptedID/xenc:EncryptedData');
        // we cant decrypt data without settings
        $this->encrypted = 1 == $encryptedIdDataEntries->length;
    }

    public function getEmail(): ?string
    {
        if ($email = $this->extractNodeValue("/saml:AttributeStatement/saml:Attribute[@Name='".self::INVOICED_EMAIL_ATTRIBUTE."']")) {
            return $email;
        }

        foreach (AbstractSamlLoginStrategy::AVAILABLE_RESPONSE_KEYS as $key) {
            if ($email = $this->extractNodeValue("/saml:AttributeStatement/saml:Attribute[@Name='$key']")) {
                return $email;
            }
        }

        return $this->extractNodeValue('/saml:Subject/saml:NameID');
    }

    private function extractNodeValue(string $xpath): ?string
    {
        $nodes = $this->_queryAssertion($xpath);
        if (1 == $nodes->length) {
            $nameId = $nodes->item(0);
            if (null === $nameId) {
                return null;
            }
            $nameId = $nameId->nodeValue;
            if (filter_var($nameId, FILTER_VALIDATE_EMAIL)) {
                return $nameId;
            }
        }

        return null;
    }
}
