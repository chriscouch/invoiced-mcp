<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\Core\Utils\RandomString;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Interfaces\PaymentViewInterface;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;
use App\Tests\AppTestCase;

abstract class PaymentViewTestBase extends AppTestCase
{
    const METHOD = '';

    protected static PaymentFlow $paymentFlow;

    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasMerchantAccount(TestGateway::ID);

        self::$paymentFlow = new PaymentFlow();
        self::$paymentFlow->identifier = RandomString::generate();
        self::$paymentFlow->status = PaymentFlowStatus::CollectPaymentDetails;
        self::$paymentFlow->initiated_from = PaymentFlowSource::Api;
        self::$paymentFlow->currency = 'usd';
        self::$paymentFlow->amount = 100;
        self::$paymentFlow->saveOrFail();
    }

    protected function getFormBuilder(): PaymentFormBuilder
    {
        $portal = new CustomerPortal(self::$company, new CustomerHierarchy(self::getService('test.database')));
        $portal->setSignedInCustomer(self::$customer);

        $form = new PaymentFormBuilder($portal);
        $form->addInvoice(self::$invoice);

        return $form;
    }

    protected function getView(PaymentMethod $method): PaymentViewInterface
    {
        return self::getService('test.payment_method_view_factory')->getPaymentView($method, $method->gateway);
    }

    public function testShouldBeShown(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $form = $this->getFormBuilder()->build();
        $merchantAccount = self::$merchantAccount ?? null;
        $this->assertTrue($view->shouldBeShown($form, $method, $merchantAccount));
    }

    public function testGetPaymentFormCapabilities(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $this->assertEquals(new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: true,
            supportsConvenienceFee: true,
            hasReceiptEmail: true,
        ), $view->getPaymentFormCapabilities());
    }

    public function testRender(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $form = $this->getFormBuilder()->build();
        $merchantAccount = self::$merchantAccount ?? null;

        $html = $view->render($form, $method, $merchantAccount, self::$paymentFlow);

        $this->assertGreaterThan(0, strlen($html), "{$method->id} payment view was empty");
        $this->assertDoesNotMatchRegularExpression('/E_NOTICE|E_WARNING|Notice|Warning/', $html, "{$method->id} payment view had errors");
    }

    public function testViewParameters(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $builder = $this->getFormBuilder();
        $form = $builder->build();
        $merchantAccount = self::$merchantAccount ?? null;

        $params = $view->getViewParameters($form, $method, $merchantAccount, self::$paymentFlow);
        $params = $this->cleanViewParameters($params);

        $this->assertEquals($this->expectedViewParameters($builder, $method), $params);
    }

    protected function cleanViewParameters(array $params): array
    {
        return $params;
    }

    protected function expectedViewParameters(PaymentFormBuilder $form, PaymentMethod $paymentMethod): array
    {
        return [
            'currency' => 'usd',
            'description' => 'INV-00001',
            'paymentMethod' => $paymentMethod,
        ];
    }
}
