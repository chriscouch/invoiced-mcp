<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\CustomerPortal\Command\SignInCustomer;
use App\Notifications\Enums\NotificationEventType;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\CancelSubscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Throwable;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class SubscriptionCustomerPortalController extends AbstractCustomerPortalController
{
    #[Route(path: '/subscriptions/{id}', name: 'view_subscription', methods: ['GET'])]
    public function subscriptionClientView(Request $request, SignInCustomer $signIn, string $id): Response
    {
        $subscription = Subscription::findClientId($id);
        if (!$subscription) {
            throw new NotFoundHttpException();
        }

        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        $customer = $subscription->customer();
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        // send them to the account page
        $response = new RedirectResponse(
            $this->generatePortalUrl(
                $portal,
                'customer_portal_account',
                $request->query->all()
            )
        );

        // sign the customer temporarily into the customer portal
        $signIn->signIn($customer, $response, true);

        return $response;
    }

    #[Route(path: '/subscriptions/{id}/cancel', name: 'cancel_subscription', methods: ['POST'])]
    public function cancelSubscription(Request $request, CancelSubscription $cancelSubscription, Connection $database, string $id): Response
    {
        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_my_account', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $subscription = Subscription::findClientId($id);

        // cannot cancel fixed duration subscriptions
        if (!$subscription || in_array($subscription->status, [SubscriptionStatus::FINISHED, SubscriptionStatus::CANCELED]) || ($subscription->cycles > 0 && Subscription::RENEWAL_MODE_AUTO != $subscription->contract_renewal_mode)) {
            throw new NotFoundHttpException();
        }

        try {
            $cancelSubscription->cancel($subscription, 'customer_portal', NotificationEventType::SubscriptionCanceled);
        } catch (Throwable) {
            $database->setRollbackOnly();

            return new Response('Unable to cancel subscription.');
        }

        return $this->render('customerPortal/subscriptions/canceled.twig');
    }
}
