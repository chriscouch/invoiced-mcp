<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

class PaymentInstructionsPaymentViewTest extends PaymentViewTestBase
{
    const METHOD = PaymentMethod::CHECK;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $method = PaymentMethod::instance(self::$company, PaymentMethod::CHECK);
        $method->meta = 'Instructions...';
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

    protected function expectedViewParameters(PaymentFormBuilder $form, PaymentMethod $paymentMethod): array
    {
        return array_replace(parent::expectedViewParameters($form, $paymentMethod), [
            'paymentMethodId' => PaymentMethod::CHECK,
            'paymentInstructions' => 'Instructions...',
        ]);
    }

    protected function cleanViewParameters(array $params): array
    {
        unset($params['dateFieldId']);

        return $params;
    }
}
