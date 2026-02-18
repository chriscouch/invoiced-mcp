<?php

namespace App\EntryPoint\Controller;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Multitenant\TenantContext;
use App\Network\Command\AcceptNetworkInvitation;
use App\Network\Command\DeclineNetworkInvitation;
use App\Network\Exception\NetworkInviteException;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkInvitation;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'network_', schemes: '%app.protocol%', host: '%app.domain%')]
class NetworkController extends AbstractController
{
    #[Route(path: '/network/{id}/accept', name: 'accept_invitation', methods: ['GET'])]
    public function acceptInvitation(string $id, AcceptNetworkInvitation $accept, UserContext $userContext, TenantContext $tenant): Response
    {
        // Load the invitation
        $invitation = $this->getInvitation($id);

        // If the invitation is not attached to a company then
        // ask user to sign in or else register
        if (!$invitation->to_company) {
            $user = $userContext->get();
            $tenants = [];
            if ($user?->isFullySignedIn()) {
                $companies = Company::where('name', '', '<>')
                    ->where("EXISTS (SELECT 1 FROM Members WHERE user_id='{$user->id()}' AND `tenant_id`=Companies.id AND (expires = 0 OR expires > ".time().') GROUP BY `tenant_id` HAVING COUNT(*) > 0)')
                    ->sort('name ASC')
                    ->all();
                foreach ($companies as $company) {
                    $tenants[] = [
                        'id' => $company->id(),
                        'name' => $company->nickname ?: $company->name,
                    ];
                }
            }

            return $this->render('network/invitation_choice.html.twig', [
                'fromName' => $invitation->from_company->name,
                'invitationId' => $invitation->uuid,
                'invitedAsCustomer' => $invitation->is_customer,
                'companies' => $tenants,
            ]);
        }

        // To proceed the tenant must be set
        $tenant->set($invitation->to_company);

        return $this->performAcceptAction($accept, $invitation);
    }

    #[Route(path: '/network/{id}/confirm', name: 'accept_invitation_for_company', methods: ['POST'])]
    public function confirmInvitation(string $id, Request $request, AcceptNetworkInvitation $accept, UserContext $userContext, TenantContext $tenant): Response
    {
        // Load the invitation
        $invitation = $this->getInvitation($id);

        // Load the company
        $company = Company::find($request->request->get('company'));
        if (!$company) {
            $this->addFlash('invitation_error', 'Please select a company from the list');

            return $this->redirectToRoute('network_accept_invitation', ['id' => $id]);
        }

        // To proceed the tenant must be set
        $tenant->set($company);

        // Load the member and validate permissions
        $member = Member::getForUser($userContext->getOrFail());
        if (!$member instanceof Member) {
            $this->addFlash('invitation_error', 'You are not a member of this company');

            return $this->redirectToRoute('network_accept_invitation', ['id' => $id]);
        }

        if (!$member->allowed('settings.edit')) {
            $this->addFlash('invitation_error', 'You are not allowed to approve new connections. The invitation must be approved by an administrator in your company.');

            return $this->redirectToRoute('network_accept_invitation', ['id' => $id]);
        }

        if ($company->id == $invitation->from_company->id) {
            $this->addFlash('invitation_error', 'You cannot join your own network');

            return $this->redirectToRoute('network_accept_invitation', ['id' => $id]);
        }

        // Accept the invitation
        $invitation->to_company = $company;
        $invitation->saveOrFail();

        return $this->performAcceptAction($accept, $invitation);
    }

    #[Route(path: '/network/{id}/decline', name: 'decline_invitation', methods: ['GET'])]
    public function declineInvitation(string $id, DeclineNetworkInvitation $decline): Response
    {
        // Load the invitation
        $invitation = $this->getInvitation($id);

        try {
            $decline->decline($invitation);
            $error = null;
        } catch (NetworkInviteException $e) {
            $error = $e->getMessage();
        }

        return $this->render('network/declined_invitation.html.twig', [
            'fromName' => $invitation->from_company->name,
            'invitedAsCustomer' => $invitation->is_customer,
            'error' => $error,
        ]);
    }

