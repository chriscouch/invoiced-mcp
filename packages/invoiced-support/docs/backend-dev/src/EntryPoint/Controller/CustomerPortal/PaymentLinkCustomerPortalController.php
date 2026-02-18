<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\Libs\PaymentLinkHelper;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkItem;
use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\CustomerPortal\Command\PaymentLinkProcessor;
use App\CustomerPortal\Command\PaymentLinks\PaymentLinkInvoiceHandler;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\CustomerPortal\ViewVariables\PaymentLinkViewVariables;
use App\PaymentProcessing\Models\Charge;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class PaymentLinkCustomerPortalController extends AbstractCustomerPortalController
{
    #[Route(path: '/pay/{id}', name: 'payment_link', methods: ['GET'])]
    public function paymentLink(Request $request, PaymentLinkViewVariables $viewVariables, TranslatorInterface $translator, PaymentLinkInvoiceHandler $invoiceHandler, string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        // Compute the hash of the query parameters
        $variables = $request->query->all();
        $hash = '';
        if ($request->query->has('client_identifier') || $request->query->has('clientId')) {
            ksort($variables);
            $hash = hash('sha256', (string) json_encode($variables));
        }

        try {
            $paymentLink = PaymentLinkHelper::getPaymentLink($translator, $id, $hash);
        } catch (PaymentLinkException $e) {
            return $this->render('customerPortal/paymentLinks/message.twig', [
                'message' => $e->getMessage(),
            ]);
        }

        $items = PaymentLinkItem::where('payment_link_id', $paymentLink->id)->all();
        if ($items->count()) {
            $amount = Money::zero($paymentLink->currency);
            foreach ($items as $item) {
                $amount = $amount->add(Money::fromDecimal($paymentLink->currency, $item->amount ?? 0));
            }
        } else {
            $amount = Money::fromDecimal($request->query->getString('currency', $paymentLink->currency), (float) $request->query->get('amount'));
        }

        $errors = $request->query->all('errors');
        $request->query->remove('errors');

        $defaultLineItemName = $invoiceHandler->getDefaultLineItemName($request->query->all());
        $variables = $viewVariables->build($portal, $paymentLink, $defaultLineItemName, $amount, $variables, $errors);
        $variables['hash'] = $hash;

        // Show payment form
        return $this->render('customerPortal/paymentLinks/paymentLink.twig', $variables);
    }

    #[Route(path: '/payment_links/{id}/thanks', name: 'payment_link_thanks', requirements: ['id' => '[0-9a-zA-Z]+'], methods: ['GET'])]
    public function paymentLinkThanks(Request $request, string $id): Response
    {
        // look up payment link
        $paymentLink = PaymentLink::findClientId($id);
        if (!$paymentLink) {
            throw new NotFoundHttpException();
        }

        // look for a payment
        $pending = false;
        $amount = null;
        $receiptUrl = null;
        if ($request->query->has('payment')) {
            $payment = Payment::findClientId($request->query->get('payment'));

            if ($payment) {
                $pending = null == $payment->charge || Charge::PENDING == $payment->charge->status;
                $amount = $payment->getAmount();
                $receiptUrl = $payment->pdf_url.'?locale='.$request->getLocale();
            }
        }

        return $this->render('customerPortal/paymentLinks/thanks.twig', [
            'pending' => $pending,
            'currency' => $amount?->currency,
            'amount' => $amount?->toDecimal(),
            'receiptUrl' => $receiptUrl,
            'googleAnalyticsEvents' => [['category' => 'Customer Portal', 'action' => 'Completed Payment Link', 'label' => $id]],
        ]);
    }

    #[Route(path: '/api/payment_links/{id}', name: 'payment_link_api', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function paymentLinkApi(Request $request, TranslatorInterface $translator, PaymentLinkProcessor $processor, SignInCustomer $signIn, string $id): Response
    {
        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_payment_link', $request->request->getString('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        // obtain the payment link
        try {
            $hash = $request->request->getString('hash');
            $paymentLink = PaymentLinkHelper::getPaymentLink($translator, $id, $hash);
        } catch (PaymentLinkException $e) {
            return $this->render('customerPortal/paymentLinks/message.twig', [
                'message' => $e->getMessage(),
            ]);
        }

        // process the form submission and return the result
        try {
            $input = $request->request->all();
            $result = $processor->handleSubmit($paymentLink, $input);
        } catch (PaymentLinkException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        }

        $response = new JsonResponse([
            'url' => $result->getRedirectUrl(),
        ]);

        // Sign in the customer that completed the payment link
        return $signIn->signIn($result->getCustomer(), $response, true);
    }
}
