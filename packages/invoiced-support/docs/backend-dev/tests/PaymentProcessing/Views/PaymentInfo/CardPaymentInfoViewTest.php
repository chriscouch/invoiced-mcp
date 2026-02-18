<?php

namespace App\Tests\PaymentProcessing\Views\PaymentInfo;

use App\Core\Utils\RandomString;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\TokenizationFlowStatus;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\PaymentProcessing\Views\PaymentInfo\CardPaymentInfoView;
use App\Tests\AppTestCase;

class CardPaymentInfoViewTest extends AppTestCase
{
    private static TokenizationFlow $tokenizationFlow;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::acceptsCreditCards('authorizenet');

        self::$tokenizationFlow = new TokenizationFlow();
        self::$tokenizationFlow->identifier = RandomString::generate();
        self::$tokenizationFlow->status = TokenizationFlowStatus::CollectPaymentDetails;
        self::$tokenizationFlow->initiated_from = PaymentFlowSource::Api;
        self::$tokenizationFlow->customer = self::$customer;
        self::$tokenizationFlow->saveOrFail();
    }

    public function testHasBillingAddress(): void
    {
        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $view = self::getService('test.payment_method_view_factory')->getPaymentInfoView($method, $method->gateway);

        $this->assertTrue($view->hasBillingAddress($method));

        $view->disableBillingAddress();
        $this->assertFalse($view->hasBillingAddress($method));
    }

    public function testRender(): void
    {
        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $view = self::getService('test.payment_method_view_factory')->getPaymentInfoView($method, $method->gateway);
        $this->assertInstanceOf(CardPaymentInfoView::class, $view);

        $html = $view->render(self::$company, $method, null, self::$tokenizationFlow);

        $this->assertGreaterThan(0, strlen($html), "{$method->id} payment form was empty");
        $this->assertDoesNotMatchRegularExpression('/E_NOTICE|E_WARNING|Notice|Warning/', $html, "{$method->id} payment form had errors");
    }
}
