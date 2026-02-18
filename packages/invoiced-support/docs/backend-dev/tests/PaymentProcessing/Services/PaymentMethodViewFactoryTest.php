<?php

namespace App\Tests\PaymentProcessing\Services;

use App\Companies\Models\Company;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Views\Payment\AchPaymentView;
use App\PaymentProcessing\Views\Payment\CardPaymentView;
use App\PaymentProcessing\Views\Payment\PaymentInstructionsPaymentView;
use App\PaymentProcessing\Views\Payment\PayPalPaymentView;
use App\PaymentProcessing\Views\Payment\StripeAchPaymentView;
use App\PaymentProcessing\Views\PaymentInfo\GoCardlessPaymentInfoView;
use App\PaymentProcessing\Views\PaymentInfo\StripeCardPaymentInfoView;
use App\Tests\AppTestCase;

class PaymentMethodViewFactoryTest extends AppTestCase
{
    public function testGetPaymentViewCreditCard(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);

        $form = self::getService('test.payment_method_view_factory')->getPaymentView($method, null);
        $this->assertInstanceOf(CardPaymentView::class, $form);
    }

    public function testGetPaymentViewAch(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::ACH);

        $form = self::getService('test.payment_method_view_factory')->getPaymentView($method, MockGateway::ID);
        $this->assertInstanceOf(AchPaymentView::class, $form);
    }

    public function testGetPaymentViewAchStripe(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::ACH);

        $form = self::getService('test.payment_method_view_factory')->getPaymentView($method, StripeGateway::ID);
        $this->assertInstanceOf(StripeAchPaymentView::class, $form);
    }

    public function testGetPaymentViewCheck(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CHECK);

        $form = self::getService('test.payment_method_view_factory')->getPaymentView($method, null);
        $this->assertInstanceOf(PaymentInstructionsPaymentView::class, $form);
    }

    public function testGetPaymentViewCash(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CASH);

        $form = self::getService('test.payment_method_view_factory')->getPaymentView($method, null);
        $this->assertInstanceOf(PaymentInstructionsPaymentView::class, $form);
    }

    public function testGetPaymentViewPayPal(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::PAYPAL);

        $form = self::getService('test.payment_method_view_factory')->getPaymentView($method, null);
        $this->assertInstanceOf(PayPalPaymentView::class, $form);
    }

    public function testGetPaymentInfoFormCreditCardStripe(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);

        $form = self::getService('test.payment_method_view_factory')->getPaymentInfoView($method, StripeGateway::ID);
        $this->assertInstanceOf(StripeCardPaymentInfoView::class, $form);
    }

    public function testGetPaymentInfoFormDirectDebitGoCardless(): void
    {
        $company = new Company(['id' => -1]);
        $method = PaymentMethod::instance($company, PaymentMethod::DIRECT_DEBIT);

        $form = self::getService('test.payment_method_view_factory')->getPaymentInfoView($method, GoCardlessGateway::ID);
        $this->assertInstanceOf(GoCardlessPaymentInfoView::class, $form);
    }
}
