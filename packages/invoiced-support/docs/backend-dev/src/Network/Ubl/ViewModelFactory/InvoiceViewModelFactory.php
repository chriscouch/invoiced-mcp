<?php

namespace App\Network\Ubl\ViewModelFactory;

use App\Network\Interfaces\UblDocumentViewModelFactoryInterface;
use App\Network\Ubl\UblReader;
use App\Network\Ubl\ViewModel\DocumentViewModel;
use App\Network\Ubl\ViewModel\InvoiceViewModel;
use Carbon\CarbonImmutable;
use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\ImmutableAddressInterface;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;
use SimpleXMLElement;

final class InvoiceViewModelFactory implements UblDocumentViewModelFactoryInterface
{
    public function make(SimpleXMLElement $xml): DocumentViewModel
    {
        $viewModel = new InvoiceViewModel();

        // Bill From / Bill To / Ship To
        $this->parseContacts($xml, $viewModel);

        // Issue Date / Due Date / Payment Terms
        $viewModel->setIssueDate(new CarbonImmutable(UblReader::xpathToString($xml, '/doc:Invoice/cbc:IssueDate')));
        if ($dueDate = UblReader::xpathToString($xml, '/doc:Invoice/cbc:DueDate')) {
            $viewModel->setDueDate(new CarbonImmutable($dueDate));
        }
        $viewModel->setPaymentTerms(UblReader::xpathToString($xml, '/doc:Invoice/cac:PaymentTerms/cbc:Note'));
        $viewModel->setPurchaseOrder(UblReader::xpathToString($xml, '/doc:Invoice/cac:OrderReference/cbc:ID'));

        // Totals
        $this->parseLegalMonetaryTotal($xml, $viewModel);

        // Line Items
        $this->parseLineItems($xml, $viewModel);

        // Notes
        $viewModel->setNotes(UblReader::xpathToString($xml, '/doc:Invoice/cbc:Note'));

        // Payment Methods
        foreach (UblReader::xpath($xml, '/doc:Invoice/cac:PaymentMeans') as $paymentMeans) {
            $code = UblReader::xpathToString($paymentMeans, 'cbc:PaymentMeansCode');
            if ('ZZZ' == $code) {
                $url = (string) UblReader::xpathToString($paymentMeans, 'cbc:InstructionNote');
                if (!str_starts_with($url, 'https://')) {
                    continue;
                }

                $viewModel->addPaymentMethod([
                    'name' => UblReader::xpathToString($paymentMeans, 'cbc:ID'),
                    'url' => $url,
                ]);
            }
        }

        return $viewModel;
    }

    private function parseContacts(SimpleXMLElement $xml, InvoiceViewModel $viewModel): void
    {
        $addressFormatRepository = new AddressFormatRepository();
        $countryRepository = new CountryRepository();
        $subdivisionRepository = new SubdivisionRepository();
        $formatter = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository, ['html' => false]);

        $fromAddress = $this->getPartyAddress($xml, '/doc:Invoice/cac:AccountingSupplierParty/cac:Party');
        $formattedFromAddress = $formatter->format($fromAddress);
        $toAddress = $this->getPartyAddress($xml, '/doc:Invoice/cac:AccountingCustomerParty/cac:Party');
        $formattedToAddress = $formatter->format($toAddress);

        // Exclude country name if the countries are the same
        if ($fromAddress->getCountryCode() == $toAddress->getCountryCode()) {
            $parts = explode("\n", $formattedFromAddress);
            $formattedFromAddress = join("\n", array_slice($parts, 0, -1));
            $parts = explode("\n", $formattedToAddress);
            $formattedToAddress = join("\n", array_slice($parts, 0, -1));
        }
        $viewModel->setBillFrom($formattedFromAddress);
        $viewModel->setBillTo($formattedToAddress);

