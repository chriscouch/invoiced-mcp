<?php

namespace App\EntryPoint\Controller\Api;

use App\SalesTax\Api\CreateTaxRateRoute;
use App\SalesTax\Api\DeleteTaxRateRoute;
use App\SalesTax\Api\EditTaxRateRoute;
use App\SalesTax\Api\ListTaxRatesRoute;
use App\SalesTax\Api\RetrieveTaxRateRoute;
use App\SalesTax\Models\TaxRate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class TaxRateApiController extends AbstractApiController
{
    #[Route(path: '/tax_rates', name: 'list_tax_rates', methods: ['GET'])]
    public function listAll(ListTaxRatesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tax_rates', name: 'create_tax_rate', methods: ['POST'])]
    public function create(CreateTaxRateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tax_rates/{model_id}', name: 'retrieve_tax_rate', methods: ['GET'])]
    public function retrieve(RetrieveTaxRateRoute $route, string $model_id): Response
    {
        if ($model = TaxRate::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/tax_rates/{model_id}', name: 'edit_tax_rate', methods: ['PATCH'])]
    public function edit(EditTaxRateRoute $route, string $model_id): Response
    {
        if ($model = TaxRate::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/tax_rates/{model_id}', name: 'delete_tax_rate', methods: ['DELETE'])]
    public function delete(DeleteTaxRateRoute $route, string $model_id): Response
    {
        if ($model = TaxRate::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }
}
