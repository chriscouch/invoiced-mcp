<?php

namespace App\Integrations\Intacct\Transformers;

use App\Imports\Libs\ImportHelper;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractCustomerTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;

class IntacctCustomerTransformer extends AbstractCustomerTransformer
{
    /**
     * @param AccountingXmlRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // active status
        $status = (string) $input->document->{'STATUS'};
        $record['active'] = 'inactive' != $status;

        // email address(es)
        $emails = [];
        if ($email = (string) $input->document->{'BILLTO.EMAIL1'}) {
            $emails = array_merge($emails, ImportHelper::parseEmailAddress($email));
        } elseif ($email = (string) $input->document->{'DISPLAYCONTACT.EMAIL1'}) {
            $emails = array_merge($emails, ImportHelper::parseEmailAddress($email));
        }

        if ($email = (string) $input->document->{'BILLTO.EMAIL2'}) {
            $emails = array_merge($emails, ImportHelper::parseEmailAddress($email));
        } elseif ($email = (string) $input->document->{'DISPLAYCONTACT.EMAIL2'}) {
            $emails = array_merge($emails, ImportHelper::parseEmailAddress($email));
        }
        $record['emails'] = $emails;

        // address
        // first try the bill to contact
        // if that does not work then try display contact
        if ($address1 = (string) $input->document->{'BILLTO.MAILADDRESS.ADDRESS1'}) {
            $record['address1'] = $address1;
            $record['address2'] = (string) $input->document->{'BILLTO.MAILADDRESS.ADDRESS2'};
            $record['city'] = (string) $input->document->{'BILLTO.MAILADDRESS.CITY'};
            $record['state'] = (string) $input->document->{'BILLTO.MAILADDRESS.STATE'};
            $record['postal_code'] = (string) $input->document->{'BILLTO.MAILADDRESS.ZIP'};
            if ($country = (string) $input->document->{'BILLTO.MAILADDRESS.COUNTRYCODE'}) {
                $record['country'] = $country;
            }
        } else {
            $record['address1'] = (string) $input->document->{'DISPLAYCONTACT.MAILADDRESS.ADDRESS1'};
            $record['address2'] = (string) $input->document->{'DISPLAYCONTACT.MAILADDRESS.ADDRESS2'};
            $record['city'] = (string) $input->document->{'DISPLAYCONTACT.MAILADDRESS.CITY'};
            $record['state'] = (string) $input->document->{'DISPLAYCONTACT.MAILADDRESS.STATE'};
            $record['postal_code'] = (string) $input->document->{'DISPLAYCONTACT.MAILADDRESS.ZIP'};
            if ($country = (string) $input->document->{'DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE'}) {
                $record['country'] = $country;
            }
        }

        // Phone #
        if ($phone = (string) $input->document->{'BILLTO.PHONE1'}) {
            $record['phone'] = $phone;
        } elseif ($phone = (string) $input->document->{'DISPLAYCONTACT.PHONE1'}) {
            $record['phone'] = $phone;
        }

        if ($entity_id = (string) $input->document->{'MEGAENTITYID'}) {
            $record['metadata']['intacct_entity'] = $entity_id;
        }

        return $record;
    }
}
