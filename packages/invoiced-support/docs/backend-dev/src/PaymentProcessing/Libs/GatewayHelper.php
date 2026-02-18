<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\Level3Data;
use App\PaymentProcessing\ValueObjects\Level3LineItem;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use Carbon\CarbonImmutable;

final class GatewayHelper
{
    private const SEC_CODE_WEB = 'WEB';
    private const SEC_CODE_BUSINESS = 'CCD';
    private const SEC_CODE_PERSONAL = 'PPD';

    private const array COMMODITY_CODES = [
        'Academia' => '10101515', // Armadillos
        'Accounting' => '44101803', // Accounting machines
        'Animal care' => '10141607', // Animal collars
        'Agriculture' => '60106201', // Agriculture teaching aids or materials
        'Apparel' => '56101540', // Apparel costumers
        'Banking' => '93141901', // Agricultural commercial banking services
        'Beauty and Cosmetics' => '10161905', // Dried twigs or sticks
        'Biotechnology' => '60106202', // Biotechnology teaching aids or materials
        'Business Services' => '10151606', // Sesame seeds
        'Chemicals' => '47101604', // Boiler feed chemicals
        'Communications' => '26121616', // Telecommunications cable
        'Construction' => '30191701', // Construction shed
        'Consulting' => '80101512', // Actuarial consulting services
        'Distribution' => '93131607', // Food distribution services
        'Education' => '86131602', // Dance education
        'Electronics' => '60106401', // Electronics kits
        'Energy' => '60104704', // Energy class kits
        'Engineering' => '81101507', // Dam engineering
        'Entertainment' => '56101505', // Entertainment centers
        'Environmental' => '92112207', // Environmental warfare //TODO
        'Finance' => '84121901', // Housing finance
        'Food and Beverage' => '40142012',  // Food and beverage hose
        'Government and Public services' => '10191506', // Rodenticides
        'Grant and Fundraising' => '10151508', // Eggplant seeds or seedlings
        'Healthcare' => '80121611', // Healthcare claim law services
        'Hospitality' => '10171603', // Phosphatic fertilizer //TODO
        'HR and Recruitment' => '10191507', // Bird repellents //TODO
        'Insurance' => '84131601', // Life insurance
        'Legal Services' => '10151601', // Wheat seeds //TODO
        'Machinery' => '23111506', // Cooking machinery
        'Manufacturing' => '73141703', // Knitwear manufacturing services
        'Marketing and Advertising' => '10131603', // Animal carrying cases //TODO
        'Media' => '31191514', // Tumble media
        'Medical Equipment and Supplies' => '10111305', // Medicated pet treatments
        'Not for profit' => '80171908', // Not for profit organization relations consultation and engagement
        'Oil and Gas' => '72141113', // Oil and gas pipeline construction service
        'Pharmaceutical' => '23151823', // Diagnostic radiopharmaceutical
        'Real Estate' => '80121606', // Real estate law
        'Recreation' => '25111803', // Recreational rowboats
        'Recruitment' => '93141802', // Recruitment services
        'Research (non-academic)' => '10141608', // Harnesses or its accessories //TODO
        'Retail' => '80141703', // Retail distribution services
        'Security Services' => '93131611', // Food security services
        'Shipping' => '24112502', // One piece die cut shipping cartons
        'Software and IT' => '81112001', // Online data processing service
        'Technology Hardware' => '43211602', // Docking stations
        'Telecommunications' => '26121616', // Telecommunications cable
        'Transportation' => '78101905', // Rail truck transportation
        'Travel' => '53121801', // Travel kits
        'Utilities' => '72141126', // Underground utilities construction service
        'Other' => '80161501', // Office administration or secretarial services
    ];

    /**
     * Generates a description for a payment.
     *
     * @param ReceivableDocument[] $documents
     */
    public static function makeDescription(array $documents): string
    {
        foreach ($documents as $document) {
            if (($document instanceof Invoice || $document instanceof Estimate) && $document->number) {
                return $document->number;
            }
        }

        return 'Advance Payment';
    }

    public static function getEmail(Customer $customer, array $parameters): ?string
    {
        if (isset($parameters['email'])) {
            return $parameters['email'];
        }

        if (isset($parameters['receipt_email'])) {
            return $parameters['receipt_email'];
        }

        if ($email = $customer->emailAddress()) {
            return $email;
        }

        return null;
    }

    /**
     * Gets the ACH SEC code from the merchant account if
     * given. Defaults to WEB. This is our most commonly
     * used SEC code.
     */
    public static function secCodeWeb(PaymentGatewayConfiguration $gatewayConfiguration): string
    {
        $secCode = $gatewayConfiguration->credentials->ach_sec_code ?? '';
        if ($secCode) {
            return $secCode;
        }

        return self::SEC_CODE_WEB;
    }

