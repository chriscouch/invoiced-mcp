<?php

namespace App\Tests\Companies\Verification;

use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Verification\UsTaxIdVerification;
use App\Tests\AppTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UsTaxIdVerificationTest extends AppTestCase
{
    private static string $jsonDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$jsonDIR = __DIR__.'/data';
    }

    private function getVerification(?HttpClientInterface $client = null): UsTaxIdVerification
    {
        $client ??= new MockHttpClient();
        $loggerFactory = self::getService('test.logger_factory');
        $redis = self::getService('test.redis');
        $client = new UsTaxIdVerification('', $client, $loggerFactory, $redis, self::getService('test.cache'), self::getService('test.database'));
        $client->setPollWait(0);

        return $client;
    }

    public function testVerifyInvalidTaxId(): void
    {
        $this->expectException(BusinessVerificationException::class);
        $this->expectExceptionMessage('Tax ID number should be 9-digit numeric');
        $verification = $this->getVerification();
        $verification->verify(random_int(100000, 999999), 'Test Verify Invalid Tax ID', '000', true);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testVerifyIrsCodeNegative1(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_-1.json')),
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_7.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify Negative 1', '123456789', true);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testVerifyIrsCode0(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_0.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 0', '123456789', true);
    }

    public function testVerifyIrsCode1(): void
    {
        $this->expectException(BusinessVerificationException::class);
        $this->expectExceptionMessage('We were unable to validate the given name and tax ID with the IRS.');
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_1.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 1', '123456789', true);
    }

    public function testVerifyIrsCode2(): void
    {
        $this->expectException(BusinessVerificationException::class);
        $this->expectExceptionMessage('We were unable to validate the given name and tax ID with the IRS.');
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_2.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 2', '123456789', true);
    }

    public function testVerifyIrsCode3(): void
    {
        $this->expectException(BusinessVerificationException::class);
        $this->expectExceptionMessage('We were unable to validate the given name and tax ID with the IRS.');
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_3.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 3', '123456789', true);
    }

    public function testVerifyIrsCode6WithEin(): void
    {
        $this->expectException(BusinessVerificationException::class);
        $this->expectExceptionMessage('We were unable to validate the given name and tax ID with the IRS.');
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_6.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 6', '123456789', true);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testVerifyIrsCode6WithSsn(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_6.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 6', '123456789', false);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testVerifyIrsCode7WithEin(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_7.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 7', '123456789', true);
    }

    public function testVerifyIrsCode7WithSsn(): void
    {
        $this->expectException(BusinessVerificationException::class);
        $this->expectExceptionMessage('We were unable to validate the given name and tax ID with the IRS.');
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_7.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 7', '123456789', false);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testVerifyIrsCode8WithEin(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_8.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 8', '123456789', true);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testVerifyIrsCode8WithSsn(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_8.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 8', '123456789', false);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testVerifyIrsCode10(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_10.json')),
            new MockResponse((string) file_get_contents(self::$jsonDIR.'/compliancely_response_7.json')),
        ]);
        $verification = $this->getVerification($client);

        $verification->verify(random_int(100000, 999999), 'Test Verify 10', '123456789', true);
    }
}
