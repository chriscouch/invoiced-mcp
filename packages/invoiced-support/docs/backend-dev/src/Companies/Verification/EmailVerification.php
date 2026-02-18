<?php

namespace App\Companies\Verification;

use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Core\Mailer\Mailer;
use App\Core\Utils\AppUrl;
use App\Core\Utils\RandomString;
use Carbon\CarbonImmutable;
use Symfony\Component\Lock\LockFactory;

/**
 * Verifies the company email address.
 */
class EmailVerification
{
    public function __construct(
        private LockFactory $lockFactory,
        private Mailer $mailer,
    ) {
    }

    /**
     * Starts a new email verification request. This generates a new
     * email verification link and then sends it to the company.
     *
     * @throws BusinessVerificationException
     */
    public function start(Company $company): CompanyEmailAddress
    {
        if (!$company->email) {
            throw new BusinessVerificationException('Cannot send verification email without an email address');
        }

        // Check for an existing verification attempt
        /** @var CompanyEmailAddress|null $companyEmail */
        $companyEmail = CompanyEmailAddress::queryWithTenant($company)
            ->where('email', $company->email)
            ->oneOrNull();

        // If previously verified then don't ask again
        if ($companyEmail?->verified_at) {
            return $companyEmail;
        }

        // generate a new verification token if needed
        if (!$companyEmail) {
            $companyEmail = new CompanyEmailAddress();
            $companyEmail->tenant_id = $company->id;
            $companyEmail->email = $company->email;
            $companyEmail->token = RandomString::generate(24, RandomString::CHAR_ALNUM);
            $companyEmail->saveOrFail();
        }

        $this->sendVerificationEmail($company, $companyEmail);

        return $companyEmail;
    }

    /**
     * Sends an email with the verification link.
     *
     * @throws BusinessVerificationException
     */
    public function sendVerificationEmail(Company $company, CompanyEmailAddress $companyEmail): void
    {
        if ($company->fraud) {
            return;
        }

        if ($companyEmail->verified_at) {
            throw new BusinessVerificationException('Your email address is already verified.');
        }

        // prevent duplicate sends (can resend every 10 minutes)
        $k = 'verify_email.'.$company->id.'.'.str_replace('@', '_', $companyEmail->email);
        $lock = $this->lockFactory->createLock($k, 600, false);

        if (!$lock->acquire()) {
            return;
        }

        $verifyLink = $company->name ? AppUrl::get()->build().'/verifyEmail/'.$companyEmail->token : null;

        // and send it
        $this->mailer->send(
            [
                'to' => [
                    [
                        'email' => $companyEmail->email,
                        'name' => $company->name,
                    ],
                ],
                'subject' => 'Please verify your email address',
            ],
            'verify-company-email',
            [
                'verifyLink' => $verifyLink,
                'code' => $companyEmail->code,
            ]
        );
    }

    /**
     * Completes the email verification process if the company email
     * has been successfully verified.
     */
    public function complete(CompanyEmailAddress $companyEmail): void
    {
        $companyEmail->verified_at = CarbonImmutable::now();
        $companyEmail->saveOrFail();
    }
}
