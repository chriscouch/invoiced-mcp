<?php

namespace App\Network\Traits;

use App\Companies\Models\Company;
use App\Network\Models\NetworkConnection;

trait NetworkConnectionApiTrait
{
    private function buildConnectionArray(NetworkConnection $connection, Company $company): array
    {
        return array_merge([
            'id' => $connection->id,
            'created_at' => $connection->created_at,
            'updated_at' => $connection->created_at,
        ], $this->buildCompanyArray($company));
    }

    private function buildCompanyArray(Company $company): array
    {
        return [
            'name' => $company->name,
            'username' => $company->username,
            'logo' => $company->logo,
            'industry' => $company->industry,
            'tax_id' => $company->tax_id,
            'entity_type' => $company->type,
            'email' => $company->email,
            'address1' => $company->address1,
            'address2' => $company->address2,
            'city' => $company->city,
            'state' => $company->state,
            'postal_code' => $company->postal_code,
            'country' => $company->country,
            'phone' => $company->phone,
            'website' => $company->website,
        ];
    }
}
