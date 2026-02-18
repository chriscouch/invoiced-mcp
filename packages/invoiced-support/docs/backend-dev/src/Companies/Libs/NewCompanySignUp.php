<?php

namespace App\Companies\Libs;

use App\Companies\Exception\NewCompanySignUpException;
use App\Companies\Models\Company;
use App\Companies\Models\MarketingAttribution;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Entitlements\Models\Product;
use App\Core\Orm\Exception\ModelException;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\RandomString;

class NewCompanySignUp implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private const MAX_RETRIES = 20;

    public function __construct(
        private UserContext $userContext,
        private string $environment,
        private CompanyEntitlementsManager $entitlementsManager,
    ) {
    }

    /**
     * Generates the entitlements for a new company created from
     * a sign up form. The entitlements vary based on the context
     * of the sign up and the environment.
     */
    public function getEntitlements(bool $isAccountsPayable, bool $fromInvitation): EntitlementsChangeset
    {
        if ('sandbox' == $this->environment) {
            // All sandbox accounts have these features.
            return new EntitlementsChangeset(
                products: [Product::where('name', 'Sandbox')->one()],
                features: [
                    'needs_onboarding' => true,
                ],
                quota: [
                    'aws_email_daily_limit' => 50,
                ],
            );
        }

        // An account created from an invitation gets the free A/R or A/P product with minimal capabilities.
        if ($fromInvitation) {
            if ($isAccountsPayable) {
                $products = [Product::where('name', 'Accounts Payable Free')->one()];
            } else {
                $products = [Product::where('name', 'Accounts Receivable Free')->one()];
            }

            return new EntitlementsChangeset(
                products: $products,
                features: [
                    'needs_onboarding' => true,
                ],
                quota: [
                    'users' => 3,
                    'aws_email_daily_limit' => 50,
                ],
            );
        }

        // If a sign up did not come from an invitation then it should
        // be configured as a free trial
        return new EntitlementsChangeset(
            products: [Product::where('name', 'Free Trial')->one()],
            features: [
                'needs_onboarding' => true,
                'not_activated' => true,
            ],
            quota: [
                'users' => 10,
                'transactions_per_day' => 20,
                'aws_email_daily_limit' => 100,
            ],
        );
    }

    /**
     * Generates a unique username for a given company name.
     * NOTE: This will return an empty string if a unique cannot
     * be found.
     */
    public function determineUsername(string $name, int $retries = 0): string
    {
        if ($retries >= self::MAX_RETRIES) {
            return '';
        }

        if (!$name) {
            $name = RandomString::generate(15, RandomString::CHAR_ALNUM);
        }

        if ($retries > 0) {
            // if the last iteration was not unique then tack on a random value
            // and retry up to a certain number of iterations
            $username = $name.rand(1, 1000);
        } else {
            // strip out any non-alphanumeric characters
            $username = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $name));
        }

        // see if it exists
        $n = Company::where('username', $username)->count();
        if ($n > 0) {
            $retryName = $retries > 0 ? $name : $username;

            return $this->determineUsername($retryName, $retries + 1);
        }

        return $username;
    }

    /**
     * Signs up a new company.
     *
     * @throws NewCompanySignUpException
     */
    public function create(array $parameters, EntitlementsChangeset $changeset, array $utm = []): Company
    {
        $parameters = $this->addParameters($parameters);
        $company = new Company();
        if (!$company->create($parameters)) {
            throw new NewCompanySignUpException($company->getErrors());
        }

        // perform any post-save actions
        // like creating other models or enabling features
        $this->postSave($company, $changeset, $utm);

        return $company;
    }

    private function addParameters(array $parameters): array
    {
        // auto-generate the username
        if (isset($parameters['name']) && !isset($parameters['username'])) {
            $parameters['username'] = $this->determineUsername($parameters['name']);
        }

        // assign the user that created this company
        if (!array_key_exists('creator_id', $parameters)) {
            $parameters['creator_id'] = $this->userContext->get()?->id();
        }

        return $parameters;
    }

    /**
     * @throws NewCompanySignUpException
     */
    private function postSave(Company $company, EntitlementsChangeset $changeset, array $utm): void
    {
        try {
            // save the entitlements
            $this->entitlementsManager->applyChangeset($company, $changeset);

            // create the billing profile
            $this->saveBillingProfile($company);

            // save the UTM parameters
            $this->addMarketingAttribution($company, $utm);
        } catch (ModelException $e) {
            throw new NewCompanySignUpException($e->getMessage());
        }
    }

    private function saveBillingProfile(Company $company): void
    {
        BillingProfile::getOrCreate($company);
    }

    /**
     * Saves marketing attribution (UTM) values.
     */
    private function addMarketingAttribution(Company $company, array $utm): void
    {
        $attribution = new MarketingAttribution();

        $shouldSave = false;
        foreach ($utm as $k => $v) {
            // do not attribute our own referrals
            if ('$initial_referring_domain' == $k && 'invoiced.com' == $v) {
                continue;
            }

            if ('$initial_referrer' == $k && str_starts_with($v, 'https://invoiced.com')) {
                continue;
            }

            $attribution->$k = $v;
            $shouldSave = true;
        }

        if ($shouldSave) {
            $attribution->tenant_id = (int) $company->id();
            $attribution->save();
        }

        // track new trials in statsd
        if ($company->trial_ends) {
            $this->statsd->increment('trial_funnel.new_trial');
        }
    }
}
