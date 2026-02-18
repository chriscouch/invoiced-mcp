<?php

namespace App\SalesTax\Calculator;

use App\AccountsReceivable\Models\Item;
use App\Companies\Models\Company;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Avalara\AvalaraAccount;
use App\Integrations\Avalara\AvalaraApi;
use App\SalesTax\Exception\TaxCalculationException;
use App\SalesTax\Interfaces\TaxCalculatorInterface;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\SalesTax\ValueObjects\SalesTaxInvoiceItem;
use Avalara\AddressesModel;
use Avalara\AddressLocationInfo;
use Avalara\DocumentType;
use Avalara\LineItemModel;
use Avalara\TransactionBuilder;
use Avalara\VoidTransactionModel;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * Performs sales tax calculation through the Avalara AvaTax
 * service. When this class is used it assumes that Avalara
 * is currently connected.
 */
class AvalaraTaxCalculator implements TaxCalculatorInterface
{
    use LoggerAwareTrait;

    const TAX_CODE = 'AVATAX';

    private const PREVIEW_CUSTOMER_NUMBER = '0000';

    public function __construct(private AvalaraApi $client)
    {
    }

    public function assess(SalesTaxInvoice $salesTaxInvoice): array
    {
        // skip calling Avalara if there are no line items available
        if (0 == count($salesTaxInvoice->getLineItems())) {
            return [];
        }

        $customer = $salesTaxInvoice->getCustomer();
        $company = $customer->tenant();
        $account = $this->getAvalaraAccount($company);

        // do not call Avalara if the integration is disabled
        if (AvalaraAccount::COMMIT_MODE_DISABLED == $account->commit_mode) {
            return [];
        }

        // create a new Avalara transaction
        $transaction = $this->buildAvalaraTransaction($salesTaxInvoice, $account);

        // create the transaction
        try {
            $result = $transaction->createOrAdjust();
        } catch (ClientException $e) {
            $this->handleClientException($e, 'assessment');

            return [];
        } catch (Throwable $e) {
            if (isset($this->logger)) {
                $this->logger->error('An error occurred when communicating with Avalara', ['exception' => $e]);
            }

            throw new TaxCalculationException('An error occurred when communicating with Avalara.', $e->getCode(), $e);
        }

        // translate the response into an applied tax rate
        // NOTE: any calculated taxes should be in cents, not in a decimal amount
        $taxAmount = Money::fromDecimal($salesTaxInvoice->getCurrency(), $result->totalTax);

        return [
            [
                'tax_rate' => self::TAX_CODE,
                '_calculated' => true,
                'amount' => $salesTaxInvoice->isReturn() ? -$taxAmount->amount : $taxAmount->amount,
            ],
        ];
    }

    public function adjust(SalesTaxInvoice $salesTaxInvoice): array
    {
        // The assess() method will perform an adjustment if
        // the transaction already exists on Avalara.
        return $this->assess($salesTaxInvoice);
    }

    public function void(SalesTaxInvoice $salesTaxInvoice): void
    {
        // Sales orders do not need to be voided
        if ($salesTaxInvoice->isPreview()) {
            return;
        }

        // create a new Avalara transaction
        $account = $this->getAvalaraAccount($salesTaxInvoice->getCustomer()->tenant());
        $client = $this->client->getClientForAccount($account);

        $documentType = (string) $this->getDocumentType($salesTaxInvoice);
        $transactionCode = (string) $salesTaxInvoice->getNumber();

        $transaction = new VoidTransactionModel();
        $transaction->code = 'DocVoided';

        // void the transaction
        try {
            $client->voidTransaction($account->company_code, $transactionCode, $documentType, null, $transaction);
        } catch (ClientException $e) {
            $this->handleClientException($e, 'void');
        } catch (Throwable $e) {
            if (isset($this->logger)) {
                $this->logger->error('An error occurred when communicating with Avalara', ['exception' => $e]);
            }

            throw new TaxCalculationException('An error occurred when communicating with Avalara.', $e->getCode(), $e);
        }
    }

    /**
     * Gets the Avalara account for a given company.
     */
    private function getAvalaraAccount(Company $company): AvalaraAccount
    {
        return AvalaraAccount::queryWithTenant($company)->one();
    }

    /**
     * Gets the Avalara document type for an Invoiced sales tax invoice.
     */
    private function getDocumentType(SalesTaxInvoice $salesTaxInvoice): int
    {
        if ($salesTaxInvoice->isReturn()) {
            return $salesTaxInvoice->isPreview() ? DocumentType::C_RETURNORDER : DocumentType::C_RETURNINVOICE;
        }

        return $salesTaxInvoice->isPreview() ? DocumentType::C_SALESORDER : DocumentType::C_SALESINVOICE;
    }

