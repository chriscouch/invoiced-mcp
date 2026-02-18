<?php

namespace App\Network\Ubl;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\I18n\AddressFormatter;
use App\Themes\Interfaces\PdfBuilderInterface;
use Carbon\CarbonImmutable;
use CommerceGuys\Addressing\Address;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use SimpleXMLElement;

/**
 * Provides shortcuts for building UBL documents.
 */
final class UblWriter
{
    const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private static DecimalMoneyFormatter $moneyFormatter;

    public static function invoice(): SimpleXMLElement
    {
        return new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="'.self::CAC.'" xmlns:cbc="'.self::CBC.'"></Invoice>');
    }

    public static function quote(): SimpleXMLElement
    {
        return new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Quotation xmlns="urn:oasis:names:specification:ubl:schema:xsd:Quotation-2" xmlns:cac="'.self::CAC.'" xmlns:cbc="'.self::CBC.'"></Quotation>');
    }

    public static function creditNote(): SimpleXMLElement
    {
        return new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" xmlns:cac="'.self::CAC.'" xmlns:cbc="'.self::CBC.'"></CreditNote>');
    }

    public static function statement(): SimpleXMLElement
    {
        return new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Statement xmlns="urn:oasis:names:specification:ubl:schema:xsd:Statement-2" xmlns:cac="'.self::CAC.'" xmlns:cbc="'.self::CBC.'"></Statement>');
    }

    public static function id(SimpleXMLElement $document, string $id): void
    {
        self::cbc($document, 'ID', $id);
    }

    public static function issueDate(SimpleXMLElement $document, string $date): void
    {
        self::cbc($document, 'IssueDate', $date);
    }

    public static function dueDate(SimpleXMLElement $document, string $date): void
    {
        self::cbc($document, 'DueDate', $date);
    }

    public static function customerMemo(SimpleXMLElement $document, string $memo): void
    {
        self::cbc($document, 'Note', $memo);
    }

    public static function documentCurrency(SimpleXMLElement $document, string $currency): void
    {
        self::cbc($document, 'DocumentCurrencyCode', $currency);
    }

    public static function pricingCurrency(SimpleXMLElement $document, string $currency): void
    {
        self::cbc($document, 'PricingCurrencyCode', $currency);
    }

    public static function orderReference(SimpleXMLElement $document, string $reference): void
    {
        $orderReference = self::cac($document, 'OrderReference');
        self::cbc($orderReference, 'ID', $reference);
    }

    public static function pdf(SimpleXMLElement $document, PdfBuilderInterface $pdf, string $locale): SimpleXMLElement
    {
        $docNumber = (string) $document->children('cbc', true)->ID;
        $docType = $document->getName();

        $additionalDocument = self::cac($document, 'AdditionalDocumentReference');
        self::cbc($additionalDocument, 'ID', $docNumber);
        if ('Invoice' == $docType) {
            self::cbc($additionalDocument, 'DocumentTypeCode', '130');
        }
        $attachment = self::cac($additionalDocument, 'Attachment');
        $binary = self::cbc($attachment, 'EmbeddedDocumentBinaryObject', base64_encode($pdf->build($locale)));
        $binary->addAttribute('mimeCode', 'application/pdf');
        $binary->addAttribute('filename', $pdf->getFilename($locale));

        return $additionalDocument;
    }

    public static function supplier(SimpleXMLElement $document, Company $company, string $elName = 'AccountingSupplierParty'): SimpleXMLElement
    {
        $supplier = self::cac($document, $elName);
        $party = self::cac($supplier, 'Party');

        // Website
        if ($website = $company->website) {
            self::cbc($party, 'WebsiteURI', $website);
        }

        // Username
        $partyIdentification = self::cac($party, 'PartyIdentification');
        self::cbc($partyIdentification, 'ID', $company->username);

        // Company Name
        $partyName = self::cac($party, 'PartyName');
        self::cbc($partyName, 'Name', $company->name);

        // Address
        $formatter = new AddressFormatter();
        self::address($party, $formatter->setFrom($company)->buildAddress());

        // Contact
        $contact = self::cac($party, 'Contact');
        self::cbc($contact, 'Name', $company->name);
        if ($phone = $company->phone) {
            self::cbc($contact, 'Telephone', $phone);
        }
        if ($email = $company->email) {
            self::cbc($contact, 'ElectronicMail', $email);
        }

        return $supplier;
    }

