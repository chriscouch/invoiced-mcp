<?php

namespace App\Service;

use App\Entity\CustomerAdmin\NewAccount;
use App\Event\CompanyCreatedEvent;
use Exception;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NewCompanyCreator
{
    private CsAdminApiClient $client;
    private EventDispatcherInterface $dispatcher;

    public function __construct(CsAdminApiClient $client, EventDispatcherInterface $dispatcher)
    {
        $this->client = $client;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Creates a new company through the backend.
     *
     * @throws Exception
     */
    public function create(NewAccount $newAccount): object
    {
        $response = $this->client->request('/_csadmin/new_company', [
            'billing_profile_id' => $newAccount->getBillingProfileId(),
            'email' => $newAccount->getEmail(),
            'country' => $newAccount->getCountry(),
            'first_name' => $newAccount->getFirstName(),
            'last_name' => $newAccount->getLastName(),
            'changeset' => $newAccount->getChangesetObject(),
        ]);

        if (isset($response->error)) {
            throw new Exception('Creating account failed: '.$response->error);
        }

        $this->dispatcher->dispatch(new CompanyCreatedEvent($newAccount, $response->id));

        return $response;
    }
}