    /**
     * Builds an Avalara transaction given an Invoiced sales tax invoice.
     *
     * @throws Throwable
     */
    private function buildAvalaraTransaction(SalesTaxInvoice $salesTaxInvoice, AvalaraAccount $account): TransactionBuilder
    {
        $documentType = (string) $this->getDocumentType($salesTaxInvoice);
        $customer = $salesTaxInvoice->getCustomer();
        $company = $customer->tenant();

        // Set the transaction date
        $company->useTimezone();
        if ($date = $salesTaxInvoice->getDate()) {
            $date = date('Y-m-d', $date);
        } else {
            $date = date('Y-m-d');
        }

        $client = $this->client->getClientForAccount($account);
        $customerCode = $customer->number ?? self::PREVIEW_CUSTOMER_NUMBER;
        $transaction = new TransactionBuilder($client, $account->company_code, $documentType, $customerCode, $date);

        // determine whether the transaction should be committed
        // only invoices support being committed
        if (!$salesTaxInvoice->isPreview() && AvalaraAccount::COMMIT_MODE_COMMITTED == $account->commit_mode) {
            $transaction->withCommit();
        }

        // add tax exemption information
        if ($entityUseCode = $customer->avalara_entity_use_code) {
            $transaction->withEntityUseCode($entityUseCode);
        }

        if ($exemptionNumber = $customer->avalara_exemption_number) {
            $transaction->withExemptionNo($exemptionNumber);
        }

        // set the tax calculation date
        // This can be different from the issue date in the
        // event of a return. Tax should be calculated in that
        // scenario according to the original transaction date,
        // and not when the return happened.
        if ($taxDate = $salesTaxInvoice->getTaxDate()) {
            $taxDate = date('Y-m-d', $taxDate);
            $transaction->withTaxOverride('TaxDate', 'Refund', null, $taxDate); /* @phpstan-ignore-line */
        }

        // build the 'ShipFrom' address
        // this comes from the company's billing address
        $transaction->withAddress('ShipFrom', $company->address1, $company->address2, null, $company->city, $company->state, $company->postal_code, $company->country);

        // build the 'ShipTo' address
        // this comes from the shipping/billing address on the invoice (depending on what was available)
        $address = $salesTaxInvoice->getAddress();
        $transaction->withAddress('ShipTo', $address->getAddressLine1(), $address->getAddressLine2(), null, $address->getLocality(), $address->getAdministrativeArea(), $address->getPostalCode(), $address->getCountryCode());

        // currency code
        $currency = $salesTaxInvoice->getCurrency();
        $transaction->withCurrencyCode(strtoupper($currency));

        // document code
        if ($number = $salesTaxInvoice->getNumber()) {
            $transaction->withTransactionCode($number);
        }

        // vat registration number
        if ($taxId = $customer->tax_id) {
            $transaction->withBusinessIdentificationNo($taxId);
        }

        // build the transaction lines
        $moneyFormatter = MoneyFormatter::get();
        $lineNumber = 1;
        foreach ($salesTaxInvoice->getLineItems() as $lineItem) {
            $avalaraLineItem = $this->buildAvalaraLineItem($moneyFormatter, $currency, $salesTaxInvoice, $lineItem);
            $avalaraLineItem->number = (string) $lineNumber;
            ++$lineNumber;
            $transaction->withLineItem($avalaraLineItem);
        }

        // add discounts
        if ($discounts = $salesTaxInvoice->getDiscounts()) {
            $discountAmount = $moneyFormatter->denormalizeFromZeroDecimal($currency, $discounts);

            // return invoices should have negative values
            if ($salesTaxInvoice->isReturn()) {
                $discountAmount = -$discountAmount;
            }

            $transaction->withDiscountAmount($discountAmount);
        }

        return $transaction;
    }

    private function buildAvalaraLineItem(MoneyFormatter $moneyFormatter, string $currency, SalesTaxInvoice $salesTaxInvoice, SalesTaxInvoiceItem $lineItem): LineItemModel
    {
        $lineAmount = $moneyFormatter->denormalizeFromZeroDecimal($currency, $lineItem->getAmount());

        // return invoices should have negative values
        if ($salesTaxInvoice->isReturn()) {
            $lineAmount = -$lineAmount;
        }

        // look up the tax code
        $itemCode = substr((string) $lineItem->getItemCode(), 0, 50);
        $taxCode = '';
        $locationCode = null;
        if ($item = Item::getCurrent($itemCode)) {
            $taxCode = $item->avalara_tax_code;
            $locationCode = $item->avalara_location_code;
        }

        $avalaraLineItem = new LineItemModel();
        $avalaraLineItem->amount = $lineAmount;
        $avalaraLineItem->quantity = $lineItem->getQuantity();
        $avalaraLineItem->taxCode = (string) $taxCode;
        $avalaraLineItem->itemCode = $itemCode;
        $avalaraLineItem->description = $lineItem->getDescription();
        $avalaraLineItem->discounted = $lineItem->isDiscountable();

        // Add a line item address when the line item contains a location code
        if ($locationCode) {
            $shipFrom = new AddressLocationInfo();
            $shipFrom->locationCode = $locationCode;
            $addresses = new AddressesModel();
            $addresses->shipFrom = $shipFrom;
            $avalaraLineItem->addresses = $addresses;
        }

        return $avalaraLineItem;
    }

    /**
     * Handles an Avalara client exception by converting it into a sales tax exception if needed.
     *
     * @param string $operation name of the operation, i.e. void, assessment
     *
     * @throws TaxCalculationException
     */
    private function handleClientException(ClientException $e, string $operation): void
    {
        // The data structure of the result can be found here:
        //   https://developer.avalara.com/api-reference/avatax/rest/v2/models/ErrorDetail/
        $json = $e->getResponse()->getBody();
        $result = json_decode($json);

        // If this is a void then we ignore the document not found error.
        if ('void' == $operation && 'EntityNotFoundError' == $result->error->code) {
            return;
        }

        // If this is a void then we ignore the transaction already canceled error.
        if ('void' == $operation && 'TransactionAlreadyCancelled' == $result->error->code) {
            return;
        }

        throw new TaxCalculationException('Sales tax '.$operation.' failed: '.$result->error->message);
    }
}
