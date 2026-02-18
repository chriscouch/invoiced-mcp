<?php

namespace App\Core\Utils;

use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Libs\ApiKeyAuth;
use App\CustomerPortal\Libs\CustomerPortalContext;
use Monolog\Processor\ProcessorInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class SentryProcessor implements ProcessorInterface
{
    public function __construct(private HubInterface $hub, private TenantContext $tenant, private ApiKeyAuth $apiKeyAuth, private CustomerPortalContext $customerPortal, private UserContext $userContext, private DebugContext $debugContext)
    {
    }

    public function __invoke(array $record): array
    {
        $this->hub->configureScope(function (Scope $scope) use ($record): void {
            if ($this->userContext->has() && $user = $this->userContext->get()) {
                $ip = array_value($record, 'extra.ip');
                if (User::API_USER == $user->id()) {
                    $apiKey = $this->apiKeyAuth->getCurrentApiKey();
                    $username = 'API';
                    if ($apiKey && $description = $apiKey->description) {
                        $username .= " ($description)";
                    }
                    if ($apiKey && $source = $apiKey->source) {
                        $username .= " ($source)";
                    }
                    $scope->setUser([
                        'id' => $apiKey ? $apiKey->id() : $user->id(),
                        'username' => $username,
                        'email' => $apiKey ? $apiKey->tenant()->email : null,
                        'ip_address' => $ip,
                    ]);
                } elseif (User::INVOICED_USER == $user->id()) {
                    $apiKey = $this->apiKeyAuth->getCurrentApiKey();
                    $scope->setUser([
                        'id' => $apiKey ? $apiKey->id() : $user->id(),
                        'username' => 'Invoiced'.($apiKey ? " ({$apiKey->source})" : ''),
                        'email' => 'support@invoiced.com',
                        'ip_address' => $ip,
                    ]);
                } else {
                    $scope->setUser([
                        'id' => $user->id(),
                        'usename' => $user->name(true),
                        'email' => $user->email,
                        'ip_address' => $ip,
                    ]);
                }
            }

            if ($this->tenant->has()) {
                $company = $this->tenant->get();
                $scope->setTag('tenantId', (string) $company->id());
                if ($username = $company->username) {
                    $scope->setTag('company', $username);
                }
            }

            $portal = $this->customerPortal->get();
            if ($portal && $client = $portal->getSignedInCustomer()) {
                $scope->setTag('customerId', (string) $client->id());
            }

            $scope->setTag('sapi', (string) php_sapi_name());

            if ($requestId = $this->debugContext->getRequestId()) {
                $scope->setTag('requestId', $requestId);
            }

            $scope->setTag('correlationId', $this->debugContext->getCorrelationId());

            foreach ($record['context'] as $key => $value) {
                if ('exception' == $key) {
                    continue;
                }

                if (in_array($key, ['queue'])) {
                    $scope->setTag($key, $value);
                } else {
                    $scope->setExtra($key, $value);
                }
            }
        });

        // We don't do anything to the monolog record
        // because the data is modified on the sentry scope.
        return $record;
    }
}
