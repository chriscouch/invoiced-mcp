<?php

namespace App\Tests\Companies\Libs;

use App\Companies\Enums\FraudOutcome;
use App\Companies\FraudScore\IpAddressFraudScore;
use App\Companies\Libs\SignUpFraudDetector;
use App\Companies\Models\Company;
use App\Companies\ValueObjects\FraudScore;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\IpLookup;
use App\Core\Utils\IpUtilities;
use App\Tests\AppTestCase;

class SignUpFraudDetectorTest extends AppTestCase
{
    private static Company $company2;
    private static Company $company3;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        $user = self::getService('test.user_context')->get();
        $user->ip = '127.0.0.123';
        $user->saveOrFail();

        self::getService('test.tenant')->clear();
        $fraudCompany1 = new Company();
        $fraudCompany1->name = 'some bank company';
        $fraudCompany1->username = 'somebankcompany';
        $fraudCompany1->fraud = true;
        $fraudCompany1->email = 'test@fraud.com';
        $fraudCompany1->creator_id = self::getService('test.user_context')->get()->id();
        $fraudCompany1->saveOrFail();
        self::$company2 = $fraudCompany1;

        self::getService('test.tenant')->clear();
        $fraudCompany2 = new Company();
        $fraudCompany2->name = 'some bank company';
        $fraudCompany2->username = 'somebankcompany2';
        $fraudCompany2->fraud = true;
        $fraudCompany2->email = 'test@fraud.com';
        $fraudCompany2->creator_id = self::getService('test.user_context')->get()->id();
        $fraudCompany2->saveOrFail();
        self::$company3 = $fraudCompany2;

