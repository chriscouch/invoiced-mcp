<?php

namespace App\Tests\Core\Billing\Actions;

use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Billing\Action\ChangeSubscriptionAction;
use App\Core\Billing\Action\CreateOrUpdateCustomerAction;
use App\Core\Billing\Action\CreateOrUpdateSubscriptionAction;
use App\Core\Billing\Action\CreateSubscriptionAction;
use App\Core\Billing\Action\LocalizedPricingAdjustment;
use App\Core\Billing\Action\PurchasePageAction;
use App\Core\Billing\Action\SetDefaultPaymentMethodAction;
use App\Core\Billing\Audit\BillingAudit;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\BillingSystem\NullBillingSystem;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\BillingPaymentTerms;
use App\Core\Billing\Enums\PurchasePageReason;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\PurchasePageContext;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\Entitlements\FeatureCollection;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\CurrencyConverter;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class PurchasePageActionTest extends AppTestCase
{
    private static BillingProfile $billingProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::getService('test.database')->executeStatement('TRUNCATE InstalledProducts');
        FeatureCollection::clearCache();
        self::$billingProfile = BillingProfile::getOrCreate(self::$company);
    }

    private function getAction(?BillingSystemInterface $billingSystem = null): PurchasePageAction
    {
        $billingSystem ??= new NullBillingSystem();
        $tenant = self::getService('test.tenant');
        $factory = Mockery::mock(BillingSystemFactory::class);
        $factory->shouldReceive('get')->andReturn($billingSystem);
        $factory->shouldReceive('getForBillingProfile')->andReturn($billingSystem);
        $createOrUpdateCustomer = new CreateOrUpdateCustomerAction($factory);
        $setDefaultPaymentMethod = new SetDefaultPaymentMethodAction($factory);
        $billingItemFactory = new BillingItemFactory();
        $entitlementsManager = self::getService('test.company_entitlements_manager');
        $createAction = new CreateSubscriptionAction($factory, $billingItemFactory, $entitlementsManager);
        $billingAudit = Mockery::mock(BillingAudit::class);
        $updateAction = new ChangeSubscriptionAction($factory, $billingItemFactory, $billingAudit, $entitlementsManager);
        $subscriptionAction = new CreateOrUpdateSubscriptionAction($factory, $createAction, $updateAction);
        $formFactory = self::getService('test.form_factory');
        $userContext = self::getService('test.user_context');
        $userRegistration = self::getService('test.user_registration');
        $newCompanySignUp = self::getService('test.new_company_sign_up');
        $loginStrategy = self::getService('test.username_password_login_strategy');
        $pricingAdjuster = new LocalizedPricingAdjustment(self::getService('test.database'), Mockery::mock(CurrencyConverter::class));

        return new PurchasePageAction($tenant, $createOrUpdateCustomer, $setDefaultPaymentMethod, $subscriptionAction, $formFactory, $userContext, $userRegistration, $newCompanySignUp, $loginStrategy, $factory, 'test', $pricingAdjuster);
    }

    private function getPageContext(): PurchasePageContext
    {
        $pageContext = new PurchasePageContext();
        $pageContext->billing_profile = self::$billingProfile;
        $pageContext->tenant = self::$company;
        $pageContext->country = 'US';
        $pageContext->payment_terms = BillingPaymentTerms::AutoPay;
        $pageContext->reason = PurchasePageReason::Activate;
        $product = Product::where('name', 'Advanced Accounts Receivable')->one();
        $changeset = new EntitlementsChangeset(
            products: [
                $product,
            ],
            productPrices: [
                (int) $product->id => [
                    'price' => new Money('usd', 100),
                    'annual' => false,
                    'custom_pricing' => false,
                ],
            ],
            quota: [
                'users' => 3,
            ],
            usagePricing: [
                'user' => [
                    'threshold' => 3,
                    'unit_price' => new Money('usd', 200),
                ],
            ],
            billingInterval: BillingInterval::Monthly,
        );
        $pageContext->changeset = (object) json_decode((string) json_encode($changeset));

        return $pageContext;
    }

    public function testMakeForm(): void
    {
        $action = $this->getAction();
        $pageContext = $this->getPageContext();
        $form = $action->makeForm($pageContext);

        $expected = [
            'company' => 'TEST',
        ];
        $this->assertEquals($expected, $form->getData());
        $this->assertTrue($form->has('company'));
        $this->assertTrue($form->has('person'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('address1'));
        $this->assertTrue($form->has('address2'));
        $this->assertTrue($form->has('city'));
        $this->assertTrue($form->has('postal_code'));
        $this->assertTrue($form->has('agree'));
        $this->assertTrue($form->has('state'));
        $this->assertTrue($form->has('invoiced_token'));
    }

    public function testHandle(): void
    {
        $pageContext = $this->getPageContext();

        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('createOrUpdateCustomer')
            ->withArgs([self::$billingProfile, [
                'company' => 'TEST',
                'person' => 'Bob Loblaw',
                'email' => 'test@example.com',
                'address1' => '701 Brazos St',
                'address2' => 'Suite 1616',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
                'autopay' => true,
                'sales_rep' => null,
            ]])
            ->once();

        $billingSystem->shouldReceive('setDefaultPaymentMethod')
            ->withArgs([self::$billingProfile, 'tok_1234'])
            ->once();

        $billingSystem->shouldReceive('getCurrentSubscription')
            ->andThrow(new BillingException())
            ->once();

        $product = Product::where('name', 'Advanced Accounts Receivable')->one();
        $billingSystem->shouldReceive('createSubscription')
            ->andReturnUsing(function ($billingProfile, $items, $startDate) use ($product) {
                $this->assertEquals(self::$billingProfile, $billingProfile);
                $expected = [
                    new BillingSubscriptionItem(
                        price: new Money('usd', 100),
                        billingInterval: BillingInterval::Monthly,
                        product: $product,
                        description: 'TEST',
                    ),
                ];
                $this->assertEquals($expected, $items);
            })
            ->once();

        $action = $this->getAction($billingSystem);
        $form = Mockery::mock(FormInterface::class);
        $form->shouldReceive('getData')
            ->andReturn([
                'company' => 'TEST',
                'person' => 'Bob Loblaw',
                'email' => 'test@example.com',
                'address1' => '701 Brazos St',
                'address2' => 'Suite 1616',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'invoiced_token' => 'tok_1234',
            ]);
        $request = new Request();

        $action->handle($request, $form, $pageContext);

        $expected = ['Advanced Accounts Receivable'];
        $this->assertEquals($expected, self::$company->features->allProducts());
    }

    public function testHandleFail(): void
    {
        $this->expectException(BillingException::class);

        $pageContext = $this->getPageContext();

        $billingSystem = Mockery::mock(BillingSystemInterface::class);
        $billingSystem->shouldReceive('createOrUpdateCustomer')
            ->andThrow(new BillingException('Fail'))
            ->once();

        $action = $this->getAction($billingSystem);
        $form = Mockery::mock(FormInterface::class);
        $form->shouldReceive('getData')
            ->andReturn([
                'company' => 'TEST',
                'person' => 'Bob Loblaw',
                'email' => 'test@example.com',
                'address1' => '701 Brazos St',
                'address2' => 'Suite 1616',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'invoiced_token' => 'tok_1234',
            ]);
        $request = new Request();

        $action->handle($request, $form, $pageContext);
    }
}