    #[Route(path: '/pay/{id}', name: 'pay_vendor', methods: ['GET'])]
    public function payVendor(string $id, UserContext $userContext, Request $request, TenantContext $tenant, Connection $connection): Response
    {
        // Get the user making the payment
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            $request->getSession()->set('redirect_after_login', $request->getUri());

            return $this->redirectToRoute('login_redirect');
        }

        // Get the company they are coming from
        $fromUsername = $request->get('from');
        if (!$fromUsername) {
            throw new NotFoundHttpException();
        }

        $payer = Company::where('username', $fromUsername)->oneOrNull();
        if (!$payer instanceof Company || !$payer->isMember($user)) {
            throw new NotFoundHttpException();
        }

        // Get the company they are paying
        $payee = Company::where('username', $id)->oneOrNull();
        if (!$payee instanceof Company) {
            throw new NotFoundHttpException();
        }
        $networkConnection = NetworkConnection::forCustomer($payee, $payer);
        if (!$networkConnection) {
            throw new NotFoundHttpException();
        }

        // Set the tenant context to the payee to be able to get customer and documents
        $tenant->set($payee);

        // Redirect to the customer portal select payment items page, as that customer
        $queryParams = [
            'Quote' => $request->query->all('Quote'),
            'Invoice' => $request->query->all('Invoice'),
            'CreditNote' => $request->query->all('CreditNote'),
        ];
        $queryParams = array_filter($queryParams);

        // Get the customer being paid
        $customers = Customer::where('network_connection_id', $networkConnection)->all()->toArray();
        if (1 == count($customers)) {
            $customer = $customers[0];
        } elseif (count($customers) > 1) {
            if (isset($queryParams['Invoice'])) {
                $qry = $connection->createQueryBuilder();
                $customerIds = $qry->select('customer')
                    ->from('Invoices')
                    ->andWhere('tenant_id', $payee->id)
                    ->andWhere($qry->expr()->in('customer', ':customers'))
                    ->andWhere($qry->expr()->in('number', ':numbers'))
                    ->setParameter('customers', array_map(fn ($customer) => (string) $customer->id, $customers), ArrayParameterType::INTEGER)
                    ->setParameter('numbers', $queryParams['Invoice'], Connection::PARAM_STR_ARRAY)
                    ->groupBy('customer')
                    ->fetchFirstColumn();
                if (1 === count($customerIds)) {
                    foreach ($customers as $customerCandidate) {
                        if ($customerCandidate->id === $customerIds[0]) {
                            $customer = $customerCandidate;
                            break;
                        }
                    }
                }
            }
            if (!isset($customer)) {
                // TODO: need to show selection screen
                $customer = $customers[0];
            }
        } else {
            throw new NotFoundHttpException();
        }

        return $this->redirectToRoute('customer_portal_payment_select_items_form', array_merge(
            $queryParams, [
            'subdomain' => $payee->getSubdomainUsername(),
            'id' => $customer->client_id,
        ]));
    }

    /**
     * @throws NotFoundHttpException
     */
    private function getInvitation(string $id): NetworkInvitation
    {
        $invitation = NetworkInvitation::where('uuid', $id)
            ->where('expires_at', CarbonImmutable::now()->toDateTimeString(), '>')
            ->oneOrNull();
        if (!$invitation instanceof NetworkInvitation) {
            throw new NotFoundHttpException();
        }

        return $invitation;
    }

    private function performAcceptAction(AcceptNetworkInvitation $accept, NetworkInvitation $invitation): Response
    {
        try {
            $accept->accept($invitation);
            $error = null;
        } catch (NetworkInviteException $e) {
            $error = $e->getMessage();
        }

        return $this->render('network/accepted_invitation.html.twig', [
            'fromName' => $invitation->from_company->name,
            'invitedAsCustomer' => $invitation->is_customer,
            'error' => $error,
        ]);
    }
}
