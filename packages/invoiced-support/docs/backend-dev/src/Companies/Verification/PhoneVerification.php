<?php

namespace App\Companies\Verification;

use App\Companies\Enums\PhoneVerificationChannel;
use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyPhoneNumber;
use Carbon\CarbonImmutable;
use DateInterval;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Verifies the company phone number.
 */
class PhoneVerification implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private CacheStorage $storage,
        private LockFactory $lockFactory,
        private Client $twilio,
        private string $verifyServiceId,
        private Connection $database,
    ) {
    }

    /**
     * Starts a new phone number verification request. This starts a new
     * phone number verification on Twilio.
     *
     * @throws BusinessVerificationException
     */
    public function start(Company $company, string $countryCode, string $phone, PhoneVerificationChannel $channel): CompanyPhoneNumber
    {
        // Sanitize the phone to only numeric
        $phone = (string) preg_replace('/[^0-9]+/i', '', $phone);
        $phone = '+'.$countryCode.$phone;

        // Phone number must be E.164 format
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            throw new BusinessVerificationException('Invalid phone number');
        }

        // Check for an existing verification attempt
        /** @var CompanyPhoneNumber|null $companyPhone */
        $companyPhone = CompanyPhoneNumber::queryWithTenant($company)
            ->where('phone', $phone)
            ->oneOrNull();

        // If previously verified then don't ask again
        if ($companyPhone?->verified_at) {
            return $companyPhone;
        }

        // generate a new verification token if needed
        if (!$companyPhone) {
            $companyPhone = new CompanyPhoneNumber();
            $companyPhone->tenant_id = $company->id;
            $companyPhone->phone = $phone;
        }

        $companyPhone->channel = $channel;
        $companyPhone->saveOrFail();

        $this->createVerification($company, $companyPhone);

        return $companyPhone;
    }

    /**
     * Completes the phone verification process.
     *
     * @throws BusinessVerificationException
     */
    public function complete(CompanyPhoneNumber $companyPhone, string $code): void
    {
        $this->createVerificationCheck($companyPhone, $code);

        $companyPhone->verified_at = CarbonImmutable::now();
        $companyPhone->saveOrFail();
    }

    /**
     * Creates a Twilio verification.
     *
     * @throws BusinessVerificationException
     */
    public function createVerification(Company $company, CompanyPhoneNumber $companyPhone): void
    {
        if ($company->fraud) {
            return;
        }

        // Check the phone number against the block list
        $count = $this->database->fetchOne('SELECT COUNT(*) FROM BlockListPhoneNumbers WHERE phone=?', [$companyPhone->phone]);
        if ($count > 0) {
            return;
        }

        // The ID has to include the limit because if the limit
        // changes then it will still use the previous limit.
        $limit = 5;
        $id = $company->id.'-'.$limit;
        $interval = new DateInterval('P1D');
        $lock = $this->lockFactory->createLock("phone_verification_limiter-$id-$limit");
        $limiter = new SlidingWindowLimiter($id, 5, $interval, $this->storage, $lock);

        if (!$limiter->consume()->isAccepted()) {
            throw new BusinessVerificationException('Too many verifications have been attempted. Please try again later.');
        }

        $channel = match ($companyPhone->channel) {
            PhoneVerificationChannel::Sms => 'sms',
            PhoneVerificationChannel::Call => 'call',
        };

        try {
            $this->twilio->verify->v2->services($this->verifyServiceId)
                ->verifications->create($companyPhone->phone, $channel);
        } catch (TwilioException $e) {
            $this->logger->error('Could not create Twilio verification', ['exception' => $e]);

            throw new BusinessVerificationException('We were unable to send a verification code to your phone number.');
        }
    }

    /**
     * Creates a Twilio verification check.
     *
     * @throws BusinessVerificationException
     */
    public function createVerificationCheck(CompanyPhoneNumber $companyPhone, string $code): void
    {
        try {
            $check = $this->twilio->verify->v2->services($this->verifyServiceId)
                ->verificationChecks->create([
                    'code' => $code,
                    'to' => $companyPhone->phone,
                ]);

            if ('approved' != $check->status) {
                throw new BusinessVerificationException('We were unable to verify the supplied code. Please try again.');
            }
        } catch (TwilioException $e) {
            $this->logger->error('Could not create Twilio verification check', ['exception' => $e]);

            throw new BusinessVerificationException('We were unable to verify the supplied code');
        }
    }
}
