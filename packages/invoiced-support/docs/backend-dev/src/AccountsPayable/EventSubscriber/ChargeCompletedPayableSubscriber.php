<?php

namespace App\AccountsPayable\EventSubscriber;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Operations\CreateVendorPayment;
use App\AccountsPayable\PaymentMethods\CreditCardPaymentMethod;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Events\CompletedChargeEvent;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\ConvenienceFeeChargeApplicationItem;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ChargeCompletedPayableSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CreateVendorPayment $createVendorPayment,
        private CreditCardPaymentMethod $creditCardPaymentMethod,
        private TenantContext $tenant,
    ) {
    }

    public function completeCharge(CompletedChargeEvent $event): void
    {
        $this->persistVendorPayment($event->chargeValueObject, $event->chargeApplication, $event->charge);
    }

    public static function getSubscribedEvents(): array
    {
        return [CompletedChargeEvent::class => 'completeCharge'];
    }

    private function persistVendorPayment(ChargeValueObject $charge, ChargeApplication $chargeApplication, Charge $chargeModel): void
    {
        // Check if this customer is a network connection
        $networkConnection = $charge->customer->network_connection;
        $customerCompany = $networkConnection?->customer;

        if (!$customerCompany) {
            return;
        }

        // Do not create a vendor payment for failed payments
        if (Charge::FAILED == $charge->status) {
            return;
        }

        // Create a vendor payment in the customer's company if this is in-network
        $this->tenant->runAs($customerCompany, function () use ($charge, $chargeApplication, $chargeModel, $networkConnection) {
            // Find the associated vendor
            $vendor = Vendor::where('network_connection_id', $networkConnection)->oneOrNull();
            if (!$vendor) {
                return;
            }

            // Check for existing vendor payment
            $count = VendorPayment::where('vendor_id', $vendor)
                ->where('reference', $chargeModel->id)
                ->count();
            if ($count > 0) {
                return;
            }

            // Create a new vendor payment
            $parameters = [
                'vendor' => $vendor,
                'amount' => $charge->amount->toDecimal(),
                'currency' => $charge->amount->currency,
                'date' => CarbonImmutable::createFromTimestamp($charge->timestamp),
                'reference' => $chargeModel->id,
                'payment_method' => $charge->method,
                'notes' => $charge->source?->toString(true),
            ];

            // TODO: this does not handle all payment types, such as credit note applications
            $appliedTo = [];
            foreach ($chargeApplication->getItems() as $item) {
                if ($item instanceof ConvenienceFeeChargeApplicationItem) {
                    $appliedTo[] = [
                        'type' => 'convenience_fee',
                        'amount' => $item->getAmount()->toDecimal(),
                    ];
                } elseif ($document = $item->getPayableDocument()) {
                    if ($document instanceof Bill) {
                        $appliedTo[] = [
                            'bill' => $document,
                            'amount' => $item->getAmount()->toDecimal(),
                        ];
                    }
                } else {
                    $document = $item->getDocument();
                    if ($document instanceof Invoice && $networkDocument = $document->network_document) {
                        $bill = Bill::where('network_document_id', $networkDocument)->oneOrNull();
                        if ($bill) {
                            $appliedTo[] = [
                                'bill' => $bill,
                                'amount' => $item->getAmount()->toDecimal(),
                            ];
                        }
                    }
                }
            }

            try {
                $vendorPayment = $this->createVendorPayment->create($parameters, $appliedTo);

                // Hand the created payment to the credit card A/P payment method
                // which can use this to return the user to the created payment.
                // Not every charge will be through the credit card A/P payment method.
                $this->creditCardPaymentMethod->setCreatedPayment($vendorPayment);
            } catch (ModelException $e) {
                throw new ReconciliationException($e->getMessage());
            }
        });
    }
}
