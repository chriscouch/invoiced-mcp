<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Saml\SamlResponseSimplified;
use App\Tests\AppTestCase;

class SamlResponseSimplifiedTest extends AppTestCase
{
    public function testMain(): void
    {
        $response = new SamlResponseSimplified($this->nonEncryptedResponse());
        $this->assertFalse($response->encrypted);
        $this->assertEquals('test@test.com', $response->getEmail());

        $response = new SamlResponseSimplified($this->nonEncryptedResponse(SamlResponseSimplified::INVOICED_EMAIL_ATTRIBUTE));
        $this->assertEquals('test2@test.com', $response->getEmail());
    }

    private function nonEncryptedResponse(string $attributeName = 'random'): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
            <saml2p:Response xmlns:saml2p="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:xs="http://www.w3.org/2001/XMLSchema" Destination="https://andriy.ngrok.io/auth/sso/sp/acs" ID="id48841808127151231092331924" IssueInstant="2021-03-17T17:19:57.872Z" Version="2.0">
               <saml2:Assertion xmlns:saml2="urn:oasis:names:tc:SAML:2.0:assertion" ID="id488418081278518531967260" IssueInstant="2021-03-17T17:19:57.872Z" Version="2.0">
                  <saml2:Subject>
                     <saml2:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">test@test.com</saml2:NameID>
                  </saml2:Subject>
                  <saml2:AttributeStatement>
                     <saml2:Attribute Name="'.$attributeName.'" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:unspecified"><saml2:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">test2@test.com</saml2:AttributeValue></saml2:Attribute>
                  </saml2:AttributeStatement>
               </saml2:Assertion>
            </saml2p:Response>';
    }
}
