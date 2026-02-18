<?php

namespace App\Tests\Network\Ubl;

use App\Network\Exception\UblValidationException;
use App\Network\Ubl\UblDocumentValidator;
use App\Tests\AppTestCase;
use Exception;

class UblDocumentValidatorTest extends AppTestCase
{
    private function getValidator(): UblDocumentValidator
    {
        return new UblDocumentValidator(self::$kernel->getProjectDir());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidExamples(): void
    {
        $validator = $this->getValidator();
        foreach (glob(__DIR__.'/data/examples/*') as $filename) { /* @phpstan-ignore-line */
            // Skipped because we do not support a detached signature
            if (str_contains($filename, 'UBL-Invoice-2.0-Detached-Signature.xml')) {
                continue;
            }

            try {
                $xml = (string) file_get_contents($filename);
                $validator->validate($xml);
            } catch (UblValidationException $e) {
                throw new Exception('Unexpected exception when validating '.$filename, $e->getCode(), $e);
            }
        }
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testOrderGood(): void
    {
        $xml = (string) file_get_contents(__DIR__.'/data/order-test-good.xml');
        $this->getValidator()->validate($xml);
    }

    public function testOrderBad1(): void
    {
        $this->expectException(UblValidationException::class);
        $this->expectExceptionMessage("Code: 1871, Line: 50, Column: 0, Message: Element '{urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2}ChannelCod': This element is not expected. Expected is one of ( {urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2}UBLExtensions, {urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2}ChannelCode, {urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2}Channel, {urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2}Value ).");
        $xml = (string) file_get_contents(__DIR__.'/data/order-test-bad1.xml');
        $this->getValidator()->validate($xml);
    }

    public function testOrderBad2(): void
    {
        $this->expectException(UblValidationException::class);
        $this->expectExceptionMessage("Code: 1, Line: 0, Column: 0, Message: Value supplied 'LA' is unacceptable for constraints identified by 'Channel-2.0 Channel-2.1 Channel-2.2 Channel-2.3' in the context 'cbc:ChannelCode': /Order/cac:BuyerCustomerParty[1]/cac:Party[1]/cac:Contact[1]/cac:OtherCommunication[1]/cbc:ChannelCode[1]");
        $xml = (string) file_get_contents(__DIR__.'/data/order-test-bad2.xml');
        $this->getValidator()->validate($xml);
    }
}
