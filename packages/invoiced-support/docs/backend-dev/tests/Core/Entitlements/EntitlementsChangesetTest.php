<?php

namespace App\Tests\Core\Entitlements;

use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;

class EntitlementsChangesetTest extends AppTestCase
{
    public function testSerialize(): void
    {
        /** @var Product $product */
        $product = Product::where('name', 'Accounts Receivable Free')->one();
        $changeset = new EntitlementsChangeset(
            products: [
                $product,
            ],
            productPrices: [
                $product->id => [
                    'price' => new Money('usd', 100),
                    'annual' => false,
                    'custom_pricing' => false,
                ],
            ],
            features: [
                'network' => true,
                'phone_support' => false,
                'network_invitations' => false,
                'live_chat' => false,
                'api' => false,
                'needs_onboarding' => true,
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

        $expected = '{"features":{"network":true,"phone_support":false,"network_invitations":false,"live_chat":false,"api":false,"needs_onboarding":true},"products":['.$product->id.'],"productPrices":{"'.$product->id.'":{"annual":false,"price":100,"custom_pricing":false}},"replaceExistingProducts":false,"quota":{"users":3},"usagePricing":{"user":{"threshold":3,"unit_price":200}},"billingInterval":1}';
        $json = json_encode($changeset);
        $this->assertEquals($expected, $json);

        $this->assertEquals($changeset, EntitlementsChangeset::fromJson(json_decode((string) $json)));
    }
}
