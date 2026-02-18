<?php

namespace App\Integrations\Xero\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractCustomerTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;

class XeroContactTransformer extends AbstractCustomerTransformer
{
    private const PHONE_TYPE_DEFAULT = 'DEFAULT';
    private const ADDRESS_TYPE_POBOX = 'POBOX';

    public function getMappingObjectType(): string
    {
        return 'contact';
    }

    /**
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // strip customer # from, if present
        $name = (string) $record['name'];
        $i = strpos($name, 'CUST-');
        if (false !== $i) {
            $name = substr($name, 0, $i);
        }
        $record['name'] = trim($name);

        // Account #
        $contactNumber = $input->document->ContactNumber ?? $input->document->AccountNumber ?? '';
        if (strlen($contactNumber) >= 1 && strlen($contactNumber) <= 32) {
            $record['number'] = $contactNumber;
        }

        // Active
        $status = $input->document->ContactStatus;
        $record['active'] = !in_array($status, ['ARCHIVED', 'GDPRREQUEST']);

        // Contacts
        $record['contacts'] = [];
        if (property_exists($input->document, 'ContactPersons')) {
            foreach ($input->document->ContactPersons as $contactPerson) {
                $name = trim(($contactPerson->FirstName ?? '').' '.($contactPerson->LastName ?? ''));
                $record['contacts'][] = [
                    'primary' => $contactPerson->IncludeInEmails,
                    'name' => $name ?: $record['name'],
                    'email' => $contactPerson->EmailAddress ?? '',
                ];
            }
        }

        // Address
        foreach ($input->document->Addresses ?? [] as $address) {
            if (self::ADDRESS_TYPE_POBOX != $address->AddressType) {
                continue;
            }

            $record['attention_to'] = trim($address->AttentionTo ?? '');
            $record['address1'] = trim($address->AddressLine1 ?? '');
            $record['address2'] = trim($address->AddressLine2 ?? '');
            $record['city'] = trim($address->City ?? '');
            $record['state'] = trim($address->Region ?? '');
            $record['postal_code'] = trim($address->PostalCode ?? '');
            $record['country'] = trim($address->Country ?? '');
            // We expect a 2-digit country code. If that's not given then
            // we are going to ignore the country for now
            if (2 != strlen($record['country'])) {
                unset($record['country']);
            }
        }

        // Phone #
        foreach ($input->document->Phones ?? [] as $phone) {
            if (self::PHONE_TYPE_DEFAULT != $phone->PhoneType) {
                continue;
            }

            $number = $phone->PhoneNumber ?? '';
            $areaCode = $phone->PhoneAreaCode ?? '';
            $countryCode = $phone->PhoneCountryCode ?? '';

            $phone = [];
            if ($countryCode) {
                $phone[] = '+'.$countryCode;
            }
            if ($areaCode) {
                $phone[] = '('.$areaCode.')';
            }
            if ($number) {
                $phone[] = $number;
            }

            $record['phone'] = implode(' ', $phone);
        }

        return $record;
    }
}