    /**
     * Gets the ACH SEC code from the merchant account if
     * given. Defaults to CCD for business bank accounts
     * and defaults to PPD for personal bank accounts. Some
     * gateways prefer these SEC codes instead of WEB.
     */
    public static function secCodeByOwnerType(PaymentGatewayConfiguration $gatewayConfiguration, BankAccount $bankAccount): string
    {
        $secCode = $gatewayConfiguration->credentials->ach_sec_code ?? '';
        if ($secCode) {
            return $secCode;
        }

        if ('individual' == $bankAccount->account_holder_type) {
            return self::SEC_CODE_PERSONAL;
        }

        return self::SEC_CODE_BUSINESS;
    }

    /**
     * Builds Level 3 data for a given transaction against a set of documents.
     *
     * @param ReceivableDocument[] $documents
     */
    public static function makeLevel3(array $documents, Customer $customer, Money $amount): Level3Data
    {
        $company = $customer->tenant();
        $firstDocument = $documents ? $documents[0] : null;
        $currency = $amount->currency;
        $totalSalesTax = Money::zero($currency);
        $totalShipping = Money::zero($currency);
        $total = Money::zero($currency);
        $commodityCode = self::getCommodityCode($company->industry);
        $lineItems = [];

        foreach ($documents as $document) {
            // Aggregate the sales tax from each document
            $calculatedDocument = InvoiceCalculator::calculate($document->currency, $document->items(), $document->discounts(), $document->taxes());
            $totalSalesTax = $totalSalesTax->add(Money::fromDecimal($currency, $calculatedDocument->totalTaxes));

            // Determine discounts for document
            $additionalDiscounts = self::computeAdditionalDiscountsPerLineItem($document, $currency);
            $appliedDiscountAdjustment = false;

            /** @var LineItem $item */
            foreach ($document->items as $item) {
                // Add shipping line items to the shipping total.
                if ('shipping' == $item->type) {
                    $totalShipping = $totalShipping->add(Money::fromDecimal($currency, $item->amount));
                    continue;
                }

                $discountAmount = self::getDiscountAmountForCurrentLineItem($currency, $item, $additionalDiscounts, $appliedDiscountAdjustment);

                $lineItem = new Level3LineItem(
                    productCode: self::sanitizeAscii(self::getProductCode($item->catalog_item)),
                    description: self::sanitizeAscii($item->name ?: 'Unknown'),
                    commodityCode: $commodityCode,
                    quantity: $item->quantity,
                    unitCost: Money::fromDecimal($currency, $item->unit_cost),
                    unitOfMeasure: 'EA',
                    discount: $discountAmount,
                );

                // Don't add zero amount line items
                if ($discountAmount->isZero() && $lineItem->total->isZero()) {
                    continue;
                }

                $lineItems[] = $lineItem;
                $total = $total->add($lineItem->total);
            }
        }

        // Ensure there is always a sales tax amount to qualify for L2 rates.
        if (!$totalSalesTax->isPositive()) {
            $totalSalesTax = Money::fromDecimal($currency, $amount->toDecimal() * 0.1);
        }

        // Calculate the total from the line items, sales tax, and shipping
        $total = $total->add($totalSalesTax)->add($totalShipping);

        // The line items must always add up to the order total in order to
        // qualify for L2/L3 rates. If they do not add up to the order total
        // then an adjustment line item is added. This scenario can happen
        // when there is a convenience fee or partial payment.
        $difference = $amount->subtract($total);
        if (!$difference->isZero() && count($lineItems) > 0) {
            $lineItems[] = new Level3LineItem(
                productCode: self::getProductCode(),
                description: 'Adjustment',
                commodityCode: $commodityCode,
                quantity: 1,
                unitCost: $difference,
                unitOfMeasure: 'EA',
                discount: Money::zero($currency),
            );
        }

        // Any scenario in which the line items cannot be directly passed through
        // will produce a single "Order Summary" line item.
        if (self::useOrderSummaryLine($lineItems)) {
            $totalShipping = new Money($currency, 0);
            $lineItems = [
                new Level3LineItem(
                    productCode: count($lineItems) > 0 ? $lineItems[0]->productCode : self::getProductCode(),
                    description: count($lineItems) > 0 ? $lineItems[0]->description : 'Order Summary',
                    commodityCode: $commodityCode,
                    quantity: 1,
                    unitCost: $amount->subtract($totalSalesTax),
                    unitOfMeasure: 'EA',
                    discount: Money::zero($currency),
                ),
            ];
        }

        return new Level3Data(
            poNumber: self::sanitizeAscii(self::getPoNumber($firstDocument)),
            orderDate: $firstDocument ? CarbonImmutable::createFromTimestamp($firstDocument->date) : CarbonImmutable::now(),
            shipTo: self::makeShipTo($firstDocument, $customer, $company),
            merchantPostalCode: self::sanitizeAscii(((string) $company->postal_code) ?: '78701'),
            summaryCommodityCode: $commodityCode,
            salesTax: $totalSalesTax,
            shipping: $totalShipping,
            lineItems: array_slice($lineItems, 0, 100),
        );
    }

