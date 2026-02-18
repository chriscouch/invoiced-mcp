<?php

namespace App\Tests\Reports;

use App\Chasing\Models\Task;
use App\Metadata\Models\CustomField;
use App\Reports\Exceptions\ReportException;
use App\Reports\Interfaces\PresetReportInterface;
use App\Reports\Libs\PresetReportFactory;
use App\Reports\Libs\ReportStorage;
use App\Reports\PresetReports\AbstractReport;
use App\Reports\PresetReports\TaxSummary;
use App\Reports\ValueObjects\Report;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class PresetReportTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasTransaction();

        $member = self::hasMember('test');
        $task = new Task();
        $task->name = 'Send shut off notice';
        $task->action = 'mail';
        $task->due_date = time();
        $task->user_id = $member->user_id;
        $task->completed_by_user_id = $member->user_id;
        $task->saveOrFail();

        $customField1 = new CustomField();
        $customField1->id = 'test';
        $customField1->object = 'invoice';
        $customField1->name = 'Test';
        $customField1->choices = ['option1', 'option2', 'option1'];
        $customField1->saveOrFail();

        $customField2 = new CustomField();
        $customField2->id = 'test';
        $customField2->object = 'transaction';
        $customField2->name = 'Test';
        $customField2->choices = ['option1', 'option2', 'option1'];
        $customField2->saveOrFail();
    }

    private function getStorage(): ReportStorage
    {
        return self::getService('test.report_storage');
    }

    public function testAvailableReports(): void
    {
        // NOTE this only tests if the reports are functional
        // the legitimacy of the output of the report should be tested
        // separately.

        $start = (new CarbonImmutable('-1 day'))->format('Y-m-d');
        $end = CarbonImmutable::now()->format('Y-m-d');

        $storage = $this->getStorage();
        $csvOutput = $storage->getCsv();
        $htmlOutput = $storage->getHtml();
        $jsonOutput = $storage->getJson();
        $pdfOutput = $storage->getPdf();

        /** @var PresetReportFactory $factory */
        $factory = self::getService('test.preset_report_factory');

        foreach ($factory->all() as $type => $class) {
            $presetReport = $factory->get($type);

            $this->assertInstanceOf(PresetReportInterface::class, $presetReport);

            $parameters = [
                '$dateRange' => [
                    'start' => $start,
                    'end' => $end,
                ],
                '$merchantAccount' => 1,
            ];

            // build it
            $report = $presetReport->generate(self::$company, $parameters);
            $this->assertInstanceOf(Report::class, $report);

            // check base filename
            $this->assertGreaterThan(5, strlen($report->getFilename()));

            // check name
            $this->assertGreaterThan(5, strlen($report->getTitle()));

            // check currency
            $reportParameters = $report->getParameters();
            if (isset($reportParameters['$currency'])) {
                $this->assertEquals('usd', $reportParameters['$currency']);
            }

            // check html output
            $html = $htmlOutput->generate($report);

            $this->assertStringStartsWith('<!DOCTYPE html>', $html);
            $this->assertDoesNotMatchRegularExpression('/E_NOTICE|E_WARNING/', $html);

            if ($presetReport instanceof TaxSummary) {
                $parameters['$taxDate'] = 'payment';
                $report = $presetReport->generate(self::$company, $parameters);

                // check html output
                $html = $htmlOutput->generate($report);

                $this->assertStringStartsWith('<!DOCTYPE html>', $html);
                $this->assertDoesNotMatchRegularExpression('/E_NOTICE|E_WARNING/', $html);
            }

            // check json output
            $json = $jsonOutput->generate($report);
            $this->assertTrue(is_array($json));
            $this->assertGreaterThan(0, count($json));

            // check pdf output
            ob_start();
            $pdf = $pdfOutput->generate($report);
            ob_end_clean();

            $this->assertTrue(is_string($pdf));
            $this->assertGreaterThan(0, strlen($pdf));

            // check csv output
            $csv = $csvOutput->generate($report);
            $this->assertGreaterThan(0, strlen($csv));
            $this->assertTrue(strpos($csv, ',') > 0);

            // check invoice metadata filter
            if ($presetReport instanceof AbstractReport) {
                $cid = self::$company->id();
                $this->assertNull($presetReport->getInvoiceMetadataQuery());
                $parameters['$invoiceMetadata'] = ['account-rep' => 'Jan', 'department' => 'Sales'];
                $presetReport->generate(self::$company, $parameters);
                $this->assertEquals("EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`=$cid AND `object_type`=\"invoice\" AND object_id=Invoices.id AND `key`='account-rep' AND `value` = 'Jan') AND EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`=$cid AND `object_type`=\"invoice\" AND object_id=Invoices.id AND `key`='department' AND `value` = 'Sales')", $presetReport->getInvoiceMetadataQuery());
                $this->assertEquals("EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`=$cid AND `object_type`=\"invoice\" AND object_id=Transactions.invoice AND `key`='account-rep' AND `value` = 'Jan') AND EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`=$cid AND `object_type`=\"invoice\" AND object_id=Transactions.invoice AND `key`='department' AND `value` = 'Sales')", $presetReport->getInvoiceMetadataQuery('Transactions.invoice'));
            }

            // check invoice tags filter
            if ($presetReport instanceof AbstractReport) {
                $this->assertNull($presetReport->getInvoiceTagsQuery());
                $parameters['$invoiceTags'] = ['thing1', 'thing2'];
                $presetReport->generate(self::$company, $parameters);
                $this->assertEquals("(SELECT COUNT(*) FROM InvoiceTags WHERE invoice_id=Invoices.id AND tag IN ('thing1','thing2')) > 0", $presetReport->getInvoiceTagsQuery());
                $this->assertEquals("(SELECT COUNT(*) FROM InvoiceTags WHERE invoice_id=Transactions.invoice AND tag IN ('thing1','thing2')) > 0", $presetReport->getInvoiceTagsQuery('Transactions.invoice'));
            }

            // test metadata group by
            $parameters['$groupBy'] = 'metadata:test';
            $presetReport->generate(self::$company, $parameters);
        }
    }

    public function testGetBogusReport(): void
    {
        $this->expectException(ReportException::class);
        self::getService('test.preset_report_factory')->get(self::$company, 'blah');
    }
}
