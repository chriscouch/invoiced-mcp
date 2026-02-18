<?php

namespace App\Tests\PaymentProcessing\Views\PaymentInfo;

use App\Core\Utils\RandomString;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\TokenizationFlowStatus;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\PaymentProcessing\Views\PaymentInfo\StripeAchPaymentInfoView;
use App\Tests\AppTestCase;

class StripeAchPaymentInfoViewTest extends AppTestCase
{
    private static TokenizationFlow $tokenizationFlow;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount(StripeGateway::ID, 'TEST_MERCHANT_ID', ['key' => 'sk_test']);
        self::acceptsACH(StripeGateway::ID);

        self::$tokenizationFlow = new TokenizationFlow();
        self::$tokenizationFlow->identifier = RandomString::generate();
        self::$tokenizationFlow->status = TokenizationFlowStatus::CollectPaymentDetails;
        self::$tokenizationFlow->initiated_from = PaymentFlowSource::Api;
        self::$tokenizationFlow->customer = self::$customer;
        self::$tokenizationFlow->saveOrFail();
    }

    private function getView(PaymentMethod $method): StripeAchPaymentInfoView
    {
        return self::getService('test.payment_method_view_factory')->getPaymentInfoView($method, StripeGateway::ID);
    }

    public function testShouldBeShown(): void
    {
        $method = PaymentMethod::instance(self::$company, PaymentMethod::ACH);
        $view = $this->getView($method);
        $this->assertTrue($view->shouldBeShown(self::$company, $method, self::$merchantAccount, self::$customer));
    }

    public function testRender(): void
    {
        $method = PaymentMethod::instance(self::$company, PaymentMethod::ACH);
        $view = $this->getView($method);

        $html = $view->render(self::$company, $method, self::$merchantAccount, self::$tokenizationFlow);

        $this->assertGreaterThan(0, strlen($html), "{$method->id} payment form was empty");
        $this->assertDoesNotMatchRegularExpression('/E_NOTICE|E_WARNING|Notice|Warning/', $html, "{$method->id} payment form had errors");
    }
}
