<?php

namespace App\EntryPoint\Controller;

use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Models\CompanyNote;
use App\Companies\Models\Member;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\LoginStrategy\UsernamePasswordLoginStrategy;
use App\Core\Billing\Action\CancelSubscriptionAction;
use App\Core\Billing\Action\ChangeExtraUserCountAction;
use App\Core\Billing\Action\LocalizedPricingAdjustment;
use App\Core\Billing\Action\PurchasePageAction;
use App\Core\Billing\Action\ReactivateSubscriptionAction;
use App\Core\Billing\Action\SetDefaultPaymentMethodAction;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\AbstractUsageRecord;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\CustomerUsageRecord;
use App\Core\Billing\Models\InvoiceUsageRecord;
use App\Core\Billing\Models\MoneyBilledUsageRecord;
use App\Core\Billing\Models\PurchasePageContext;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Core\Billing\Webhook\InvoicedBillingWebhook;
use App\Core\Billing\Webhook\StripeBillingWebhook;
use App\Core\Database\TransactionManager;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ReCaptcha\ReCaptcha;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\LocaleSwitcher;

#[Route(schemes: '%app.protocol%', host: '%app.domain%')]
class BillingController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[Route(path: '/companies/{companyId}/billing', name: 'dashboard_billing_info', host: '%app.domain%', methods: ['GET'], schemes: '%app.protocol%')]
    public function billingInfo(TenantContext $tenant, string $companyId, BillingSystemFactory $billingSystemFactory, UserContext $userContext): JsonResponse
    {
        $company = Company::findOrFail($companyId);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $accessRights = $this->checkBusinessBillingPermission($company, $userContext);
        if(!$accessRights)
            $this->checkEditPermission($company, $userContext);

        $result = [];
        $billingProfile = BillingProfile::getOrCreate($company);
        $billingSystem = $billingSystemFactory->getForBillingProfile($billingProfile);

        try {
            // billing state
            $billingState = $billingSystem->getBillingState($billingProfile);
            $result['source'] = $billingState->paymentSource;
            $result['next_charge_amount'] = $billingState->nextChargeAmount;
            $result['discount'] = $billingState->discount;
            $result['cancel_at_period_end'] = $billingState->cancelAtPeriodEnd;
            $result['next_bill_date'] = $billingState->nextBillDate?->getTimestamp();
            $result['autopay'] = $billingState->autopay;

            // update payment info url
            $result['update_payment_info_url'] = $billingSystem->getUpdatePaymentInfoUrl($billingProfile);

            // billing history
            $result['billing_history'] = $billingSystem->getBillingHistory($billingProfile);
        } catch (BillingException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        return new JsonResponse(array_merge($result, [
            'products' => $company->features->allProducts(),
            'usage' => $this->getUsage($company),
            'usage_history' => $this->getUsageHistory($company),
            'usage_pricing_plans' => $this->getUsagePricingPlans($company),
        ]));
    }

    #[Route(path: '/companies/{companyId}/reactivate', name: 'dashboard_reactivate_billing', defaults: ['no_database_transaction' => true], methods: ['PUT'])]
    public function reactivateBilling(TenantContext $tenant, string $companyId, UserContext $userContext, ReactivateSubscriptionAction $reactivateAction): Response
    {
        $company = Company::findOrFail($companyId);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        try {
            $reactivateAction->reactivate($company);

            return new Response('', 204);
        } catch (BillingException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route(path: '/companies/{companyId}/default_payment_method', name: 'dashboard_update_payment_method', defaults: ['no_database_transaction' => true], methods: ['PUT'])]
    public function updatePaymentMethod(Request $request, TenantContext $tenant, string $companyId, UserContext $userContext, SetDefaultPaymentMethodAction $paymentMethodAction): Response
    {
        $company = Company::findOrFail($companyId);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        try {
            if ($token = (string) $request->request->get('payment_method_token')) {
                $billingProfile = BillingProfile::getOrCreate($company);
                $paymentMethodAction->set($billingProfile, $token);
            }

            return new Response('', 204);
        } catch (BillingException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route(path: '/companies/{companyId}/cancel', name: 'dashboard_cancel_account', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function cancelAccount(Request $request, UserContext $userContext, UsernamePasswordLoginStrategy $strategy, CancelSubscriptionAction $cancelAction, string $companyId, TenantContext $tenant, BillingItemFactory $billingItemFactory): JsonResponse
    {
        $company = Company::findOrFail($companyId);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        // do not allow higher value accounts (>= $500/month) to cancel using this form
        try {
            $billingProfile = BillingProfile::getOrCreate($company);
            $monthlyTotal = $billingItemFactory->calculateTotal($billingProfile);
            if ($monthlyTotal->greaterThanOrEqual(new Money('usd', 50000))) {
                return new JsonResponse([
                    'type' => 'invalid_request',
                    'message' => 'Please contact Invoiced Support to cancel your account.',
                ], 400);
            }
        } catch (BillingException) {
            // ignore exceptions on this check
        }

        // verify user's password
        $user = $userContext->getOrFail();
        $password = (string) $request->request->get('password');
        if (!$strategy->verifyPassword($user, $password)) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => 'Your password is not correct.',
            ], 400);
        }

        // cancel account
        $why = $request->request->get('why');
        $reason = (string) $request->request->get('reason');
        $reasonKey = strtolower(str_replace(' ', '_', $reason));

        try {
            $cancelAction->cancel($company, $reasonKey, true);
        } catch (BillingException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        // save the cancellation comment as a note on the company
        $name = $user->name(true);
        $note = new CompanyNote();
        $note->tenant_id = $company->id;
        $note->note = "$name requested cancellation for {$company->name}:\nReason: $reason\nComments: $why";
        $note->created_by = 'System';
        $note->save();

        return new JsonResponse([
            'canceled_now' => $company->canceled,
        ]);
    }

    #[Route(path: '/companies/{companyId}/extra_users', name: 'dashboard_change_extra_users', defaults: ['no_database_transaction' => true], methods: ['PUT'])]
    public function changeUserCount(Request $request, TenantContext $tenant, UserContext $userContext, ChangeExtraUserCountAction $changeAction, string $companyId): JsonResponse
    {
        $company = Company::findOrFail($companyId);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        try {
            $count = (int) $request->request->get('count');
            $changeAction->change($company, $count);
        } catch (BillingException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        return new JsonResponse(['count' => $count]);
    }

    #[Route(path: '/purchase/{id}', name: 'purchase_page', host: '%app.domain%', methods: ['GET', 'POST'], schemes: '%app.protocol%')]
    public function purchasePage(
        Request $request,
        UserContext $userContext,
        ReCaptcha $recaptcha,
        LocaleSwitcher $localeSwitcher,
        TransactionManager $transactionManager,
        PurchasePageAction $purchasePageAction,
        LocalizedPricingAdjustment $pricingAdjuster,
        string $invoicedPublishableKey,
        string $invoicedTokenizationUrl,
        string $id,
        string $adyenClientKey,
        bool $adyenLiveMode,
        string $tokenizationUrl,
    ): Response
    {
        $pageContext = PurchasePageContext::where('identifier', $id)->oneOrNull();
        if (!$pageContext instanceof PurchasePageContext) {
            throw new NotFoundHttpException();
        }

        // Check if page is completed
        if ($pageContext->completed_at) {
            return $this->render('billing/purchaseError.twig', [
                'user' => $userContext->get(),
                'reason' => 'completed',
            ]);
        }

        // Check if page is expired
        if (CarbonImmutable::now()->startOfDay()->isAfter($pageContext->expiration_date)) {
            return $this->render('billing/purchaseError.twig', [
                'user' => $userContext->get(),
                'reason' => 'expired',
            ]);
        }

        $localeSwitcher->setLocale('en_'.$pageContext->country);

        $form = $purchasePageAction->makeForm($pageContext);
        $form->handleRequest($request);

        // Check recaptcha before processing form
        if ($form->isSubmitted()) {
            $captchaResp = (string) $request->request->get('g-recaptcha-response');
            if (!$captchaResp) {
                $form->addError(new FormError('Please complete the captcha prompt.'));
            }

            $resp = $recaptcha->verify($captchaResp, (string) $request->getClientIp());
            if (!$resp->isSuccess()) {
                $form->addError(new FormError('Captcha response failed.'));
            }
        }

        // Handle form submission
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $transactionManager->perform(function () use ($request, $form, $pageContext, $purchasePageAction) {
                    $purchasePageAction->handle($request, $form, $pageContext);
                });

                return $this->redirectToRoute('purchase_complete', ['id' => $pageContext->identifier]);
            } catch (BillingException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        // Update page last viewed
        $pageContext->last_viewed = CarbonImmutable::now();
        $pageContext->save();

        // Build purchase item display list
        $params = $this->makeDisplayItems($pageContext, $pricingAdjuster);

        return $this->render('billing/purchase.twig', array_merge($params, [
            'user' => $userContext->get(),
            'errors' => [],
            'note' => $pageContext->note,
            'billingProfileName' => $pageContext->billing_profile->name,
            'company' => $pageContext->tenant?->name,
            'salesRep' => $pageContext->sales_rep,
            'paymentTerms' => $pageContext->payment_terms->name,
            'country' => $pageContext->country,
            'reason' => $pageContext->reason->name,
            'invoicedPublishableKey' => $invoicedPublishableKey,
            'invoicedTokenizationUrl' => $invoicedTokenizationUrl,
            'adyenClientKey' => $adyenClientKey,
            'payFacEnv' => $adyenLiveMode ? 'live' : 'test',
            'tokenizationUrl' => $tokenizationUrl,
            'form' => $form->createView(),
        ]));
    }

    #[Route(path: '/purchase/{id}/complete', name: 'purchase_complete', host: '%app.domain%', methods: ['GET'], schemes: '%app.protocol%')]
    public function purchaseComplete(string $dashboardUrl, string $id): Response
    {
        $pageContext = PurchasePageContext::where('identifier', $id)->oneOrNull();
        if (!$pageContext instanceof PurchasePageContext || !$pageContext->completed_at) {
            throw new NotFoundHttpException();
        }

        $token = null;
        if ($company = $pageContext->tenant) {
            $companyEmail = CompanyEmailAddress::queryWithTenant($company)
                ->where('email', $company->email)
                ->oneOrNull();
            $token = !$companyEmail?->verified_at && !$company->creator()?->has_password ? $companyEmail?->token : null;
        }

        return $this->render('billing/purchaseThanks.twig', [
            'dashboardUrl' => $dashboardUrl,
            'token' => $token,
        ]);
    }

    #[Route(path: '/billing/webhook', name: 'stripe_billing_webhook', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function stripeWebhook(Request $request, StripeBillingWebhook $webhook): Response
    {
        return new Response($webhook->handle($request->request->all()));
    }

    #[Route(path: '/billing/invoiced_webhook', name: 'invoiced_billing_webhook', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function invoicedWebhook(Request $request, InvoicedBillingWebhook $webhook): Response
    {
        return new Response($webhook->handle($request->request->all()));
    }

    private function getUsage(Company $company): array
    {
        $users = Member::queryWithTenant($company)
            ->where('expires', 0)
            ->count();
        $billingPeriod = MonthBillingPeriod::now();

        return [
            'no_invoices' => InvoiceUsageRecord::getOrCreate($company, $billingPeriod)->count,
            'no_customers' => CustomerUsageRecord::getOrCreate($company, $billingPeriod)->count,
            'no_users' => $users,
            'money_billed' => MoneyBilledUsageRecord::getOrCreate($company, $billingPeriod)->count,
        ];
    }

    private function getUsageHistory(Company $company): array
    {
        $types = [];
        if ($company->features->has('accounts_receivable')) {
            $types['customers'] = CustomerUsageRecord::class;
            $types['invoices'] = InvoiceUsageRecord::class;
            $types['money_billed'] = MoneyBilledUsageRecord::class;
        }

        $result = [];
        foreach ($types as $metric => $modelClass) {
            $usageData = $this->getUsageHistoryForType($company, $modelClass);
            foreach ($usageData as $k => $value) {
                if (!isset($result[$k][$metric])) {
                    $result[$k][$metric] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get up to last 24 months of usage history.
     *
     * @param class-string<AbstractUsageRecord> $modelClass
     */
    private function getUsageHistoryForType(Company $company, string $modelClass): array
    {
        $usage = [];
        $records = $modelClass::queryWithTenant($company)
            ->where('month', MonthBillingPeriod::now()->getName(), '<')
            ->sort('month DESC')
            ->first(24);

        foreach ($records as $volume) {
            $usage[$volume->month] = $volume->count;
        }

        return $usage;
    }

    private function getUsagePricingPlans(Company $company): array
    {
        $result = [];
        $usagePricingPlans = UsagePricingPlan::where('tenant_id', $company)->first(100);
        foreach ($usagePricingPlans as $usagePricingPlan) {
            $result[] = [
                'type' => $usagePricingPlan->usage_type->getName(),
                'name' => $usagePricingPlan->usage_type->getFriendlyName(),
                'unit' => $usagePricingPlan->usage_type->getUnit(),
                'threshold' => $usagePricingPlan->threshold,
                'unit_price' => $usagePricingPlan->unit_price,
            ];
        }

        return $result;
    }

    private function checkEditPermission(Company $company, UserContext $userContext): void
    {
        $user = $userContext->get();
        if (!$user) {
            throw new UnauthorizedHttpException('');
        }

        $member = Member::getForUser($user);
        if (!$member || !$company->memberCanEdit($member)) {
            throw new UnauthorizedHttpException('');
        }
    }

    private function checkBusinessBillingPermission(Company $company, UserContext $userContext): bool
    {
        $user = $userContext->get();
        if (!$user) {
            throw new UnauthorizedHttpException('');
        }

        $member = Member::getForUser($user);
        if (!$member) {
            throw new UnauthorizedHttpException('');
        }

        if ($company->memberCanAccessBusinessBilling($member)) {
           return true;
        }

        return false;
    }

    private function makeDisplayItems(PurchasePageContext $pageContext, LocalizedPricingAdjustment $pricingAdjuster): array
    {
        $changeset = EntitlementsChangeset::fromJson($pageContext->changeset);
        $displayItems = [];
        $formatter = MoneyFormatter::get();
        $recurringTotal = Money::zero('usd');
        $oneTimeTotal = Money::zero('usd');
        $interval = $changeset->billingInterval ?: $pageContext->billing_profile->billing_interval;
        if (!$interval) {
            throw new RuntimeException('Missing billing interval on purchase page');
        }

        // Add product pricing
        foreach ($changeset->products as $product) {
            $pricePlan = $changeset->getProductPrice($product);
            if ($pricePlan) {
                $price = $pricePlan['price'];

                // Scale to billing interval
                $fromInterval = $pricePlan['annual'] ? BillingInterval::Yearly : BillingInterval::Monthly;
                $price = $this->normalizeToBillingInterval($price, $fromInterval, $interval);
            } else {
                $price = Money::zero('usd');
            }

            $recurringTotal = $recurringTotal->add($price);
            $precision = 0 == $price->amount % 100 ? 0 : 2;

            $displayItems[] = [
                'description' => $product->name,
                'price' => $formatter->format($price, ['precision' => $precision]).'/'.strtolower($interval->getUnitName()),
            ];
        }

        // Add usage pricing plans
        foreach ($changeset->usagePricing as $usageTypeName => $pricePlan) {
            $usageType = UsageType::fromName($usageTypeName);
            $price = $pricePlan['unit_price'];

            // Special case for users
            if ('user' == $usageTypeName) {
                $quantity = $changeset->quota['users'] ?? 0;
                if ($quantity > 0) {
                    $delta = max(0, $quantity - $pricePlan['threshold']);
                    $price = Money::fromDecimal('usd', $price->toDecimal() * $delta);
                    $recurringTotal = $recurringTotal->add($price);

                    // Scale to billing interval
                    $price = $this->normalizeToBillingInterval($price, BillingInterval::Monthly, $interval);
                    $precision = 0 == $price->amount % 100 ? 0 : 2;

                    $displayItems[] = [
                        'description' => 'Users x '.number_format($quantity),
                        'price' => $formatter->format($price, ['precision' => $precision]).'/'.strtolower($interval->getUnitName()),
                    ];
                }
            } elseif ($price->isPositive()) {
                $precision = 0 == $price->amount % 100 ? 0 : 2;
                $displayItems[] = [
                    'description' => $usageType->getFriendlyName().', '.number_format($pricePlan['threshold']).' Included',
                    'price' => $formatter->format($price, ['precision' => $precision]).'/add\'l',
                ];
            }
        }

        // Add one time items
        if ($fee = $pageContext->activation_fee) {
            $price = Money::fromDecimal('usd', $fee);
            $oneTimeTotal = $oneTimeTotal->add($price);
            $precision = 0 == $price->amount % 100 ? 0 : 2;
            $displayItems[] = [
                'description' => 'Activation Fee',
                'price' => $formatter->format($price, ['precision' => $precision]),
            ];
        }

        // Add purchase price parity adjustment
        if ($pageContext->localized_pricing) {
            $pppAdjustment = $pricingAdjuster->getLocalizedAdjustment($pageContext->country);
            if ($pppAdjustment) {
                $displayItems[] = [
                    'description' => 'Localized Pricing Adjustment',
                    'price' => ($pppAdjustment * 100).'%',
                ];
                $recurringTotal = $pricingAdjuster->applyAdjustment($recurringTotal, $pppAdjustment);
                $oneTimeTotal = $pricingAdjuster->applyAdjustment($oneTimeTotal, $pppAdjustment);
            }
        }

        $precision = 0 == $recurringTotal->amount % 100 && 0 == $oneTimeTotal->amount % 100 ? 0 : 2;

        return [
            'displayItems' => $displayItems,
            'recurringTotal' => $recurringTotal->isPositive() ? $formatter->format($recurringTotal, ['precision' => $precision]).'/'.strtolower($interval->getUnitName()) : null,
            'oneTimeTotal' => $oneTimeTotal->isPositive() ? $formatter->format($oneTimeTotal, ['precision' => $precision]) : null,
        ];
    }

    /**
     * Normalizes a price from one billing interval to another.
     * Eg. monthly to yearly.
     */
    private function normalizeToBillingInterval(Money $price, BillingInterval $from, BillingInterval $to): Money
    {
        // Calculate the conversion factor by number of months in interval
        return Money::fromDecimal('usd', $price->toDecimal() / $from->numMonths() * $to->numMonths());
    }
}
