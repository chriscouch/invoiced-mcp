<?php

namespace App\AccountsReceivable\ClientView;

use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\AccountsReceivable\ValueObjects\CalculatedInvoice;
use App\Companies\Models\Company;
use App\Core\Files\Models\Attachment;
use App\Core\I18n\AddressFormatter;
use App\Core\I18n\Countries;
use App\Core\Utils\AppUrl;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\InfuseUtility as Utility;
use App\SalesTax\Calculator\AvalaraTaxCalculator;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use Carbon\CarbonImmutable;
use EmailReplyParser\EmailReplyParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractDocumentClientViewVariables
{
    use CustomerPortalViewVariablesTrait;

    public function __construct(
        protected UrlGeneratorInterface $urlGenerator,
        private EmailBodyStorageInterface $storage,
    ) {
    }

    /**
     * Makes the view parameters for a document to be used
     * in the client view.
     */
    protected function makeForDocument(ReceivableDocument $document, Request $request): array
    {
        $company = $document->tenant();
        $theme = $document->theme();
        $customer = $document->customer();

        // calculate the document so we have access to the computed rates
        $calculatedDocument = InvoiceCalculator::calculate($document->currency, $document->items(), $document->discounts(), $document->taxes(), $document->shipping());
        [$discounts, $discountBreakdown] = $this->getDiscounts($company, $document, $calculatedDocument);
        [$taxes, $taxBreakdown] = $this->getDocumentTaxes($company, $document, $calculatedDocument);
        [$shipping, $shippingBreakdown] = $this->getShipping($document, $calculatedDocument);

        // Ship To
        $shipTo = null;
        if ($shippingDetail = $document->ship_to) {
            $formatter = new AddressFormatter();
            $formatter->setTo($shippingDetail);
            $address = $formatter->buildAddress(false);
            // only show the country line when the customer and company are in different countries
            $showCountry = $address->getCountryCode() != $company->country;
            $options = ['showCountry' => $showCountry];
            $shipTo = [
                'name' => $shippingDetail->name,
                'attentionTo' => $shippingDetail->attention_to,
                'address' => $formatter->formatAddress($address, $options),
            ];
        }

        // Generate View on Invoiced URL
        $viewOnInvoicedUrl = null;
        if ($id = $document->network_document_id) {
            $viewOnInvoicedUrl = AppUrl::get()->getObjectLink(ObjectType::NetworkDocument, $id, ['account' => $document->network_document?->to_company_id]);
        }

        $showCountry = $customer->country && $company->country != $customer->country;

        return [
            'documentType' => $document->object,
            'billFrom' => $this->makeBillFrom($company, $showCountry),
            'billTo' => $this->makeBillTo($customer, $showCountry, $theme->show_customer_no),
            'shipTo' => $shipTo,
            'status' => $document->status,
            'number' => $document->number,
            'date' => CarbonImmutable::createFromTimestamp($document->date),
            'purchaseOrder' => $document->purchase_order,
            'currency' => $document->currency,
            'subtotal' => $document->subtotal,
            'total' => $document->total,
            'discounts' => $discounts,
            'discountBreakdown' => $discountBreakdown,
            'taxes' => $taxes,
            'taxBreakdown' => $taxBreakdown,
            'shipping' => $shipping,
            'shippingBreakdown' => $shippingBreakdown,
            'customFields' => $this->makeCustomFields($document, $customer),
            'lineItems' => $this->makeLineItemViewParameters($document, $customer),
            'notes' => $document->notes,
            'downloadCsvUrl' => $document->csv_url.'?locale='.$request->getLocale(),
            'downloadPdfUrl' => $document->pdf_url.'?locale='.$request->getLocale(),
            'downloadXmlUrl' => $document->xml_url.'?locale='.$request->getLocale(),
            'viewOnInvoicedUrl' => $viewOnInvoicedUrl,
            'attachments' => $this->getAttachments($document),
            'emails' => $this->getEmails($document),
            'customerEmail' => $customer->email,
        ];
    }

    private function makeLineItemViewParameters(ReceivableDocument $document, Customer $customer): array
    {
        $company = $document->tenant();
        $lines = [];
        foreach ($document->items as $lineItem) {
            $lineAmount = $lineItem->amount;
            $unitCost = $lineItem->unit_cost;

            // on estimates when the quantity is 0 then we want
            // to show the rate as the line item amount
            if ($document instanceof Estimate && 0 == $lineItem->quantity) {
                $lineAmount = $unitCost;
            }

            // add the billing period to the description
            $billingPeriod = '';
            if ($lineItem->period_start) {
                if ($lineItem->prorated) {
                    $billingPeriod .= 'Proration for ';
                }
                $billingPeriod .= date($company->date_format, $lineItem->period_start);
                $billingPeriod .= ' - ';
                $billingPeriod .= date($company->date_format, $lineItem->period_end);
            }

            $lines[] = [
                'name' => $lineItem->name,
                'description' => trim($lineItem->description),
                'billingPeriod' => $billingPeriod,
                'amount' => $lineAmount,
                'quantity' => $lineItem->quantity,
                'unitCost' => $unitCost,
                'customFields' => $this->makeCustomFields($lineItem, $customer),
                'type' => $lineItem->type,
            ];
        }

        return $lines;
    }

    private function getDiscounts(Company $company, ReceivableDocument $document, CalculatedInvoice $calculatedDocument): array
    {
        // total up the discounts
        $breakdown = [];
        $discounts = 0;
        foreach ($calculatedDocument->discounts as $discount) {
            $discounts += $discount['amount'];

            // name
            $line = '';
            if ($discount['coupon']) {
                $line .= $discount['coupon']['name'];
            } else {
                $line .= 'Discount';
            }

            // amount
            $line .= ': '.$document->currencyFormat($discount['amount']);

            // valid until
            if ($discount['expires']) {
                $line .= ' (expires '.date($company->date_format, $discount['expires']).')';
            }

            $breakdown[] = $line;
        }

        // mention line item discounts (if any)
        $lineItemDiscounts = 0;
        foreach ($calculatedDocument->items as $item) {
            foreach ($item['discounts'] as $discount) {
                $lineItemDiscounts += $discount['amount'];
                $discounts += $discount['amount'];
            }
        }

        if ($lineItemDiscounts > 0) {
            $breakdown[] = 'Line item discounts: '.$document->currencyFormat($lineItemDiscounts);
        }

        $discountBreakdown = join(', ', $breakdown);

        return [$discounts, $discountBreakdown];
    }

    private function getDocumentTaxes(Company $company, ReceivableDocument $document, CalculatedInvoice $calculatedDocument): array
    {
        // add subtotal taxes
        $breakdown = [];
        $taxes = 0;
        $countries = new Countries();
        foreach ($calculatedDocument->taxes as $tax) {
            $line = '';
            $taxes += $tax['amount'];

            // name
            if ($tax['tax_rate'] && AvalaraTaxCalculator::TAX_CODE != $tax['tax_rate']['id']) {
                $line .= $tax['tax_rate']['name'];
            } else {
                $label = 'Tax';
                if ($country = $countries->get((string) $company->country)) {
                    if (isset($country['tax_label'])) {
                        $label = $country['tax_label'];
                    }
                }
                $line .= $label;
            }

            // amount
            $line .= ': '.$document->currencyFormat($tax['amount']);

            $breakdown[] = $line;
        }

        // mention line item taxes (if any)
        $lineItemTaxes = 0;
        foreach ($calculatedDocument->items as $item) {
            foreach ($item['taxes'] as $tax) {
                $lineItemTaxes += $tax['amount'];
                $taxes += $tax['amount'];
            }
        }

        if ($lineItemTaxes > 0) {
            $breakdown[] = 'Line item taxes: '.$document->currencyFormat($lineItemTaxes);
        }

        $taxBreakdown = join(', ', $breakdown);

        return [$taxes, $taxBreakdown];
    }

    private function getShipping(ReceivableDocument $document, CalculatedInvoice $calculatedDocument): array
    {
        // add subtotal shipping
        $breakdown = [];
        $shipping = 0;
        foreach ($calculatedDocument->shipping as $entry) {
            $shipping += $entry['amount'];

            // name
            $line = '';
            if ($entry['shipping_rate']) {
                $line .= $entry['shipping_rate']['name'];
            } else {
                $line .= 'Shipping';
            }

            // amount
            $line .= ': '.$document->currencyFormat($entry['amount']);

            $breakdown[] = $line;
        }

        $shippingBreakdown = join(', ', $breakdown);

        return [$shipping, $shippingBreakdown];
    }

    private function getAttachments(ReceivableDocument $document): array
    {
        $attachments = [];
        foreach (Attachment::allForObject($document, Attachment::LOCATION_ATTACHMENT) as $attachment) {
            $attachment = $attachment->toArray();
            $size = $attachment['file']['size'];
            $attachment['size'] = Utility::numberAbbreviate($size).'B';
            $attachments[] = $attachment;
        }

        return $attachments;
    }

    protected function getEmails(ReceivableDocument $document): array
    {
        $objectTypeId = ObjectType::fromTypeName($document->object)->value;
        $thread = EmailThread::where('related_to_type', $objectTypeId)
            ->where('related_to_id', $document)
            ->oneOrNull();

        if (!$thread instanceof EmailThread) {
            return [];
        }

        $emails = [];
        foreach ($thread->emails as $email) {
            // Do not display bounce notifications to customer
            if ('Delivery Status Notification (Failure)' == $email->subject) {
                continue;
            }

            $emails[] = $this->expandInboxEmail($email, $document, null);
        }

        return $emails;
    }

    private function expandInboxEmail(InboxEmail $email, ReceivableDocument $document, ?string $text): array
    {
        $result = $email->toArray();
        if (!$text) {
            $text = $this->storage->retrieve($email, EmailBodyStorageInterface::TYPE_PLAIN_TEXT);
            $text = EmailReplyParser::parseReply((string) $text);
        }
        $result['text'] = $text;

        if ($email->incoming) {
            $result['name'] = $document->customer()->name;
            $result['from_customer'] = true;
        } else {
            $result['from_customer'] = false;
            $result['name'] = $email->tenant()->name;
        }

        $createdAt = $result['created_at'] - 1;
        $result['when'] = Utility::timeAgo($createdAt);

        $attachments = Attachment::allForObject($email);

        $result['attachments'] = [];
        foreach ($attachments as $attachment) {
            $attachment = $attachment->toArray();
            $size = $attachment['file']['size'];
            $attachment['size'] = Utility::numberAbbreviate($size).'B';
            $result['attachments'][] = $attachment;
        }

        return $result;
    }
}
