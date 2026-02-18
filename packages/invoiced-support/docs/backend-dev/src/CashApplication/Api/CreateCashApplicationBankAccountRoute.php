<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Plaid\Libs\AddPlaidItem;
use App\Integrations\Plaid\Libs\PlaidApi;
use App\Integrations\Plaid\Models\PlaidItem;
use Carbon\CarbonImmutable;
use RuntimeException;
use stdClass;
use Throwable;

class CreateCashApplicationBankAccountRoute extends AbstractModelApiRoute
{
    public function __construct(
        private PlaidApi $plaidClient,
        private TenantContext $tenant,
        private AddPlaidItem $addPlaidBankAccount,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'token' => new RequestParameter(),
                'metadata' => new RequestParameter(),
                'start_date' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: CashApplicationBankAccount::class,
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $token = $context->requestParameters['token'];
        $metadata = (array) $context->requestParameters['metadata'];
        $startDate = new CarbonImmutable($context->requestParameters['start_date']);

        try {
            $result = $this->plaidClient->exchangePublicToken($this->tenant->get(), (string) $token);
        } catch (Throwable $e) {
            throw new InvalidRequest('There was a problem exchanging the public token: '.$e->getMessage());
        }

        $accountLinks = [];
        foreach ($metadata['accounts'] as $account) {
            try {
                $this->validateCashAccount($account, $metadata);
                $plaidLink = $this->addPlaidBankAccount->saveAccount($account, $metadata, $result);

                $bankAccount = new CashApplicationBankAccount();
                $bankAccount->plaid_link = $plaidLink;
                $bankAccount->data_starts_at = $startDate->getTimestamp();
                $bankAccount->saveOrFail();

                $accountLinks[] = $bankAccount;
            } catch (RuntimeException $e) {
                $error = new stdClass();
                $error->message = $e->getMessage();
                $accountLinks[] = $error;
            }
        }

        return $accountLinks;
    }

    private function validateCashAccount(array $account, array $metadata): bool
    {
        if ('checking' !== $account['subtype']) {
            throw new RuntimeException('Your '.$metadata['institution']['name'].' '.ucwords($account['subtype']).' account ending in \''.$account['mask'].'\' could not be connected. Only checking accounts can be connected.');
        }

        $existingLink = PlaidItem::withoutDeleted()
            ->join(CashApplicationBankAccount::class, 'PlaidBankAccountLinks.id', 'plaid_link_id')
            ->where('institution_id', $metadata['institution']['institution_id'])
            ->where('account_name', $account['name'])
            ->where('account_last4', $account['mask'])
            ->where('account_subtype', $account['subtype'])
            ->oneOrNull();

        if ($existingLink) {
            throw new RuntimeException('Your '.$metadata['institution']['name'].' '.ucwords($account['subtype']).' account ending in \''.$account['mask'].'\' could not be added because it is already connected.');
        }

        return true;
    }
}
