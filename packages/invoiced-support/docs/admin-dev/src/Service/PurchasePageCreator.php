<?php

namespace App\Service;

use App\Entity\Forms\NewPurchasePage;
use App\Entity\Invoiced\PurchasePageContext;
use App\Security\RandomString;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Symfony\Component\Security\Core\User\UserInterface;

class PurchasePageCreator
{
    public function __construct(
        private ManagerRegistry $doctrine
    ) {
    }

    public function create(NewPurchasePage $newPurchasePage, UserInterface $user): PurchasePageContext
    {
        $billingProfile = $newPurchasePage->getBillingProfile();
        if (!$billingProfile) {
            throw new Exception('Missing billing profile');
        }

        // Product pricing plans
        if (0 == count($newPurchasePage->getProductPricingPlans())) {
            throw new Exception('You must add at least one product');
        }
        $productIds = [];
        $productPrices = [];
        foreach ($newPurchasePage->getProductPricingPlans() as $row) {
            $productId = $row['product']->getId();
            $productIds[] = $productId;
            $productPrices[$productId] = [
                'price' => round($row['price'] * 100),
                'annual' => $row['annual'],
                'custom_pricing' => false,
            ];
        }

        // Usage pricing plans
        $usagePricing = [];
        $quota = [];
        foreach ($newPurchasePage->getUsagePricingPlans() as $row) {
            $usageType = $row['usageType'];
            $usagePricing[$usageType] = [
                'threshold' => $row['threshold'],
                'unit_price' => round($row['unit_price'] * 100),
            ];
        }

        // Set user quota
        if (isset($usagePricing['user'])) {
            $quota['users'] = $usagePricing['user']['threshold'];

            // If there's an existing tenant then set the quota to the max of the
            // included users or current number of users on tenant.
            if ($tenant = $newPurchasePage->getTenant()) {
                foreach ($tenant->getQuotas() as $tenantQuota) {
                    if ('Users' == $tenantQuota->getName()) {
                        $quota['users'] = max($quota['users'], $tenantQuota->getLimit());
                    }
                }
            }
        }

        $pageContext = new PurchasePageContext();
        $pageContext->setIdentifier(RandomString::generate(48, RandomString::CHAR_ALNUM));
        $pageContext->setBillingProfile($billingProfile);
        $pageContext->setTenant($newPurchasePage->getTenant());
        $pageContext->setExpirationDate($newPurchasePage->getExpirationDate());
        $pageContext->setReason($newPurchasePage->getReason());
        $pageContext->setSalesRep((string) $user); /* @phpstan-ignore-line */
        $pageContext->setCountry($newPurchasePage->getCountry());
        $pageContext->setNote($newPurchasePage->getNote());
        $pageContext->setActivationFee($newPurchasePage->getActivationFee());
        $pageContext->setPaymentTerms($newPurchasePage->getPaymentTerms());

        // Enable localized pricing for all pages except reactivation pages
        if (4 != $pageContext->getReason()) { // 4 = Reactivate
            $pageContext->setLocalizedPricing(true);
        }

        $changeset = [
            'features' => [],
            'products' => $productIds,
            'productPrices' => $productPrices,
            'quota' => $quota,
            'usagePricing' => $usagePricing,
            'billingInterval' => $newPurchasePage->getBillingInterval(),
        ];
        $pageContext->setChangeset((string) json_encode($changeset));

        /** @var ObjectManager $em */
        $em = $this->doctrine->getManagerForClass(PurchasePageContext::class);
        $em->persist($pageContext);
        $em->flush();

        return $pageContext;
    }
}