    public static function address(SimpleXMLElement $document, Address $address, string $elName = 'PostalAddress'): SimpleXMLElement
    {
        $addressXml = self::cac($document, $elName);
        if ($city = $address->getLocality()) {
            self::cbc($addressXml, 'CityName', $city);
        }
        if ($postalCode = $address->getPostalCode()) {
            self::cbc($addressXml, 'PostalZone', $postalCode);
        }
        if ($state = $address->getAdministrativeArea()) {
            self::cbc($addressXml, 'CountrySubentity', $state);
        }
        if ($address1 = $address->getAddressLine1()) {
            $addressLine = self::cac($addressXml, 'AddressLine');
            self::cbc($addressLine, 'Line', $address1);
        }
        if ($address2 = $address->getAddressLine2()) {
            $addressLine = self::cac($addressXml, 'AddressLine');
            self::cbc($addressLine, 'Line', $address2);
        }
        $country = self::cac($addressXml, 'Country');
        self::cbc($country, 'IdentificationCode', $address->getCountryCode());

        return $addressXml;
    }

    public static function customer(SimpleXMLElement $document, Customer $customerModel, string $elName = 'AccountingCustomerParty'): SimpleXMLElement
    {
        $customer = self::cac($document, $elName);
        self::cbc($customer, 'SupplierAssignedAccountID', $customerModel->number);
        $party = self::cac($customer, 'Party');

        // Username
        if ($username = $customerModel->network_connection?->customer->username) {
            $partyIdentification = self::cac($party, 'PartyIdentification');
            self::cbc($partyIdentification, 'ID', $username);
        }

        // Customer Name
        $partyName = self::cac($party, 'PartyName');
        self::cbc($partyName, 'Name', $customerModel->name);

        // Address
        $formatter = new AddressFormatter();
        self::address($party, $formatter->setFrom($customerModel)->buildAddress());

        return $customer;
    }

    public static function paymentLink(SimpleXMLElement $document, string $name, string $url): SimpleXMLElement
    {
        $paymentMeans = self::cac($document, 'PaymentMeans');
        self::cbc($paymentMeans, 'ID', $name);
        self::cbc($paymentMeans, 'PaymentMeansCode', 'ZZZ')
            ->addAttribute('listID', 'UN/ECE 4461');
        self::cbc($paymentMeans, 'InstructionNote', htmlspecialchars($url));

        return $paymentMeans;
    }

    public static function paymentTerms(SimpleXMLElement $document, string $terms): SimpleXMLElement
    {
        $paymentTerms = self::cac($document, 'PaymentTerms');
        self::cbc($paymentTerms, 'Note', $terms);

        return $paymentTerms;
    }

    public static function invoiceLineItem(SimpleXMLElement $document, string $id, string $name, ?string $description, string $quantity, ?Money $unitCost, Money $amount): SimpleXMLElement
    {
        $moneyFormatter = self::getMoneyFormatter();
        $type = $document->getName().'Line';
        $quantityType = 'InvoicedQuantity';
        if ('CreditNoteLine' == $type) {
            $quantityType = 'CreditedQuantity';
        }

        $lineItem = self::cac($document, $type);
        self::cbc($lineItem, 'ID', $id);
        self::cbc($lineItem, $quantityType, $quantity);
        $lineTotal = self::cbc($lineItem, 'LineExtensionAmount', $moneyFormatter->format($amount));
        $lineTotal->addAttribute('currencyID', $amount->getCurrency()->getCode());

        $item = self::cac($lineItem, 'Item');
        if ($description) {
            self::cbc($item, 'Description', $description);
        }
        self::cbc($item, 'Name', $name ?: 'Unknown');
        // TODO: Include customer-visible custom fields

        if ($unitCost) {
            $price = self::cac($lineItem, 'Price');
            $priceAmount = self::cbc($price, 'PriceAmount', $moneyFormatter->format($unitCost));
            $priceAmount->addAttribute('currencyID', $unitCost->getCurrency()->getCode());
            self::cbc($price, 'BaseQuantity', '1');
        }

        return $lineItem;
    }

