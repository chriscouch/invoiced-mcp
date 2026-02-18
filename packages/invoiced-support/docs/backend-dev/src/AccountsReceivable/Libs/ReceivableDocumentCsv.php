<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Models\ShippingDetail;
use App\Companies\Models\Company;
use App\Core\Csv\CsvWriter;
use App\Core\Csv\Interfaces\CsvBuilderInterface;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Libs\CustomFieldRepository;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Used to generate the CSV file for a document invoice that
 * can be business-facing or customer-facing.
 */
abstract class ReceivableDocumentCsv implements CsvBuilderInterface
{
    public function __construct(
        protected ReceivableDocument $document,
        private bool $forCustomer,
        protected TranslatorInterface $translator,
    ) {
        $this->translator->setLocale($this->document->customer()->getLocale());
    }

    //
    // CsvBuilderInterface
    //

    public function build(string $locale): string
    {
        $csv = fopen('php://output', 'w');
        if (!$csv) {
            throw new RuntimeException('Could not write to output');
        }

        ob_start();
        foreach ($this->buildLines() as $row) {
            CsvWriter::write($csv, $row);
        }
        fclose($csv);

        return (string) ob_get_clean();
    }

    //
    // Helpers
    //

    /**
     * Creates the CSV lines for the document.
     */
    public function buildLines(): array
    {
        $company = $this->document->tenant();
        $info = $this->document->toArray();

        // build the document header
        $lines = $this->buildDocumentHeader($this->forCustomer, $info, $company);

        // build the line items
        $lines[] = [' '];
        $lines = array_merge($lines, $this->buildLineItems($info, $company));

        // build the subtotal items
        $lines = array_merge($lines, $this->buildSubtotalItems($info));

        return $lines;
    }

    /**
     * @param bool $forCustomer
     */
    private function buildDocumentHeader($forCustomer, array $info, Company $company): array
    {
        $lines = [];
        $repository = CustomFieldRepository::get($company);
        $typeName = $this->document->object;
        $documentCustomFields = $repository->getFieldsForObject($typeName, true);

        if ($this->document instanceof CreditNote) {
            $shipTo = null;
        } else {
            $shipTo = ShippingDetail::where($typeName.'_id', $this->document->id())->oneOrNull();
        }

        $lines[] = $this->buildDocumentColumns($forCustomer, $documentCustomFields);

        // when building the CSV for the customer then
        // the business contact information is included,
        // otherwise the customer contact information is included
        $company = $this->document->tenant();
        $contact = ($forCustomer) ?
            $company :
            $this->document->customer();

        $lines[] = $this->buildSummaryLine($contact, $info, $documentCustomFields);

        // add ship to information
        if ($shipTo) {
            $lines[] = [' '];
            $lines[] = [
                'ship_to.name',
                'ship_to.address1',
                'ship_to.address2',
                'ship_to.city',
                'ship_to.state',
                'ship_to.postal_code',
                'ship_to.country',
            ];

            $lines[] = [
                $shipTo->name,
                $shipTo->address1,
                $shipTo->address2,
                $shipTo->city,
                $shipTo->state,
                $shipTo->postal_code,
                $shipTo->country,
            ];
        }

        return $lines;
    }

    protected function buildDocumentColumns(bool $forCustomer, array $documentCustomFields): array
    {
        $result = [
            ($forCustomer) ? 'from' : 'customer',
            'email',
            'address_1',
            'address_2',
            'city',
            'state',
            'postal_code',
            'country',
            'number',
            'date',
            'currency',
            'total',
        ];

        foreach ($documentCustomFields as $customField) {
            $result[] = $customField->id;
        }

        return $result;
    }

    protected function buildSummaryLine(Company|Customer $contact, array $info, array $documentCustomFields): array
    {
        $summaryLine = [
            $contact->name,
            $contact->email,
            $contact->address1,
            $contact->address2,
            $contact->city,
            $contact->state,
            $contact->postal_code,
            $contact->country,
            $info['number'],
            date('Y-m-d', $info['date']),
            $info['currency'],
            round($info['total'], 2),
        ];

        foreach ($documentCustomFields as $customField) {
            $k = $customField->id;
            if (property_exists($info['metadata'], $k)) {
                $summaryLine[] = $info['metadata']->$k;
            } else {
                $summaryLine[] = null;
            }
        }

        return $summaryLine;
    }

    private function buildLineItems(array $info, Company $company): array
    {
        $lines = [
            [
                'item',
                'description',
                'quantity',
                'unit_cost',
                'line_total',
                'discount',
                'tax',
            ],
        ];

        $repository = CustomFieldRepository::get($company);
        $lineItemCustomFields = $repository->getFieldsForObject(ObjectType::LineItem->typeName(), true);
        foreach ($lineItemCustomFields as $customField) {
            $lines[0][] = $customField->id;
        }

        $unitCostPrecision = $company->accounts_receivable_settings->unit_cost_precision;
        if (null === $unitCostPrecision) {
            $unitCostPrecision = 2;
        }

        foreach ($info['items'] as $item) {
            // add in item-level discounts/taxes
            $discounts = new Money($info['currency'], 0);
            foreach ($item['discounts'] as $discount) {
                $discountAmount = Money::fromDecimal($info['currency'], $discount['amount']);
                $discounts = $discounts->add($discountAmount);
            }

            $taxes = new Money($info['currency'], 0);
            foreach ($item['taxes'] as $tax) {
                $taxAmount = Money::fromDecimal($info['currency'], $tax['amount']);
                $taxes = $taxes->add($taxAmount);
            }

            $lines[] = $this->buildLineItemLine($item, $discounts, $taxes, $unitCostPrecision, $lineItemCustomFields);
        }

        return $lines;
    }

    private function buildLineItemLine(array $item, Money $discounts, Money $taxes, int $unitCostPrecision, array $lineItemCustomFields): array
    {
        $result = [
            $item['name'],
            $item['description'],
            $item['quantity'],
            round($item['unit_cost'], $unitCostPrecision),
            round($item['amount'], 2),
            $discounts->isPositive() ? $discounts->toDecimal() : '',
            $taxes->isPositive() ? $taxes->toDecimal() : '',
        ];

        foreach ($lineItemCustomFields as $customField) {
            $k = $customField->id;
            if (property_exists($item['metadata'], $k)) {
                $result[] = $item['metadata']->$k;
            } else {
                $result[] = null;
            }
        }

        return $result;
    }

    private function buildSubtotalItems(array $info): array
    {
        $lines = [];

        // build a line for each subtotal discount
        foreach ($info['discounts'] as $discount) {
            $amount = Money::fromDecimal($info['currency'], $discount['amount']);
            $lines[] = [
                $discount['coupon'] ? $discount['coupon']['name'] : 'Discount',
                '',
                '',
                '',
                '',
                $amount->toDecimal(),
                '',
            ];
        }

        // build a line for each subtotal tax
        foreach ($info['taxes'] as $tax) {
            $amount = Money::fromDecimal($info['currency'], $tax['amount']);
            $lines[] = [
                $tax['tax_rate'] ? $tax['tax_rate']['name'] : 'Sales Tax',
                '',
                '',
                '',
                '',
                '',
                $amount->toDecimal(),
            ];
        }

        return $lines;
    }
}
