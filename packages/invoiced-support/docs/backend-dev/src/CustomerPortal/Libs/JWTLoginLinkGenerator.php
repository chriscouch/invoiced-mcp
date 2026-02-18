<?php

namespace App\CustomerPortal\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use Firebase\JWT\JWT;

final class JWTLoginLinkGenerator
{
    public const string JWT_ALGORITHM = 'HS256';

    public function generateToken(Company $company, Customer $customer, int $ttl): string
    {
        $params = [
            'iss' => $company->id,
            'sub' => $customer->id(),
            'iat' => time(),
            'exp' => time() + $ttl,
        ];

        return JWT::encode($params, $company->sso_key, self::JWT_ALGORITHM);
    }

    public function generateLoginUrl(Company $company, Customer $customer, int $ttl): string
    {
        $token = $this->generateToken($company, $customer, $ttl);

        return $company->url.'/login'.($token ? '/'.$token : '');
    }
}