        IpUtilities::blockIp('127.0.0.123', self::getService('test.database'));
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$company2->delete();
        self::$company3->delete();
    }

    private function getDetector(): SignUpFraudDetector
    {
        return self::getService('test.sign_up_fraud_detector');
    }

    public function testIsFraudulentPass(): void
    {
        $detector = $this->getDetector();
        $userParams = [];
        $companyParams = [
            'name' => 'pass',
            'email' => 'test@example.com',
            'country' => 'GB',
        ];
        $requestParams = [
            'ip' => '25.25.25.25',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/109.0',
            'accept_language' => 'en-US,en;q=0.5"',
        ];
        $this->assertEquals(new FraudScore(
            score: 0,
            determination: FraudOutcome::Pass,
            log: 'Calculating fraud score for new company sign up
Company parameters: {"name":"pass","email":"test@example.com","country":"GB"}
User parameters: {}
Request parameters: {"ip":"25.25.25.25","user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/109.0","accept_language":"en-US,en;q=0.5\""}
Detected personal email domain (no penalty): example.com
Mean Sign Ups/Hour: 0. Standard Deviation: 0
1 companies with @example.com email address created in last hour
Score: 0
Outcome: PASS',
        ), $detector->evaluate($userParams, $companyParams, $requestParams));
    }

    public function testIsFraudulentFail(): void
    {
        $detector = $this->getDetector();
        $userParams = [];
        $companyParams = [
            'name' => 'some bank company',
            'email' => 'test@mail.ru',
        ];
        $requestParams = [
            'ip' => '127.0.0.123',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/109.0',
            'accept_language' => 'en-US,en;q=0.5"',
        ];
        $this->assertEquals(new FraudScore(
            score: 15,
            determination: FraudOutcome::Block,
            log: 'Calculating fraud score for new company sign up
Company parameters: {"name":"some bank company","email":"test@mail.ru"}
User parameters: {}
Request parameters: {"ip":"127.0.0.123","user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/109.0","accept_language":"en-US,en;q=0.5\""}
Company has "bank" in the name
Email domain (mail.ru) has a banned TLD: .ru
Detected personal email domain (no penalty): mail.ru
IP address (127.0.0.123) is on our block list
Mean Sign Ups/Hour: 0. Standard Deviation: 0
2 companies with same name created in last week: some bank company
3 companies with IP address created in last hour: 127.0.0.123
Score: 15
Outcome: BLOCK',
        ), $detector->evaluate($userParams, $companyParams, $requestParams));
    }

    public function testIsFraudulentFail2(): void
    {
        $ipLookup = \Mockery::mock(IpLookup::class);
        $ipLookup->shouldReceive('get')
            ->andReturn((object) ['country' => 'GB']);

        $detector = new SignUpFraudDetector(
            self::getService('test.logger_factory'),
            [new IpAddressFraudScore($ipLookup, self::getService('test.database'))],
        );
        $detector->setStatsd(new StatsdClient());
        $userParams = [];
        $companyParams = [
            'name' => 'ALL CAPS',
            'email' => 'test@gmail.com',
            'country' => 'US',
        ];
        $requestParams = [
            'ip' => '25.25.25.25',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/109.0',
            'accept_language' => 'en-US,en;q=0.5"',
        ];
        $this->assertEquals(new FraudScore(
            score: 2,
            determination: FraudOutcome::Warning,
            log: 'Calculating fraud score for new company sign up
Company parameters: {"name":"ALL CAPS","email":"test@gmail.com","country":"US"}
User parameters: {}
Request parameters: {"ip":"25.25.25.25","user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/109.0","accept_language":"en-US,en;q=0.5\""}
IP info: {"country":"GB"}
IP country (United Kingdom) does not match company country (United States)
Score: 2
Outcome: WARNING',
        ), $detector->evaluate($userParams, $companyParams, $requestParams));
    }

    public function testIsFraudulentDisposableEmailDomain(): void
    {
        $detector = $this->getDetector();
        $userParams = [];
        $companyParams = [
            'name' => 'mailnator',
            'email' => 'test@mailnator.com',
            'country' => 'GB',
        ];
        $requestParams = [
            'ip' => '25.25.25.25',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/109.0',
            'accept_language' => 'en-US,en;q=0.5"',
        ];
        $this->assertEquals(new FraudScore(
            score: 4,
            determination: FraudOutcome::Block,
            log: 'Calculating fraud score for new company sign up
Company parameters: {"name":"mailnator","email":"test@mailnator.com","country":"GB"}
User parameters: {}
Request parameters: {"ip":"25.25.25.25","user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/109.0","accept_language":"en-US,en;q=0.5\""}
Detected disposable email domain from exact match: mailnator.com
Mean Sign Ups/Hour: 0. Standard Deviation: 0
Score: 4
Outcome: BLOCK',
        ), $detector->evaluate($userParams, $companyParams, $requestParams));
    }

    public function testIsFraudulentDisposableWildcardEmailDomain(): void
    {
        $detector = $this->getDetector();
        $userParams = [];
        $companyParams = [
            'name' => 'mailnator',
            'email' => 'test@test.email-temp.com',
            'country' => 'GB',
        ];
        $requestParams = [
            'ip' => '25.25.25.25',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/109.0',
            'accept_language' => 'en-US,en;q=0.5"',
        ];
        $this->assertEquals(new FraudScore(
            score: 4,
            determination: FraudOutcome::Block,
            log: 'Calculating fraud score for new company sign up
Company parameters: {"name":"mailnator","email":"test@test.email-temp.com","country":"GB"}
User parameters: {}
Request parameters: {"ip":"25.25.25.25","user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/109.0","accept_language":"en-US,en;q=0.5\""}
Detected disposable email domain from wildcard match on email-temp.com: test.email-temp.com
Mean Sign Ups/Hour: 0. Standard Deviation: 0
Score: 4
Outcome: BLOCK',
        ), $detector->evaluate($userParams, $companyParams, $requestParams));
    }

    public function testIsFraudulentFailLongName(): void
    {
        $detector = $this->getDetector();
        $userParams = [];
        $companyParams = [
            'name' => str_repeat('x', 101),
            'email' => 'test@gmail.com',
            'country' => 'GB',
        ];
        $requestParams = [
            'ip' => '25.25.25.25',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/109.0',
            'accept_language' => 'en-US,en;q=0.5"',
        ];
        $this->assertEquals(new FraudScore(
            score: 1,
            determination: FraudOutcome::Pass,
            log: 'Calculating fraud score for new company sign up
Company parameters: {"name":"xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx","email":"test@gmail.com","country":"GB"}
User parameters: {}
Request parameters: {"ip":"25.25.25.25","user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/109.0","accept_language":"en-US,en;q=0.5\""}
Suspiciously long company name "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
Detected personal email domain (no penalty): gmail.com
Mean Sign Ups/Hour: 0. Standard Deviation: 0
Score: 1
Outcome: PASS',
        ), $detector->evaluate($userParams, $companyParams, $requestParams));
    }

    public function testIsFraudulentFailShortName(): void
    {
        $detector = $this->getDetector();
        $userParams = [];
        $companyParams = [
            'name' => 'xx',
            'email' => 'test@gmail.com',
            'country' => 'GB',
        ];
        $requestParams = [
            'ip' => '25.25.25.25',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/109.0',
            'accept_language' => 'en-US,en;q=0.5"',
        ];
        $this->assertEquals(new FraudScore(
            score: 1,
            determination: FraudOutcome::Pass,
            log: 'Calculating fraud score for new company sign up
Company parameters: {"name":"xx","email":"test@gmail.com","country":"GB"}
User parameters: {}
Request parameters: {"ip":"25.25.25.25","user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/109.0","accept_language":"en-US,en;q=0.5\""}
Suspiciously short company name "xx"
Detected personal email domain (no penalty): gmail.com
Mean Sign Ups/Hour: 0. Standard Deviation: 0
Score: 1
Outcome: PASS',
        ), $detector->evaluate($userParams, $companyParams, $requestParams));
    }

    public function testIpIsBlocked(): void
    {
        $database = self::getService('test.database');
        $this->assertFalse(IpUtilities::isBlocked('127.0.0.2', $database));
        $this->assertTrue(IpUtilities::isBlocked('127.0.0.123', $database));
    }
}