        // Ship To
        $shipToAddress = $this->getShipToAddress($xml);
        $formattedShipToAddress = $formatter->format($shipToAddress);
        if ($fromAddress->getCountryCode() == $shipToAddress->getCountryCode()) {
            $parts = explode("\n", $formattedShipToAddress);
            $formattedShipToAddress = join("\n", array_slice($parts, 0, -1));
        }
        $viewModel->setShipTo($formattedShipToAddress);
    }

    private function getPartyAddress(SimpleXMLElement $party, string $pathPrefix): ImmutableAddressInterface
    {
        return $this->getAddress(UblReader::xpathToSingle($party, $pathPrefix.'/cac:PostalAddress'))
            ->withOrganization((string) UblReader::xpathToString($party, $pathPrefix.'/cac:PartyName/cbc:Name'));
    }

    private function getShipToAddress(SimpleXMLElement $xml): ImmutableAddressInterface
    {
        return $this->getAddress(UblReader::xpathToSingle($xml, '/doc:Invoice/cac:Delivery/cac:DeliveryLocation/cac:Address'))
            ->withOrganization((string) UblReader::xpathToString($xml, '/doc:Invoice/cac:Delivery/cac:DeliveryParty/cac:PartyName/cbc:Name'));
    }

    private function getAddress(?SimpleXMLElement $xml): ImmutableAddressInterface
    {
        $address = new Address();
        if (!$xml) {
            // TODO: Need country code from organization
            return $address->withCountryCode('US');
        }

        $address1 = [
            UblReader::xpathToString($xml, 'cbc:Postbox').' '.UblReader::xpathToString($xml, 'cbc:StreetName'),
        ];

        foreach (UblReader::xpath($xml, 'cac:AddressLine/cbc:Line') as $addressLine) {
            $address1[] = (string) $addressLine;
        }

        return (new Address())
            ->withAddressLine1(trim(implode("\n", $address1)))
            ->withAddressLine2((string) UblReader::xpathToString($xml, 'cbc:AdditionalStreetName'))
            ->withLocality((string) UblReader::xpathToString($xml, 'cbc:CityName'))
            ->withAdministrativeArea((string) UblReader::xpathToString($xml, 'cbc:CountrySubentityCode'))
            ->withPostalCode((string) UblReader::xpathToString($xml, 'cbc:PostalZone'))
            // TODO: Need country code from organization
            ->withCountryCode((string) (UblReader::xpathToString($xml, 'cac:Country/cbc:IdentificationCode') ?? 'US'));
    }

    private function parseLegalMonetaryTotal(SimpleXMLElement $xml, InvoiceViewModel $viewModel): void
    {
        $currencies = new ISOCurrencies();
        $moneyParser = new DecimalMoneyParser($currencies);
        $subtotal = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount');
        $subtotalCurrency = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount/@currencyID');
        if ($subtotal && $subtotalCurrency) {
            $viewModel->setSubtotal($moneyParser->parse($subtotal, new Currency($subtotalCurrency)));
        }
        $total = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount');
        $totalCurrency = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount/@currencyID');
        if ($total && $totalCurrency) {
            $viewModel->setTotal($moneyParser->parse($total, new Currency($totalCurrency)));
        }
        $totalAllowances = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount');
        $totalAllowancesCurrency = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount/@currencyID');
        if ($totalAllowances && $totalAllowancesCurrency) {
            $viewModel->setTotalAllowances($moneyParser->parse($totalAllowances, new Currency($totalAllowancesCurrency)));
        }
        $totalCharges = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:ChargeTotalAmount');
        $totalChargesCurrency = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:ChargeTotalAmount/@currencyID');
        if ($totalCharges && $totalChargesCurrency) {
            $viewModel->setTotalCharges($moneyParser->parse($totalCharges, new Currency($totalChargesCurrency)));
        }
        $totalTax = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/doc:Invoice/cac:TaxTotal/cbc:TaxAmount');
        $totalTaxCurrency = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/doc:Invoice/cac:TaxTotal/cbc:TaxAmount/@currencyID');
        if ($totalTax && $totalTaxCurrency) {
            $viewModel->setTotalTax($moneyParser->parse($totalTax, new Currency($totalTaxCurrency)));
        }
        $amountPaid = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:PrepaidAmount');
        $amountPaidCurrency = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:PrepaidAmount/@currencyID');
        if ($amountPaid && $amountPaidCurrency) {
            $viewModel->setAmountPaid($moneyParser->parse($amountPaid, new Currency($amountPaidCurrency)));
        }
        $balance = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount');
        $balanceCurrency = UblReader::xpathToString($xml, '/doc:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount/@currencyID');
        if ($balance && $balanceCurrency) {
            $viewModel->setBalance($moneyParser->parse($balance, new Currency($balanceCurrency)));
        }
    }

    private function parseLineItems(SimpleXMLElement $xml, InvoiceViewModel $viewModel): void
    {
        $currencies = new ISOCurrencies();
        $moneyParser = new DecimalMoneyParser($currencies);
        foreach (UblReader::xpath($xml, '/doc:Invoice/cac:InvoiceLine') as $lineItem) {
            $viewModelLine = [
                'name' => UblReader::xpathToString($lineItem, 'cac:Item/cbc:Name'),
                'description' => UblReader::xpathToString($lineItem, 'cac:Item/cbc:Description'),
                'quantity' => UblReader::xpathToString($lineItem, 'cbc:InvoicedQuantity'),
                'unit_cost' => null,
                'total' => null,
            ];

            $unitCost = UblReader::xpathToString($lineItem, 'cac:Price/cbc:PriceAmount');
            $unitCostCurrency = UblReader::xpathToString($lineItem, 'cac:Price/cbc:PriceAmount/@currencyID');
            if ($unitCost && $unitCostCurrency) {
                $viewModelLine['unit_cost'] = $moneyParser->parse($unitCost, new Currency($unitCostCurrency));
            }

            $total = UblReader::xpathToString($lineItem, 'cbc:LineExtensionAmount');
            $totalCurrency = UblReader::xpathToString($lineItem, 'cbc:LineExtensionAmount/@currencyID');
            if ($total && $totalCurrency) {
                $viewModelLine['total'] = $moneyParser->parse($total, new Currency($totalCurrency));
            }

            $viewModel->addLineItem($viewModelLine);
        }
    }
}
