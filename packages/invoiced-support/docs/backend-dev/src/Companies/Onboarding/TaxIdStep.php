<?php

namespace App\Companies\Onboarding;

use App\Companies\Enums\TaxIdType;
use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Exception\OnboardingException;
use App\Companies\Interfaces\OnboardingStepInterface;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyTaxId;
use App\Companies\Verification\UsTaxIdVerification;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;
use Symfony\Component\HttpFoundation\Request;

class TaxIdStep implements OnboardingStepInterface
{
    const FAKE_TAX_ID = '123456789';

    public function __construct(
        private readonly UsTaxIdVerification $usTaxIdVerification,
        private readonly string $environment,
    ) {
    }

    public function mustPerform(Company $company): bool
    {
        // Tax ID verification is currently disabled in all environments
        // Only live U.S. companies using A/R should perform this step
//        if ('US' == $company->country && !$company->test_mode && $company->features->has('accounts_receivable')) {
//            return 0 == CompanyTaxId::queryWithTenant($company)
//                ->where('name', $company->name)
//                ->where('country', 'US')
//                ->where('verified_at', null, '<>')
//                ->count();
//        }

        return false;
    }

    public function canRevisit(Company $company): bool
    {
        return $this->mustPerform($company);
    }

    public function handleSubmit(Company $company, Request $request): void
    {
        try {
            // Verify the tax ID
            $taxId = (string) $request->request->get('ein');
            if ($taxId) {
                $isEin = true;
            } else {
                $taxId = (string) $request->request->get('ssn');
                $isEin = false;
            }

            if (!$taxId) {
                throw new OnboardingException('Missing tax ID');
            }

            if (in_array($this->environment, ['test', 'dev', 'staging']) && self::FAKE_TAX_ID === str_replace('-', '', $taxId)) {
                $this->createCompanyTaxRecord($company, $taxId, null, $isEin);

                return;
            }

            $irsCode = $this->usTaxIdVerification->verify($company->id, $company->name, $taxId, $isEin);

            $this->createCompanyTaxRecord($company, $taxId, $irsCode, $isEin);
        } catch (BusinessVerificationException|ModelException $e) {
            throw new OnboardingException($e->getMessage(), 'tax_id');
        }
    }

    /**
     * @throws ModelException
     */
    private function createCompanyTaxRecord(Company $company, string $taxId, ?int $irsCode, bool $isEin): void
    {
        // Create the tax ID record
        $companyTaxId = new CompanyTaxId();
        $companyTaxId->name = $company->name;
        $companyTaxId->tax_id = $taxId;
        $companyTaxId->country = 'US';
        $companyTaxId->irs_code = $irsCode;
        $companyTaxId->tax_id_type = $isEin ? TaxIdType::EIN : TaxIdType::SSN;
        $companyTaxId->verified_at = CarbonImmutable::now();
        $companyTaxId->saveOrFail();
    }
}
