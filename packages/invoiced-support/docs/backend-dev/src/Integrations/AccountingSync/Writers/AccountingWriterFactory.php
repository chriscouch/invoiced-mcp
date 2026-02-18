<?php

namespace App\Integrations\AccountingSync\Writers;

use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Interfaces\AccountingWriterInterface;
use App\Integrations\BusinessCentral\Writers\BusinessCentralWriterFactory;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Writers\IntacctWriterFactory;
use App\Integrations\NetSuite\Writers\NetSuiteWriter;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksWriterFactory;
use App\Integrations\Xero\Writers\XeroWriterFactory;

/**
 * Factory for accounting system writer selection.
 */
class AccountingWriterFactory
{
    public function __construct(
        private BusinessCentralWriterFactory $businessCentralFactory,
        private IntacctWriterFactory $intacctFactory,
        private NetSuiteWriter $netSuiteWriter,
        private QuickBooksWriterFactory $qboFactory,
        private XeroWriterFactory $xeroFactory,
    ) {
    }

    public function build(AccountingWritableModelInterface $obj, IntegrationType $integration): AccountingWriterInterface
    {
        return match ($integration) {
            IntegrationType::BusinessCentral => $this->businessCentralFactory->get($obj),
            IntegrationType::Intacct => $this->intacctFactory->get($obj),
            IntegrationType::NetSuite => $this->netSuiteWriter,
            IntegrationType::QuickBooksOnline => $this->qboFactory->get($obj),
            IntegrationType::Xero => $this->xeroFactory->get($obj),
            default => new NullWriter(),
        };
    }
}