    /**
     * Builds Klarna Line items for a given transaction against a set of documents.
     */
    public static function makeKlarnaLineItems(array $documents, Money $amount): array
    {
        $currency = $amount->currency;
        $total = Money::zero($currency);
        $totalShipping = Money::zero($currency);
        $lineItems = [];
        foreach ($documents as $document) {
            // Aggregate the sales tax from each document
            $calculatedDocument = InvoiceCalculator::calculate($document->currency, $document->items(), $document->discounts(), $document->taxes());

            /** @var LineItem $item */
            foreach ($calculatedDocument->items as $item) {
                // Add shipping line items to the shipping total.
                if ('shipping' == $item['type']) {
                    $totalShipping = $totalShipping->add(Money::fromDecimal($currency, $item['amount']));
                    continue;
                }

                $factor = ceil($item['amount'] / $calculatedDocument->subtotal * 100) / 100;
                $itemDiscount = Money::fromDecimal($document->currency, ($calculatedDocument->totalDiscounts ?? 0) * $factor);
                foreach ($item['discounts'] as $discount) {
                    $itemDiscount = $itemDiscount->add(Money::fromDecimal($document->currency, $discount['amount']));
                }
                $itemTotal = Money::fromDecimal($document->currency, $item['amount'])->subtract($itemDiscount);

                $itemTaxes = Money::fromDecimal($document->currency, ($calculatedDocument->totalTaxes ?? 0) * $factor);
                foreach ($item['taxes'] as $tax) {
                    $itemTaxes = $itemTaxes->add(Money::fromDecimal($document->currency, $tax['amount']));
                }

                $taxPercentage = round($itemTaxes->amount / $itemTotal->amount * 10000);

                $lineItems[] = [
                    "quantity" => $item['quantity'],
                    "amountExcludingTax" => $itemTotal->amount,
                    "taxPercentage" => $taxPercentage,
                    "description" => self::sanitizeAscii($item['name'] ?: 'Unknown'),
                    "id" => $document->number . '-' . $item['name'],
                ];

                $total = $total->add($itemTotal)->add($itemTaxes);
            }
        }


        if (!$totalShipping->isZero()) {
            $lineItems[] = [
                "quantity" => 1,
                "amountExcludingTax" => $totalShipping->amount,
                "taxPercentage" => 0,
                "description" => 'Shipping',
                "id" => 'Shipping',
            ];
        }

        $difference = $amount->subtract($total);
        if (!$difference->isZero() && count($lineItems) > 0) {
            $lineItems[] = [
                "description" =>  'Rounding Adjustment',
                "quantity" => 1,
                "amountExcludingTax" => $difference->amount,
                "taxPercentage" => 0,
                "id" => 'Adjustment',
            ];
        }

        return $lineItems;
    }

    /**
     * Creates a populated bank account value object from
     * an ACH form input.
     *
     * @throws InvalidBankAccountException
     */
    public static function makeAchBankAccount(RoutingNumberLookup $routingNumberLookup, Customer $customer, MerchantAccount $account, array $parameters, bool $vault): BankAccountValueObject
    {
        // Look up the bank name
        $routingNumber = $routingNumberLookup->lookup($parameters['routing_number'] ?: '');

        $bankAccount = new BankAccountValueObject(
            customer: $customer,
            gateway: $account->gateway,
            gatewayId: null,
            merchantAccount: $account,
            chargeable: $vault,
            receiptEmail: $parameters['receipt_email'] ?? null,
            bankName: $routingNumber?->bank_name ?: 'Unknown',
            routingNumber: $parameters['routing_number'] ?? null,
            accountNumber: $parameters['account_number'] ?? null,
            last4: substr($parameters['account_number'] ?? '', -4, 4) ?: '0000',
            currency: 'usd',
            country: 'US',
            accountHolderName: $parameters['account_holder_name'] ?? null,
            accountHolderType: $parameters['account_holder_type'] ?? null,
            type: $parameters['type'] ?? null,
            verified: true,
        );

        // Validate the bank account details
        (new BankAccountValidator())->validate($bankAccount);

        return $bankAccount;
    }

    /**
     * Sanitizes a string to ASCII characters only for Level 3 data.
     */
    private static function sanitizeAscii(?string $unsanitary): string
    {
        return $unsanitary ? trim(preg_replace('/[[:^ascii:]]/', ' ', $unsanitary) ?: 'Unknown') : 'Unknown';
    }

