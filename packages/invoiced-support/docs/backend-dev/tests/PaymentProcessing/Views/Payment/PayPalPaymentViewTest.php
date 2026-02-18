<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

class PayPalPaymentViewTest extends PaymentViewTestBase
{
    const METHOD = PaymentMethod::PAYPAL;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $method = PaymentMethod::instance(self::$company, PaymentMethod::PAYPAL);
        $method->meta = 'paypal@example.com';
        $method->saveOrFail();
    }

    public function testGetPaymentFormCapabilities(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $this->assertEquals(new PaymentFormCapabilities(
            isSubmittable: false,
            supportsVaulting: false,
            supportsConvenienceFee: false,
            hasReceiptEmail: false,
        ), $view->getPaymentFormCapabilities());
    }

    protected function cleanViewParameters(array $params): array
    {
        $params = parent::cleanViewParameters($params);
        unset($params['companyIdentifier']);
        unset($params['paypalInvoice']);

        return $params;
    }

    protected function expectedViewParameters(PaymentFormBuilder $view, PaymentMethod $paymentMethod): array
    {
        $base = parent::expectedViewParameters($view, $paymentMethod);

        return array_replace($base, [
            'paypalUrl' => 'https://www.sandbox.paypal.com/cgi-bin/webscr',
            'paypalEnv' => 'www.sandbox',
            'ipnUrl' => 'http://invoiced.localhost:1234/paypal/ipn',
            'cancelReturnUrl' => 'http://'.self::$company->username.'.invoiced.localhost:1234/flows/'.self::$paymentFlow->identifier.'/canceled',
            'shoppingUrl' => 'http://'.self::$company->username.'.invoiced.localhost:1234/flows/'.self::$paymentFlow->identifier.'/complete',
            'returnUrl' => 'http://'.self::$company->username.'.invoiced.localhost:1234/flows/'.self::$paymentFlow->identifier.'/complete',
            'paypalEmail' => 'paypal@example.com',
        ]);
    }
}
