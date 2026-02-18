<?php

namespace App\Notifications\Api;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Notifications\Models\NotificationSubscription;

class UserNotificationSubscriptionRoute extends AbstractModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            requiresMember: true,
        );
    }

    public function buildResponse(ApiCallContext $context): NotificationSubscription
    {
        /** @var Member $member */
        $member = ACLModelRequester::get();
        $customerId = (string) $context->request->request->get('customer_id');
        $subscribe = $context->request->request->getBoolean('subscription');

        $model = NotificationSubscription::where('member_id', $member->id)
            ->where('customer_id', $customerId)
            ->oneOrNull();
        if (!$model) {
            $model = new NotificationSubscription();
        }
        $this->setModel($model);

        /** @var NotificationSubscription $model */
        $model = $this->getModel();

        $customer = Customer::findOrFail($customerId);
        $model->member = $member;
        $model->customer = $customer;
        $model->subscribe = $subscribe;
        $model->saveOrFail();

        return $model;
    }
}
