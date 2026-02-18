<?php

namespace App\Tests\Companies\Verification;

use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Verification\AddressVerification;
use App\Tests\AppTestCase;
use CommerceGuys\Addressing\Address;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AddressVerificationTest extends AppTestCase
{
    private static string $jsonDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$jsonDIR = __DIR__.'/data';
    }

    private function getVerification(?HttpClientInterface $client = null): AddressVerification
    {
        $client ??= new MockHttpClient();
        $loggerFactory = self::getService('test.logger_factory');

        return new AddressVerification('', $client, $loggerFactory);
    }

    private function getAddress(): Address
    {
        return new Address(
            countryCode: 'US',
            administrativeArea: 'TX',
            locality: 'Austin',
            postalCode: '78735',
            addressLine1: '5301 Southwest Parkway',
            addressLine2: 'Suite 470',
        );
    }

    public function testCountryIsSupported(): void
    {
        $verification = $this->getVerification();
        $this->assertTrue($verification->countryIsSupported('US'));
        $this->assertTrue($verification->countryIsSupported('CA'));
        $this->assertFalse($verification->countryIsSupported('UY'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateSubPremise(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/google_response_subpremise_valid.json')),
        ]);

        $verification = $this->getVerification($client);

        $verification->validate($this->getAddress());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidatePremise(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/google_response_premise_valid.json')),
        ]);

        $verification = $this->getVerification($client);

        $verification->validate($this->getAddress());
    }

    public function testValidateNotGranularEnough(): void
    {
        $this->expectException(BusinessVerificationException::class);
        $this->expectExceptionMessage('We were unable to validate the given address.');
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/google_response_other_granularity.json')),
        ]);

        $verification = $this->getVerification($client);

        $verification->validate($this->getAddress());
    }

    public function testValidateHasReplacedComponents(): void
    {
        $this->expectException(BusinessVerificationException::class);
        $this->expectExceptionMessage('We were unable to validate the given address.');
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/google_response_replaced_components.json')),
        ]);

        $verification = $this->getVerification($client);

        $verification->validate($this->getAddress());
    }
}
