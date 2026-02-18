<?php

namespace App\Tests\Integrations\Adyen;

use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Integrations\Adyen\FlywirePaymentsOnboarding;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class FlywirePaymentsOnboardingTest extends AppTestCase
{
    public static MerchantAccount $adyenMerchantAccount;
    public static MerchantAccount $adyenMerchantAccount2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::$company->phone = '1234';
        self::$company->saveOrFail();
        self::hasMerchantAccount(AdyenGateway::ID);
        self::$adyenMerchantAccount = self::$merchantAccount;
        self::hasMerchantAccount(AdyenGateway::ID, 'SOME_OTHER_GATEWAY');
        self::$adyenMerchantAccount2 = self::$merchantAccount;
        self::hasMerchantAccount(FlywireGateway::ID);
    }

    private function resetPaymentMethods(string $method, bool $enabled, ?MerchantAccount $merchantAccount): void
    {
        $method = PaymentMethod::instance(self::$company, $method);
        if ($merchantAccount) {
            $method->setMerchantAccount($merchantAccount);
        } else {
            $method->merchant_account = null;
        }
        $method->enabled = $enabled;
        $method->saveOrFail();
    }

    private function verifyPaymentMethod(string $method, bool $enabled, ?MerchantAccount $merchantAccount): void
    {
        $pMethod = PaymentMethod::instance(self::$company, $method);
        $this->assertEquals($enabled, $pMethod->enabled);
        $this->assertEquals($pMethod->merchantAccount()?->id, $merchantAccount?->id);
    }

    /**
     * @dataProvider accountProvider
     */
    public function testEnableFromAdyenEnabled(
        string $country,
        bool $enabled,
        bool $inputCcEnabled,
        callable $inputCcAccount,
        bool $inputAchEnabled,
        callable $inputAchAccount,
        bool $outputCcEnabled,
        callable $outputCcAccount,
        bool $outputAchEnabled,
        callable $outputAchAccount,
    ): void {
        $onboarding = $this->getOnboarding();
        self::$company->country = $country;
        self::$company->saveOrFail();

        $this->resetPaymentMethods(PaymentMethod::CREDIT_CARD, $inputCcEnabled, $inputCcAccount());
        $this->resetPaymentMethods(PaymentMethod::ACH, $inputAchEnabled, $inputAchAccount());

        if ($enabled) {
            $adyenAccount = new AdyenAccount();
            $adyenAccount->saveOrFail();
            $onboarding->activateAccount($adyenAccount);
        } else {
            $onboarding->disablePayments();
        }

        $this->verifyPaymentMethod(PaymentMethod::CREDIT_CARD, $outputCcEnabled, $outputCcAccount());
        $this->verifyPaymentMethod(PaymentMethod::ACH, $outputAchEnabled, $outputAchAccount());
    }

    public function accountProvider(): array
    {
        return [
            'US no account to adyen enabled' => ['US', true, false, fn () => null, false, fn () => null, true, fn () => self::$adyenMerchantAccount, true, fn () => self::$adyenMerchantAccount],
            'US random disabled account to adyen enabled' => ['US', true, false, fn () => self::$merchantAccount, false, fn () => self::$merchantAccount, true, fn () => self::$adyenMerchantAccount, true, fn () => self::$adyenMerchantAccount],
            'US random disabled account to adyen disabled' => ['US', false, false, fn () => self::$merchantAccount, false, fn () => self::$merchantAccount, false, fn () => self::$adyenMerchantAccount, false, fn () => self::$adyenMerchantAccount],
            'US random enabled account to adyen enabled' => ['US', true, true, fn () => self::$merchantAccount, true, fn () => self::$merchantAccount, true, fn () => self::$adyenMerchantAccount, true, fn () => self::$adyenMerchantAccount],
            'US random enabled account to adyen disabled' => ['US', false, true, fn () => self::$merchantAccount, true, fn () => self::$merchantAccount, true, fn () => self::$merchantAccount, true, fn () => self::$merchantAccount],
            'US adyen disabled account to adyen enabled' => ['US', true, false, fn () => self::$adyenMerchantAccount2, false, fn () => self::$adyenMerchantAccount2, true, fn () => self::$adyenMerchantAccount2, true, fn () => self::$adyenMerchantAccount2],
            'US adyen enabled account to adyen enabled' => ['US', true, true, fn () => self::$adyenMerchantAccount2, true, fn () => self::$adyenMerchantAccount2, true, fn () => self::$adyenMerchantAccount2, true, fn () => self::$adyenMerchantAccount2],
            'UA no account to adyen enabled' => ['UA', true, false, fn () => null, false, fn () => null, true, fn () => self::$adyenMerchantAccount, false, fn () => null],
            'UA random disabled account to adyen enabled' => ['UA', true, false, fn () => self::$merchantAccount, false, fn () => self::$merchantAccount, true, fn () => self::$adyenMerchantAccount, false, fn () => self::$merchantAccount],
            'UA random disabled account to adyen disabled' => ['UA', false, false, fn () => self::$merchantAccount, false, fn () => self::$merchantAccount, false, fn () => self::$adyenMerchantAccount, false, fn () => self::$merchantAccount],
            'UA random enabled account to adyen enabled' => ['UA', true, true, fn () => self::$merchantAccount, true, fn () => self::$merchantAccount, true, fn () => self::$adyenMerchantAccount, true, fn () => self::$merchantAccount],
            'UA random enabled account to adyen disabled' => ['UA', false, true, fn () => self::$merchantAccount, true, fn () => self::$merchantAccount, true, fn () => self::$merchantAccount, true, fn () => self::$merchantAccount],
            'UA adyen disabled account to adyen enabled' => ['UA', true, false, fn () => self::$adyenMerchantAccount2, false, fn () => null, true, fn () => self::$adyenMerchantAccount2, false, fn () => null],
            'UA adyen enabled account to adyen enabled' => ['UA', true, true, fn () => self::$adyenMerchantAccount2, false, fn () => null, true, fn () => self::$adyenMerchantAccount2, false, fn () => null],
        ];
    }

    private function getOnboarding(): FlywirePaymentsOnboarding
    {
        return self::getService('test.flywire_payments_onboarding');
    }

    public function testIsAlreadyEnrolled(): void
    {
        $onboarding = $this->getOnboarding();
        $this->assertFalse($onboarding->isAlreadyEnrolled(self::$company));

        self::acceptsFlywire();

        $this->assertTrue($onboarding->isAlreadyEnrolled(self::$company));

        $paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $paymentMethod->enabled = false;
        $paymentMethod->saveOrFail();

        $this->assertFalse($onboarding->isAlreadyEnrolled(self::$company));

        $adyenAccount = new AdyenAccount();
        $adyenAccount->saveOrFail();

        $this->assertFalse($onboarding->isAlreadyEnrolled(self::$company));

        $adyenAccount->industry_code = '1234';
        $adyenAccount->terms_of_service_acceptance_ip = '127.0.0.1';
        $adyenAccount->saveOrFail();
    }

    public function testNeedsStartPage(): void
    {
        $onboarding = $this->getOnboarding();
        $this->assertTrue($onboarding->needsStartPage(null));

        $adyenAccount = new AdyenAccount();
        $this->assertTrue($onboarding->needsStartPage($adyenAccount));

        $adyenAccount->industry_code = '1234';
        $adyenAccount->terms_of_service_acceptance_ip = '127.0.0.1';
        $this->assertFalse($onboarding->needsStartPage($adyenAccount));
    }
}