    private static function computeAdditionalDiscountsPerLineItem(ReceivableDocument $d, string $currency): array
    {
        $additionalDiscounts = Money::zero($currency);
        $discountLineItemCount = 0;
        /** @var LineItem $item */
        foreach ($d->items as $item) {
            if ($item->amount < 0) {
                $additionalDiscounts = $additionalDiscounts->add(Money::fromDecimal($currency, $item->amount)->abs());
            } elseif (0 == $item->amount) {
                $discountAmount = Money::zero($currency);
                foreach ($item->discounts as $discount) {
                    $discountAmount = $discountAmount->add(Money::fromDecimal($currency, $discount['amount']));
                }
                $additionalDiscounts = $additionalDiscounts->add($discountAmount);
            } else {
                ++$discountLineItemCount;
            }
        }
        $additionalDiscountPerLineItem = Money::zero($currency);
        $discountAdjustment = Money::zero($currency);
        if ($discountLineItemCount > 1) {
            $discountLineItemCountMoney = Money::fromDecimal($currency, $discountLineItemCount);
            $additionalDiscountPerLineItem = $additionalDiscounts->divide($discountLineItemCountMoney); // divide discount amount between remaining line items
            $discountAdjustment = $additionalDiscounts->subtract($additionalDiscountPerLineItem->multiply($discountLineItemCountMoney)); // calculate adjustment
        }

        return [
            'additionalDiscountsPerLineItem' => $additionalDiscountPerLineItem,
            'discountAdjustment' => $discountAdjustment,
        ];
    }

    private static function getDiscountAmountForCurrentLineItem(string $currency, LineItem $item, array $additionalDiscounts, bool &$appliedDiscountAdjustment): Money
    {
        $discountAmount = Money::zero($currency);
        foreach ($item->discounts as $discount) {
            $discountAmount = $discountAmount->add(Money::fromDecimal($currency, $discount['amount']));
        }
        $discountAmount = $discountAmount->add($additionalDiscounts['additionalDiscountsPerLineItem']);
        if (!$appliedDiscountAdjustment) {
            $discountAmount = $discountAmount->add($additionalDiscounts['discountAdjustment']);
            $appliedDiscountAdjustment = true;
        }

        return $discountAmount;
    }

    /**
     * Gets the commodity code for an industry.
     */
    private static function getCommodityCode(?string $industry): string
    {
        if ($industry && isset(self::COMMODITY_CODES[$industry])) {
            return self::COMMODITY_CODES[$industry];
        }

        return self::COMMODITY_CODES['Other'];
    }

    /**
     * Gets the product code for an item, or generates one if not available.
     */
    private static function getProductCode(?string $sku = null): string
    {
        return $sku ?: RandomString::generate(8, RandomString::CHAR_UPPER);
    }

    /**
     * Generates the ship to for Level 3 data.
     */
    private static function makeShipTo(?ReceivableDocument $firstDocument, Customer $customer, Company $company): array
    {
        $shippingSource = $firstDocument?->ship_to;
        if (!$shippingSource) {
            $shippingSource = $customer;
        }

        if (!$customer->address1) {
            $shippingSource = $company;
        }

        return [
            'object' => 'address',
            'address1' => self::sanitizeAscii((string) $shippingSource->address1),
            'address2' => self::sanitizeAscii((string) $shippingSource->address2),
            'city' => self::sanitizeAscii((string) $shippingSource->city),
            'state' => self::sanitizeAscii((string) $shippingSource->state),
            'postal_code' => self::sanitizeAscii((string) $shippingSource->postal_code),
            'country' => $shippingSource->country ?? $customer->country ?? $company->country ?? 'US',
        ];
    }

    /**
     * Generates the PO number for Level 3 data.
     */
    private static function getPoNumber(?ReceivableDocument $firstDocument): string
    {
        if ($firstDocument) {
            return $firstDocument->purchase_order ?? $firstDocument->number;
        }

        return '1'.RandomString::generate(7, RandomString::CHAR_NUMERIC);
    }

    /**
     * Checks for any scenarios in which the order summary line item must be sent.
     *
     * @param Level3LineItem[] $lineItems
     */
    private static function useOrderSummaryLine(array $lineItems): bool
    {
        // If there are no line items or greater than 100 line items
        // then use an order summary line.
        $numLineItems = count($lineItems);
        if (!$numLineItems || $numLineItems > 100) {
            return true;
        }

        // Level 3 data does not allow for negative amounts in quantity, unit cost, or discount.
        foreach ($lineItems as $lineItem) {
            if ($lineItem->unitCost->isNegative() || $lineItem->quantity < 0 || $lineItem->discount->isNegative()) {
                return true;
            }
        }

        return false;
    }
}