    public static function quoteLineItem(SimpleXMLElement $document, string $id, string $name, ?string $description, string $quantity, ?Money $unitCost, Money $amount): SimpleXMLElement
    {
        $moneyFormatter = self::getMoneyFormatter();
        $lineItem = self::cac($document, 'QuotationLine');
        self::cbc($lineItem, 'ID', $id);
        self::cbc($lineItem, 'Note', $name);

        $subLineItem = self::cac($lineItem, 'LineItem');
        self::cbc($subLineItem, 'ID', $id);
        self::cbc($subLineItem, 'Quantity', $quantity);
        $lineTotal = self::cbc($subLineItem, 'LineExtensionAmount', $moneyFormatter->format($amount));
        $lineTotal->addAttribute('currencyID', $amount->getCurrency()->getCode());

        if ($unitCost) {
            $price = self::cac($subLineItem, 'Price');
            $priceAmount = self::cbc($price, 'PriceAmount', $moneyFormatter->format($unitCost));
            $priceAmount->addAttribute('currencyID', $unitCost->getCurrency()->getCode());
            self::cbc($price, 'BaseQuantity', '1');
        }

        $item = self::cac($subLineItem, 'Item');
        if ($description) {
            self::cbc($item, 'Description', $description);
        }
        self::cbc($item, 'Name', $name ?: 'Unknown');
        // TODO: Include customer-visible custom fields

        return $lineItem;
    }

    public static function taxAmount(SimpleXMLElement $taxTotal, Money $amount): SimpleXMLElement
    {
        $moneyFormatter = self::getMoneyFormatter();
        $taxAmount = self::cbc($taxTotal, 'TaxAmount', $moneyFormatter->format($amount));
        $taxAmount->addAttribute('currencyID', $amount->getCurrency()->getCode());

        return $taxAmount;
    }

    public static function total(SimpleXMLElement $document, Money $subtotal, Money $total, Money $totalDiscounts, Money $paidAmount, Money $balance): SimpleXMLElement
    {
        $moneyFormatter = self::getMoneyFormatter();
        $legalMonetaryTotal = self::cac($document, 'LegalMonetaryTotal');
        $lineExtAmount = self::cbc($legalMonetaryTotal, 'LineExtensionAmount', $moneyFormatter->format($subtotal));
        $lineExtAmount->addAttribute('currencyID', $subtotal->getCurrency()->getCode());
        $taxExclAmount = self::cbc($legalMonetaryTotal, 'TaxExclusiveAmount', $moneyFormatter->format($subtotal));
        $taxExclAmount->addAttribute('currencyID', $subtotal->getCurrency()->getCode());
        $taxInclAmount = self::cbc($legalMonetaryTotal, 'TaxInclusiveAmount', $moneyFormatter->format($total));
        $taxInclAmount->addAttribute('currencyID', $total->getCurrency()->getCode());
        $allowanceAmount = self::cbc($legalMonetaryTotal, 'AllowanceTotalAmount', $moneyFormatter->format($totalDiscounts));
        $allowanceAmount->addAttribute('currencyID', $totalDiscounts->getCurrency()->getCode());
        $chargeAmount = self::cbc($legalMonetaryTotal, 'ChargeTotalAmount', '0');
        $chargeAmount->addAttribute('currencyID', $subtotal->getCurrency()->getCode());
        $prepaidAmount = self::cbc($legalMonetaryTotal, 'PrepaidAmount', $moneyFormatter->format($paidAmount));
        $prepaidAmount->addAttribute('currencyID', $paidAmount->getCurrency()->getCode());
        $roundingAmount = self::cbc($legalMonetaryTotal, 'PayableRoundingAmount', '0');
        $roundingAmount->addAttribute('currencyID', $subtotal->getCurrency()->getCode());
        $payableAmount = self::cbc($legalMonetaryTotal, 'PayableAmount', $moneyFormatter->format($balance));
        $payableAmount->addAttribute('currencyID', $balance->getCurrency()->getCode());

        return $legalMonetaryTotal;
    }

