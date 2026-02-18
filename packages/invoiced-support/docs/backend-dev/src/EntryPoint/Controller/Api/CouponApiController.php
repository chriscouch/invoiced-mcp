<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\Coupons\CreateCouponRoute;
use App\AccountsReceivable\Api\Coupons\DeleteCouponRoute;
use App\AccountsReceivable\Api\Coupons\EditCouponRoute;
use App\AccountsReceivable\Api\Coupons\ListCouponsRoute;
use App\AccountsReceivable\Api\Coupons\RetrieveCouponRoute;
use App\AccountsReceivable\Models\Coupon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CouponApiController extends AbstractApiController
{
    #[Route(path: '/coupons', name: 'list_coupons', methods: ['GET'])]
    public function listAll(ListCouponsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/coupons', name: 'create_coupon', methods: ['POST'])]
    public function create(CreateCouponRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/coupons/{model_id}', name: 'retrieve_coupon', methods: ['GET'])]
    public function retrieve(RetrieveCouponRoute $route, string $model_id): Response
    {
        if ($model = Coupon::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/coupons/{model_id}', name: 'edit_coupon', methods: ['PATCH'])]
    public function edit(EditCouponRoute $route, string $model_id): Response
    {
        if ($model = Coupon::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/coupons/{model_id}', name: 'delete_coupon', methods: ['DELETE'])]
    public function delete(DeleteCouponRoute $route, string $model_id): Response
    {
        if ($model = Coupon::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }
}
