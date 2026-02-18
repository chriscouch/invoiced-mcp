<?php

namespace App\Integrations\Intacct\Extractors;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use Generator;
use SimpleXMLElement;

class IntacctArInvoiceExtractor extends AbstractIntacctExtractor
{
    private const FIELDS = [/* @phpstan-ignore-line */
        'RECORDNO',
        'RECORDID',
        'CURRENCY',
        'WHENPOSTED',
        'WHENDUE',
        'TRX_TOTALPAID',
        'DOCNUMBER',
        // customers
        'CUSTREC', // TODO: need to confirm if this is correct field for customer record number
        'CUSTOMERID',
        'CUSTOMERNAME',
        // ship to
        'SHIPTO.PRINTAS',
        'SHIPTO.MAILADDRESS.ADDRESS1',
        'SHIPTO.MAILADDRESS.ADDRESS2',
        'SHIPTO.MAILADDRESS.CITY',
        'SHIPTO.MAILADDRESS.STATE',
        'SHIPTO.MAILADDRESS.ZIP',
        'SHIPTO.MAILADDRESS.COUNTRYCODE',
    ];

    public function getObject(string $objectId): AccountingRecordInterface
    {
        // TODO: Implement getObject() method.
        return new AccountingXmlRecord(new SimpleXMLElement(''));
    }

    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        // TODO: Implement getObjects() method.
        return new Generator();
    }
}
