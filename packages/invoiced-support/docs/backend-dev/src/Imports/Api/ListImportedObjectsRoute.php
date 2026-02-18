<?php

namespace App\Imports\Api;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\RemittanceAdvice;
use App\CashApplication\Models\Transaction;
use App\Core\Orm\Model;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Utils\Enums\ObjectType;
use App\Imports\Models\Import;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\SalesTax\Models\TaxRate;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use Doctrine\DBAL\Connection;

class ListImportedObjectsRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private Connection $database)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Import::class,
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $import = parent::buildResponse($context);

        $sql = 'SELECT object,object_id FROM ImportedObjects WHERE import = :import LIMIT 1000';
        $rows = $this->database->fetchAllAssociative($sql, ['import' => $import->id()]);

        $result = [];
        foreach ($rows as $row) {
            $objectType = ObjectType::from((int) $row['object']);
            $objectId = $row['object_id'];

            $modelClass = $objectType->modelClass();

            /** @var Model $modelClass */
            $model = $modelClass::find($objectId);

            $data = [
                'object' => $objectType->typeName(),
                'id' => $objectId,
                'name' => $this->getName($model, $objectId),
            ];

            if ($model instanceof Contact) {
                $data['customer_id'] = $model->customer_id;
            }

            $result[] = $data;
        }

        return $result;
    }

    private function getName(?Model $model, string $objectId): string
    {
        if ($model instanceof Customer || $model instanceof Contact || $model instanceof Plan || $model instanceof Item || $model instanceof TaxRate || $model instanceof Coupon || $model instanceof Vendor) {
            return $model->name;
        }

        if ($model instanceof BankAccount || $model instanceof Card) {
            return $model->gateway.($model->gateway_customer ? '-'.$model->gateway_customer : '').'-'.$model->gateway_id.' ('.$model->toString().')';
        }

        if ($model instanceof Invoice || $model instanceof CreditNote || $model instanceof Estimate || $model instanceof Bill || $model instanceof VendorCredit || $model instanceof VendorPayment) {
            return $model->number;
        }

        if ($model instanceof Subscription) {
            return 'Subscription to '.$model->plan;
        }

        if ($model instanceof Payment) {
            return 'Payment for '.$model->getAmount();
        }

        if ($model instanceof RemittanceAdvice) {
            return $model->payment_reference;
        }

        if ($model instanceof Transaction) {
            return 'Payment for '.$model->transactionAmount();
        }

        return $objectId;
    }
}
