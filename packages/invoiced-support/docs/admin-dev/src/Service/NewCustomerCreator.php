<?php

namespace App\Service;

use App\Entity\Forms\NewCustomer;
use App\Entity\Invoiced\BillingProfile;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Exception;

class NewCustomerCreator
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private CsAdminApiClient $csAdminApiClient
    ) {
    }

    public function create(NewCustomer $newCustomer): BillingProfile
    {
        // Look for an existing billing profile
        /** @var ObjectManager $em */
        $em = $this->doctrine->getManagerForClass(BillingProfile::class);
        $repository = $em->getRepository(BillingProfile::class);
        $billingProfile = $repository->findOneBy([
            'billing_system' => 'invoiced',
            'name' => $newCustomer->getName(),
        ]);
        if ($billingProfile instanceof BillingProfile) {
            return $billingProfile;
        }

        // Create a new billing profile
        $response = $this->csAdminApiClient->request(
            '/_csadmin/new_customer',
            [
                'company' => $newCustomer->getName(),
                'email' => $newCustomer->getBillingEmail(),
                'phone' => $newCustomer->getBillingPhone(),
                'address1' => $newCustomer->getAddress1(),
                'address2' => $newCustomer->getAddress2(),
                'city' => $newCustomer->getCity(),
                'state' => $newCustomer->getState(),
                'postal_code' => $newCustomer->getPostalCode(),
                'country' => $newCustomer->getCountry(),
                'sales_rep' => $newCustomer->getSalesRep(),
            ]
        );

        if (isset($response->error)) {
            throw new Exception('Creating customer failed: '.$response->error);
        }

        return $repository->find($response->id); /* @phpstan-ignore-line */
    }
}
