<?php

namespace App\Tests\AccountsPayable\Api;

use App\AccountsPayable\Api\PrintVendorPaymentBatchCheckApiRoute;
use App\AccountsPayable\Enums\CheckStock;
use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Libs\CheckPdf;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class PrintVendorPaymentBatchCheckApiRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::hasCompanyBankAccount();
        self::hasBatchPayment();
        self::hasVendor();
        self::hasBill();
        self::$batchPayment->status = VendorBatchPaymentStatus::Finished;
        self::$batchPayment->check_layout = CheckStock::CheckOnTop;
        self::$batchPayment->saveOrFail();
    }

    public function testBuildResponse(): void
    {
        $definition = new ApiRouteDefinition(null, null, []);
        $checkPdf = Mockery::mock(CheckPdf::class);
        $checkPdf->shouldReceive('setParameters', 'build', 'getFilename');
        $request = new Request();
        $context = new ApiCallContext($request, [], [], $definition);
        $route = new PrintVendorPaymentBatchCheckApiRoute($checkPdf, self::getService('test.tenant'));
        $route->setModelClass(VendorPaymentBatch::class);

        // no bill payments
        $request->attributes->set('model_id', self::$batchPayment->id);
        try {
            $route->buildResponse($context);
            $this->assertTrue(false, 'Exception not thrown');
        } catch (InvalidRequest $e) {
            $this->assertEquals('There are no selected bills to create checks', $e->getMessage());
        }

        $vendorPayment = new VendorPayment();
        $vendorPayment->vendor = self::$vendor;
        $vendorPayment->payment_method = 'check';
        $vendorPayment->currency = 'usd';
        $vendorPayment->amount = 100;
        $vendorPayment->vendor_payment_batch = self::$batchPayment;
        $vendorPayment->saveOrFail();

        $route->buildResponse($context);

        self::$batchPayment->check_layout = CheckStock::ThreePerPage;
        self::$batchPayment->saveOrFail();

        $context = new ApiCallContext($request, [], [], $definition);

        $route->buildResponse($context);
    }
}
