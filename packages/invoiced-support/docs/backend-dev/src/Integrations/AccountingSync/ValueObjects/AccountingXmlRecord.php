<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use SimpleXMLElement;

/**
 * This represents a record extracted from the accounting system
 * that has not been transformed into an Invoiced record yet.
 */
final readonly class AccountingXmlRecord implements AccountingRecordInterface
{
    /**
     * @param SimpleXMLElement[]|null $lines
     */
    public function __construct(
        public SimpleXMLElement $document,
        public ?string $pdf = null,
        public ?array $lines = null,
    ) {
    }
}
