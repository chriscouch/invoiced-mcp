<?php

namespace App\Integrations\Xero\Libs;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use Carbon\CarbonImmutable;

/**
 * Helps map data imported from Xero.
 */
class XeroMapper
{
    /**
     * Parses Xero's messed up JSON unix timestamp date format.
     *
     * Examples:
     * /Date(1439434356790)/
     * /Date(1419937200000+0000)/
     *
     * @throws TransformException
     */
    public function parseUnixDate(string $date, bool $endOfDay = false): CarbonImmutable
    {
        if (preg_match('/\/Date\(([\d]+)\+?[\d]*\)\//', $date, $matches)) {
            $timestamp = (int) $matches[1];
            $dateTime = CarbonImmutable::createFromTimestamp($timestamp / 1000);
            $hour = $endOfDay ? 18 : 6;

            return $dateTime->setTime($hour, 0, 0);
        }

        throw new TransformException('Could not parse date: '.$date);
    }

    public function buildLineItems(string $currency, array $xeroLines): array
    {
        $items = [];
        $discount = new Money($currency, 0);
        foreach ($xeroLines as $xeroLine) {
            if (isset($xeroLine->Item->Name)) {
                $lineItemName = $xeroLine->Item->Name;
                $lineItemDescription = $xeroLine->Description;
            } else {
                $lineItemName = $xeroLine->Description;
                $lineItemDescription = null;
            }

            // We have a 255 character limit on the line item name.
            // Xero line item descriptions can be longer. If the Xero
            // description is longer then it should overflow into our
            // description field.
            if (strlen($lineItemName) > 255) {
                $original = $lineItemName;
                $lineItemName = substr($original, 0, 255);
                $lineItemDescription = trim(substr($original, 255).' '.$lineItemDescription);
            }

            $item = [
                'name' => $lineItemName,
                'description' => $lineItemDescription,
                'quantity' => (float) ($xeroLine->Quantity ?? 1),
                'unit_cost' => (float) ($xeroLine->UnitAmount ?? 0),
                'metadata' => [],
            ];

            // We calculate the discount at the line item level
            // because of the way that discounts are represented with
            // tax inclusive invoices.
            $discountRate = (float) ($xeroLine->DiscountRate ?? 0);
            if (0 != $discountRate) {
                $netAmount = Money::fromDecimal($currency, (float) ($xeroLine->LineAmount ?? 0));
                $grossAmount = Money::fromDecimal($currency, $item['quantity'] * $item['unit_cost']);
                $discount = $discount->add($grossAmount->subtract($netAmount));
            }

            // NOTE we can ignore tax because that is factored
            // in to the TotalTax property on the invoice

            // tracking categories
            foreach ($xeroLine->Tracking ?? [] as $trackingCategory) {
                $item['metadata'][$trackingCategory->Name] = $trackingCategory->Option;
            }

            $items[] = $item;
        }

        // Discount and Tax
        $discount = $discount->toDecimal();

        return [$discount, $items];
    }

    public function buildPaymentSplit(AccountingJsonRecord $xeroPayment): array
    {
        $invoice = [
            'accounting_id' => $xeroPayment->document->Invoice->InvoiceID,
        ];
        if (isset($xeroPayment->document->Invoice->InvoiceNumber)) {
            $invoice['number'] = $xeroPayment->document->Invoice->InvoiceNumber;
        }

        return [
            'amount' => (float) $xeroPayment->document->Amount,
            'type' => 'invoice',
            'invoice' => $invoice,
        ];
    }
}
