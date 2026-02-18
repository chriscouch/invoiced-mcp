<?php

namespace App\Tests\PaymentProcessing\Views\PaymentInfo;

use App\Core\Utils\RandomString;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\TokenizationFlowStatus;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\PaymentProcessing\Views\PaymentInfo\PaymentInstructionsPaymentInfoView;
use App\Tests\AppTestCase;

class PaymentInstructionsPaymentInfoViewTest extends AppTestCase
{
    private static TokenizationFlow $tokenizationFlow;

    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
        self::hasCustomer();

        self::$tokenizationFlow = new TokenizationFlow();
        self::$tokenizationFlow->identifier = RandomString::generate();
        self::$tokenizationFlow->status = TokenizationFlowStatus::CollectPaymentDetails;
        self::$tokenizationFlow->initiated_from = PaymentFlowSource::Api;
        self::$tokenizationFlow->customer = self::$customer;
        self::$tokenizationFlow->saveOrFail();
    }

    public function testRenderCheck(): void
    {
        $this->renderMethod(PaymentMethod::CHECK);
    }

    public function testRenderWireTransfer(): void
    {
        $this->renderMethod(PaymentMethod::WIRE_TRANSFER);
    }

    private function renderMethod(string $m): void
    {
        $method = PaymentMethod::instance(self::$company, $m);
        $view = self::getService('test.payment_method_view_factory')->getPaymentInfoView($method, $method->gateway);
        $this->assertInstanceOf(PaymentInstructionsPaymentInfoView::class, $view);

        $html = $view->render(self::$company, $method, null, self::$tokenizationFlow);

        $this->assertGreaterThan(0, strlen($html), "$m payment form was empty");
        $this->assertDoesNotMatchRegularExpression('/E_NOTICE|E_WARNING|Notice|Warning/', $html, "$m payment form had errors");
    }
}
