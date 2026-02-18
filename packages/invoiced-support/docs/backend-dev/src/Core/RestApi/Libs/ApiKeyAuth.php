<?php

namespace App\Core\RestApi\Libs;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\User;
use App\Core\Billing\Enums\BillingSubscriptionStatus;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Exception\ModelException;
use App\Core\RestApi\Exception\ApiHttpException;
use App\Core\RestApi\Models\ApiKey;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

class ApiKeyAuth
{
    private ?ApiKey $apiKey = null;

    public function __construct(
        private Connection $database,
        private UserContext $userContext,
        private TenantContext $tenant,
    ) {
    }

    /**
     * Gets a company given a username.
     */
    public function getOrgFromUsername(string $username): ?Company
    {
        $id = $this->database->createQueryBuilder()
            ->select('id')
            ->from('Companies')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->fetchOne();

        if ($id) {
            return Company::find($id);
        }

        return null;
    }

    /**
     * Authenticates an API request.
     *
     * @throws ApiHttpException when the request cannot be authenticated
     */
    public function handleRequest(Request $request): void
    {
        $company = null;
        $username = $request->getUser();
        $password = $request->getPassword();

        // validate the company's username (if given)
        if ($username && !empty($password)) {
            $company = $this->getOrgFromUsername($username);

            if (!$company) {
                throw new ApiHttpException(401, 'We did not find an account matching the username: '.$username);
            }
            // the api key was supplied as the username
        } else {
            $password = $username;
        }

        if (!$password) {
            throw new ApiHttpException(401, 'Missing API key! HINT: You can pass in your API key as the username parameter using HTTP Basic Auth.');
        }

        // validate api key
        $key = ApiKey::getFromSecret($password);
        if (!$key) {
            throw new ApiHttpException(401, 'We could not authenticate you with the API Key: '.$this->getScrubbedKey($password));
        }

        // validate the organization on the key
        $company = $company ?: $key->tenant();
        if ($company->id() != $key->tenant_id) {
            throw new ApiHttpException(401, 'We could not authenticate you with the API Key: '.$this->getScrubbedKey($password));
        }

        // check if the company has an active subscription
        ACLModelRequester::set(new User(['id' => -1])); // set a temporary requester in case of modifying the billing profile
        $subscriptionStatus = $company->billingStatus();
        if (BillingSubscriptionStatus::Canceled == $subscriptionStatus) {
            throw new ApiHttpException(402, 'This account has been canceled. Please contact us at support@invoiced.com or visit https://app.invoiced.com to reactivate it.');
        }

        if (BillingSubscriptionStatus::Unpaid == $subscriptionStatus) {
            throw new ApiHttpException(402, 'Your trial has ended. Please contact us at support@invoiced.com or visit https://app.invoiced.com to subscribe.');
        }

        // set the org as the requester
        $this->tenant->set($company);
        ACLModelRequester::set($company);

        // use the company time zone for any datetime functions to be localized
        $company->useTimezone();

        // determine who the requester is of the api call
        if ($user = $key->user()) {
            $this->userContext->set($user);

            // set the member as the requester
            $member = Member::getForUser($user);
            if (!$member) {
                throw new ApiHttpException(401, 'You are no longer a member of this account');
            }

            ACLModelRequester::set($member);
        } else {
            if ($key->protected) {
                $this->userContext->set(new User(['id' => User::INVOICED_USER]));
            } else {
                $this->userContext->set(new User(['id' => User::API_USER]));
            }

            // set the org as the requester
            ACLModelRequester::set($company);
        }

        // make the API key globally available
        $this->apiKey = $key;
    }

    /**
     * Gets the current API key.
     */
    public function getCurrentApiKey(): ?ApiKey
    {
        return $this->apiKey;
    }

    /**
     * Updates the key last used and expiration timestamps as needed.
     *
     * @throws ModelException
     */
    public function updateKeyUsage(ApiKey $key): void
    {
        // update the last used timestamp on the key every 30 minutes
        if ($key->last_used < time() - 1800) {
            $key->last_used = time();
        }

        // refresh the expiry time of the key (if used)
        $expires = $key->expires;
        if ($expires > time() && $expires < strtotime('+20 minutes')) {
            // remember me sessions allow a longer expiration date
            // on the API key
            if ($key->remember_me) {
                $key->expires = strtotime('+3 days');
            } else {
                $key->expires = strtotime('+30 minutes');
            }
        }

        $key->saveOrFail();
    }

    private function getScrubbedKey(string $input): string
    {
        $scrubbedKey = $input;
        if (strlen($scrubbedKey) > 6) {
            $scrubbedKey = substr($input, 0, 3);
            $scrubbedKey .= str_repeat('*', strlen($input) - 6);
            $scrubbedKey .= substr($input, -3);
        }

        return $scrubbedKey;
    }
}
