<?php

namespace App\Tests\AccountsPayable\Operations;

use App\AccountsPayable\Libs\ECheckPdf;
use App\AccountsPayable\Models\ECheck;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentItem;
use App\Core\Files\Models\File;
use App\Core\Files\Models\VendorPaymentAttachment;
use App\Core\Mailer\Mailer;
use App\Core\NullFileProxy;
use App\Core\S3ProxyFactory;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\QueueJob\SendECheckQueueJob;
use App\Tests\AppTestCase;
use Aws\S3\S3Client;
use Aws\Sdk;
use Mockery;

class SendECheckQueueJobTest extends AppTestCase
{
    private static VendorPayment $vendorPayment;

    /**
     * test for SendECheckQueueJob::perform().
     */
    public function testPerform(): void
    {
        self::hasCompany();
        self::hasVendor();
        self::hasBill();
        self::hasCompanyBankAccount();

        self::$companyBankAccount->account_number = '123456789';
        self::$companyBankAccount->routing_number = '123456789';
        self::$companyBankAccount->saveOrFail();

        self::$vendorPayment = new VendorPayment();
        self::$vendorPayment->vendor = self::$vendor;
        self::$vendorPayment->amount = 100;
        self::$vendorPayment->currency = 'usd';
        self::$vendorPayment->saveOrFail();

        $item1 = new VendorPaymentItem();
        $item1->vendor_payment = self::$vendorPayment;
        $item1->amount = 100;
        $item1->bill = self::$bill;
        $item1->saveOrFail();

        $eCheck = new ECheck();
        $eCheck->payment = self::$vendorPayment;
        $eCheck->account = self::$companyBankAccount;
        $eCheck->address1 = 'address1';
        $eCheck->address2 = 'address2';
        $eCheck->city = 'city';
        $eCheck->state = 'TX';
        $eCheck->postal_code = 78738;
        $eCheck->country = 'US';
        $eCheck->email = 'test@test.com';
        $eCheck->amount = 100;
        $eCheck->check_number = 1;
        $eCheck->saveOrFail();

        $aws = Mockery::mock(Sdk::class);
        $s3 = Mockery::mock(S3Client::class);
        $aws->shouldReceive('createS3')->andReturn($s3);
        $mailer = Mockery::mock(Mailer::class);
        $pdf = Mockery::mock(ECheckPdf::class);

        $pdf->shouldReceive('setParameters')->withArgs(function ($a) use ($eCheck) {
            $this->assertEquals(array_diff_key($a[0], ['date' => true]), [
                    'amount' => 100,
                    'amount_text' => 'one hundred 0/100',
                    'vendor_name' => self::$vendor->name,
                    'customer_name' => self::$company->name,
                    'vendor_id' => self::$vendor->id,
                    'check_number' => $eCheck->check_number,
                    'currency' => self::$bill->currency,
                    'bills' => [
                        [
                            'bill_id' => self::$bill->id,
                            'number' => self::$bill->number,
                            'amount' => $eCheck->amount,
                            'date' => self::$bill->date->format(self::$company->date_format),
                        ],
                    ],
                    'email' => null,
                    'address1' => null,
                    'address2' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                    'country' => null,
                    'routing_number' => '123456789',
                    'account_number' => '123456789',
                    'signature' => null,
                ]
            );

            return true;
        });

        $pdf->shouldReceive('build')->andReturn('pdf');

        $s3 = Mockery::mock(NullFileProxy::class);
        $s3->shouldReceive('putObject');

        $s3Factory = Mockery::mock(S3ProxyFactory::class);
        $s3Factory->shouldReceive('build')
            ->andReturn($s3);

        $mailer->shouldReceive('send')->withArgs(function ($a, $b, $c) use ($eCheck) {
            $this->assertEquals([
                'from_email' => 'test@example.com',
                'to' => [[
                    'email' => 'test@test.com',
                    'name' => self::$vendor->name,
                ]],
                'subject' => 'You received E-Check from '.self::$company->name,
            ], $a);
            $this->assertEquals('e-check', $b);
            $this->assertEquals([
                'href' => 'http://invoiced.localhost:1234/checks/'.$eCheck->hash,
                'vendor_name' => self::$vendor->name,
                'customer_name' => self::$company->name,
            ], $c);

            return true;
        });

        $job = new SendECheckQueueJob(
            'dev',
            'test',
            'us-east-1',
            $mailer,
            $pdf,
            'http://files.invoiced.localhost:1234',
            $s3Factory
        );

        $job->setStatsd(new StatsdClient());
        $job->args = [
            'check_id' => $eCheck->id,
        ];
        $job->setLogger(self::$logger);

        $this->assertEquals(0, File::count());
        $this->assertEquals(0, VendorPaymentAttachment::count());
        $job->perform();

        $files = File::execute();
        $this->assertCount(1, $files);
        $this->assertEquals([
            'id' => $files[0]->id,
            'name' => 'E-Check for Test Vendor ('.self::$vendorPayment->number.').pdf',
            'type' => 'application/pdf',
            'size' => 3,
            'url' => $files[0]->url,
            'created_at' => $files[0]->created_at,
            'updated_at' => $files[0]->updated_at,
            'object' => 'file',
            'bucket_name' => 'test',
            'bucket_region' => 'us-east-1',
            'key' => $files[0]->key,
            's3_environment' => 'dev',
        ], $files[0]->toArray());

        /** @var VendorPaymentAttachment[] $attachments */
        $attachments = VendorPaymentAttachment::execute();
        $this->assertCount(1, $attachments);
        $this->assertEquals(self::$vendorPayment->id, $attachments[0]->vendor_payment_id);
    }
}
