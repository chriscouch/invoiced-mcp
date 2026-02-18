<?php

namespace App\Tests\Companies\Verification;

use App\Companies\Enums\PhoneVerificationChannel;
use App\Companies\Verification\PhoneVerification;
use App\Tests\AppTestCase;
use Mockery;
use stdClass;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Twilio\Rest\Client;

class PhoneVerificationTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getVerification(Client $twilio): PhoneVerification
    {
        $storage = new CacheStorage(self::getService('test.cache'));

        return new PhoneVerification($storage, self::getService('test.lock_factory'), $twilio, 'test', self::getService('test.database'));
    }

    public function testComplete(): void
    {
        $twilio = Mockery::mock(Client::class);
        $twilio->verify = new stdClass(); /* @phpstan-ignore-line */
        $twilio->verify->v2 = Mockery::mock();
        $services = new stdClass();
        $services->verifications = Mockery::mock();
        $services->verifications
            ->shouldReceive('create')
            ->withArgs(['+11234567890', 'sms'])
            ->once();
        $services->verificationChecks = Mockery::mock();
        $services->verificationChecks->shouldReceive('create')
            ->withArgs([['code' => 123456, 'to' => '+11234567890']])
            ->andReturn((object) ['status' => 'approved']);
        $twilio->verify->v2->shouldReceive('services')
            ->withArgs(['test'])
            ->andReturn($services);

        $verification = $this->getVerification($twilio);

        $companyPhone = $verification->start(self::$company, '1', '1234567890', PhoneVerificationChannel::Sms);

        $this->assertNull($companyPhone->verified_at);

        $verification->complete($companyPhone, '123456');

        $this->assertNotNull($companyPhone->verified_at);
    }
}
