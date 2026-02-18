<?php

namespace App\Tests\Integrations\Adyen\ReportHandler;

use App\Integrations\Adyen\ReportHandler\BalanceReportHandler;

class BalanceReportHandlerTest extends AbstractReportHandlerTest
{
    protected function getHandler(): BalanceReportHandler
    {
        return new BalanceReportHandler(
            true,
            self::getService('test.mailer'),
            'test',
        );
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHandleRow(): void
    {
        $this->createMerchantAccount('112741-maxnczmria');

        // Process all the reports
        $this->handleFile('balanceplatform_balance_report_2025_04_21.csv');

        // Nothing to verify
    }
}
