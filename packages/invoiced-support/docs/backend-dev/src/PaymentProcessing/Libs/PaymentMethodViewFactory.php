<?php

namespace App\PaymentProcessing\Libs;

use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Gateways\OPPGateway;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Interfaces\PaymentInfoViewInterface;
use App\PaymentProcessing\Interfaces\PaymentViewInterface;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Views\Payment\AchPaymentView;
use App\PaymentProcessing\Views\Payment\AdyenCardPaymentView;
use App\PaymentProcessing\Views\Payment\AffirmPaymentView;
use App\PaymentProcessing\Views\Payment\CardPaymentView;
use App\PaymentProcessing\Views\Payment\FlywirePaymentView;
use App\PaymentProcessing\Views\Payment\GoCardlessPaymentView;
use App\PaymentProcessing\Views\Payment\KlarnaPaymentView;
use App\PaymentProcessing\Views\Payment\OPPAchPaymentView;
use App\PaymentProcessing\Views\Payment\OPPCardPaymentView;
use App\PaymentProcessing\Views\Payment\PaymentInstructionsPaymentView;
use App\PaymentProcessing\Views\Payment\PayPalPaymentView;
use App\PaymentProcessing\Views\Payment\StripeAchPaymentView;
use App\PaymentProcessing\Views\Payment\StripeCardPaymentView;
use App\PaymentProcessing\Views\PaymentInfo\AchPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\AdyenCardPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\CardPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\FlywirePaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\GoCardlessPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\OPPAchPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\OPPCardPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\PaymentInstructionsPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\StripeAchPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\StripeCardPaymentInfoView;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Payment form factory.
 */
class PaymentMethodViewFactory
{
    public function __construct(private ServiceLocator $viewLocator)
    {
    }

    /**
     * Gets the payment form view for a given payment method.
     */
    public function getPaymentView(PaymentMethod $method, ?string $gateway): PaymentViewInterface
    {
        if (FlywireGateway::ID == $gateway) {
            return $this->viewLocator->get(FlywirePaymentView::class);
        }

        switch ($method->id) {
            case PaymentMethod::AFFIRM:
                return $this->viewLocator->get(AffirmPaymentView::class);
            case PaymentMethod::KLARNA:
                return $this->viewLocator->get(KlarnaPaymentView::class);
            case PaymentMethod::ACH:
                if (OPPGateway::ID == $gateway) {
                    return $this->viewLocator->get(OPPAchPaymentView::class);
                }
                if (StripeGateway::ID == $gateway) {
                    return $this->viewLocator->get(StripeAchPaymentView::class);
                }

                return $this->viewLocator->get(AchPaymentView::class);

            case PaymentMethod::DIRECT_DEBIT:
                return $this->viewLocator->get(GoCardlessPaymentView::class);

            case PaymentMethod::CREDIT_CARD:
                if (OPPGateway::ID == $gateway) {
                    return $this->viewLocator->get(OPPCardPaymentView::class);
                }
                if (StripeGateway::ID == $gateway) {
                    return $this->viewLocator->get(StripeCardPaymentView::class);
                } elseif (AdyenGateway::ID == $gateway) {
                    return $this->viewLocator->get(AdyenCardPaymentView::class);
                }

                return $this->viewLocator->get(CardPaymentView::class);

            case PaymentMethod::PAYPAL:
                return $this->viewLocator->get(PayPalPaymentView::class);
        }

        return $this->viewLocator->get(PaymentInstructionsPaymentView::class);
    }

    /**
     * Gets the payment source form for a given payment method.
     */
    public function getPaymentInfoView(PaymentMethod $method, ?string $gateway): PaymentInfoViewInterface
    {
        if (FlywireGateway::ID == $gateway) {
            return $this->viewLocator->get(FlywirePaymentInfoView::class);
        }

        switch ($method->id) {
            case PaymentMethod::ACH:
                if (OPPGateway::ID == $gateway) {
                    return $this->viewLocator->get(OPPAchPaymentInfoView::class);
                }
                if (StripeGateway::ID == $gateway) {
                    return $this->viewLocator->get(StripeAchPaymentInfoView::class);
                }

                return $this->viewLocator->get(AchPaymentInfoView::class);

            case PaymentMethod::CREDIT_CARD:
                if (OPPGateway::ID == $gateway) {
                    return $this->viewLocator->get(OPPCardPaymentInfoView::class);
                }
                if (StripeGateway::ID == $gateway) {
                    return $this->viewLocator->get(StripeCardPaymentInfoView::class);
                } elseif (AdyenGateway::ID == $gateway) {
                    return $this->viewLocator->get(AdyenCardPaymentInfoView::class);
                }

                return $this->viewLocator->get(CardPaymentInfoView::class);

            case PaymentMethod::DIRECT_DEBIT:
                return $this->viewLocator->get(GoCardlessPaymentInfoView::class);

            default:
                return $this->viewLocator->get(PaymentInstructionsPaymentInfoView::class);
        }
    }
}
