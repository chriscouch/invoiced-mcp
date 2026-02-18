<?php

namespace App\Companies\Libs;

use App\Companies\Models\Company;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Authentication\Libs\UserRegistration;
use App\Core\Authentication\Models\User;
use App\Core\Database\TransactionManager;
use App\Core\Mailer\Mailer;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * Allows our employees to create companies and users
 * on behalf of others. This will lookup/create a user
 * and then create a company for that user. The user
 * will receive an activation email with instructions to
 * claim their account.
 */
class CsadminCompanyCreator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TransactionManager $transactionManager,
        private NewCompanySignUp $newCompanySignUp,
        private string $dashboardUrl,
        private UserRegistration $userRegistration,
        private Mailer $mailer,
    ) {
    }

    /**
     * @throws Exception
     */
    public function create(array $companyParams, array $userParams): Company
    {
        [$company, $userWasCreated, $user] = $this->transactionManager->perform(function () use ($companyParams, $userParams) {
            try {
                return $this->_create($companyParams, $userParams);
            } catch (Throwable $e) {
                $this->logger->error('Could not complete sign up', ['exception' => $e]);

                throw new Exception($e->getMessage());
            }
        });

        // send a welcome email
        $this->sendWelcomeEmail($company, $userWasCreated, $user);

        return $company;
    }

    private function _create(array $companyParams, array $userParams): array
    {
        // first try to lookup the user
        $user = User::where('email', $userParams['email'])->oneOrNull();

        // create a temporary user if this is a new user
        // they will finish registration following the email we send
        $userWasCreated = false;
        if (!($user instanceof User)) {
            $userWasCreated = true;
            $user = $this->userRegistration->createTemporaryUser($userParams);
        }

        // capture the entitlements
        $changeset = EntitlementsChangeset::fromJson((object) $companyParams['changeset']);
        $changeset = $changeset->withFeatures(['needs_onboarding' => true]);
        unset($companyParams['changeset']);

        // create the company
        $companyParams['creator_id'] = $user->id();
        $company = $this->newCompanySignUp->create($companyParams, $changeset);

        return [$company, $userWasCreated, $user];
    }

    private function sendWelcomeEmail(Company $company, bool $userWasCreated, User $user): void
    {
        // existing users get a link to sign in
        $signInUrl = $this->dashboardUrl.'/?account='.$company->id();

        // new users get a link to complete registration / set password
        if ($userWasCreated) {
            $params = ['email' => $user->email];

            if ($firstName = $user->first_name) {
                $params['first_name'] = $firstName;
            }

            if ($lastName = $user->last_name) {
                $params['last_name'] = $lastName;
            }

            $query = http_build_query($params);
            $signInUrl = $this->dashboardUrl.'/#!/register'.(($query) ? '?'.$query : '');
        }

        $this->mailer->sendToUser($user, [
            'subject' => 'Activate your Invoiced account',
            ], 'new-purchase', [
            'username' => $user->name(true),
            'companyName' => $company->name,
            'signInUrl' => $signInUrl,
        ]);
    }
}