    public static function quotedTotal(SimpleXMLElement $document, Money $subtotal, Money $total, Money $totalDiscounts, Money $paidAmount, Money $balance): SimpleXMLElement
    {
        $moneyFormatter = self::getMoneyFormatter();
        $quotedMonetaryTotal = self::cac($document, 'QuotedMonetaryTotal');
        $lineExtAmount = self::cbc($quotedMonetaryTotal, 'LineExtensionAmount', $moneyFormatter->format($subtotal));
        $lineExtAmount->addAttribute('currencyID', $subtotal->getCurrency()->getCode());
        $taxExclAmount = self::cbc($quotedMonetaryTotal, 'TaxExclusiveAmount', $moneyFormatter->format($subtotal));
        $taxExclAmount->addAttribute('currencyID', $subtotal->getCurrency()->getCode());
        $taxInclAmount = self::cbc($quotedMonetaryTotal, 'TaxInclusiveAmount', $moneyFormatter->format($total));
        $taxInclAmount->addAttribute('currencyID', $total->getCurrency()->getCode());
        $allowanceAmount = self::cbc($quotedMonetaryTotal, 'AllowanceTotalAmount', $moneyFormatter->format($totalDiscounts));
        $allowanceAmount->addAttribute('currencyID', $totalDiscounts->getCurrency()->getCode());
        $chargeAmount = self::cbc($quotedMonetaryTotal, 'ChargeTotalAmount', '0');
        $chargeAmount->addAttribute('currencyID', $subtotal->getCurrency()->getCode());
        $prepaidAmount = self::cbc($quotedMonetaryTotal, 'PrepaidAmount', $moneyFormatter->format($paidAmount));
        $prepaidAmount->addAttribute('currencyID', $paidAmount->getCurrency()->getCode());
        $roundingAmount = self::cbc($quotedMonetaryTotal, 'PayableRoundingAmount', '0');
        $roundingAmount->addAttribute('currencyID', $subtotal->getCurrency()->getCode());
        $payableAmount = self::cbc($quotedMonetaryTotal, 'PayableAmount', $moneyFormatter->format($balance));
        $payableAmount->addAttribute('currencyID', $balance->getCurrency()->getCode());

        return $quotedMonetaryTotal;
    }

    public static function statementPeriod(SimpleXMLElement $document, ?CarbonImmutable $start, CarbonImmutable $end): SimpleXMLElement
    {
        $statementPeriod = self::cac($document, 'StatementPeriod');
        if ($start) {
            self::cbc($statementPeriod, 'StartDate', $start->toDateString());
        }
        self::cbc($statementPeriod, 'EndDate', $end->toDateString());

        return $statementPeriod;
    }

    public static function statementTotal(SimpleXMLElement $document, Money $total, string $type): SimpleXMLElement
    {
        $moneyFormatter = self::getMoneyFormatter();
        $totalEl = self::cbc($document, $type, $moneyFormatter->format($total));
        $totalEl->addAttribute('currencyID', $total->getCurrency()->getCode());

        return $totalEl;
    }

    public static function statementLineItem(SimpleXMLElement $document, string $id, Money $amount, string $note, bool $balanceForward = false): SimpleXMLElement
    {
        $moneyFormatter = self::getMoneyFormatter();
        $lineItem = self::cac($document, 'StatementLine');
        self::cbc($lineItem, 'ID', $id);
        self::cbc($lineItem, 'Note', $note);
        self::cbc($lineItem, 'BalanceBroughtForwardIndicator', $balanceForward ? 'true' : 'false');

        $debit = $amount->isPositive() ? $amount : new Money(0, $amount->getCurrency());
        $debitAmount = self::cbc($lineItem, 'DebitLineAmount', $moneyFormatter->format($debit));
        $debitAmount->addAttribute('currencyID', $debit->getCurrency()->getCode());

        $credit = $amount->isNegative() ? $amount->absolute() : new Money(0, $amount->getCurrency());
        $creditAmount = self::cbc($lineItem, 'CreditLineAmount', $moneyFormatter->format($credit));
        $creditAmount->addAttribute('currencyID', $credit->getCurrency()->getCode());

        $balanceAmount = self::cbc($lineItem, 'BalanceAmount', $moneyFormatter->format($amount));
        $balanceAmount->addAttribute('currencyID', $amount->getCurrency()->getCode());

        return $lineItem;
    }

    public static function cac(SimpleXMLElement $parent, string $name): SimpleXMLElement
    {
        return $parent->addChild('cac:'.$name, '', self::CAC);
    }

    public static function cbc(SimpleXMLElement $parent, string $name, string $value): SimpleXMLElement
    {
        return $parent->addChild('cbc:'.$name, htmlentities(trim($value), ENT_XML1), self::CBC);
    }

    private static function getMoneyFormatter(): DecimalMoneyFormatter
    {
        if (!isset(self::$moneyFormatter)) {
            self::$moneyFormatter = new DecimalMoneyFormatter(new ISOCurrencies());
        }

        return self::$moneyFormatter;
    }
}
