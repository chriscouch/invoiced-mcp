<?php

namespace App\PaymentProcessing\Views\Payment;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Integrations\Libs\IpnContext;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Traits\PaymentFlowUrlTrait;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Renders a PayPal Express Checkout payment form.
 */
class PayPalPaymentView extends AbstractPaymentView
{
    use PaymentFlowUrlTrait;

    private const PAYPAL_URL = 'https://www.paypal.com/cgi-bin/webscr';
    private const PAYPAL_URL_SANDBOX = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

    public function __construct(
        Environment $twig,
        TranslatorInterface $translator,
        private string $environment,
        private IpnContext $payPalIpnContext,
        private UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($twig, $translator);
    }

    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool
    {
        return true;
    }

    public function getPaymentFormCapabilities(): PaymentFormCapabilities
    {
        return new PaymentFormCapabilities(
            isSubmittable: false,
            supportsVaulting: false,
            supportsConvenienceFee: false,
            hasReceiptEmail: false
        );
    }

    public function getTemplate(): string
    {
        return 'customerPortal/paymentMethods/paymentForms/paypal.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        $company = $form->company;

        if ('production' == $this->environment) {
            $paypalUrl = self::PAYPAL_URL;
            $paypalEnv = 'www';
        } else {
            $paypalUrl = self::PAYPAL_URL_SANDBOX;
            $paypalEnv = 'www.sandbox';
        }

        // encrypt company id
        $cid = $this->payPalIpnContext->encode((int) $company->id());

        $returnUrl = $this->getPaymentFlowCanceledUrl($company, $paymentFlow);
        $thanksUrl = $this->getPaymentFlowCompletedUrl($company, $paymentFlow);

        return [
            'paypalUrl' => $paypalUrl,
            'paypalEnv' => $paypalEnv,
            'ipnUrl' => $this->getIpnUrl(),
            'cancelReturnUrl' => $returnUrl,
            'shoppingUrl' => $thanksUrl,
            'returnUrl' => $thanksUrl,
            'paypalEmail' => $paymentMethod->meta,
            'companyIdentifier' => $cid,
            'paypalInvoice' => $this->getPayPalInvoiceNumber($form),
            'currency' => $form->currency,
            'description' => $this->getPayPalDescription($form),
            'paymentMethod' => $paymentMethod,
        ];
    }

    private function getIpnUrl(): string
    {
        return $this->urlGenerator->generate('paypal_ipn', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getPayPalInvoiceNumber(PaymentForm $form): string
    {
        return $this->serialize($form);
    }

    public function getPayPalDescription(PaymentForm $form): string
    {
        return $form->getPaymentDescription($this->translator);
    }

    protected function serialize(PaymentForm $form): string
    {
        // the timestamp will allow for
        // multiple payments on PayPal without triggering
        // a duplicate payment error on PayPal
        $pieces = ['s'.time()];
        foreach ($form->paymentItems as $item) {
            $itemStr = '';
            if ($item->document) {
                $prefix = match (get_class($item->document)) {
                    Invoice::class => 'i',
                    Estimate::class => 'e',
                    CreditNote::class => 'c',
                    default => '',
                };
                $itemStr .= $prefix.$item->document->id();
            } else {
                $itemStr .= 'a'.$form->customer->id();
            }

            $itemStr .= '|'.$item->amount->amount;
            $pieces[] = $itemStr;
        }

        return implode('', $pieces);
    }
}
