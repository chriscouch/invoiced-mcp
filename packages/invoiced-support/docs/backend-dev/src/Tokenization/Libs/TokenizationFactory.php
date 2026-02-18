<?php

namespace App\Tokenization\Libs;

use App\Core\Multitenant\TenantContext;
use App\Tokenization\Operations\Tokenize;
use App\Tokenization\Operations\TokenizeAch;
use App\Tokenization\Operations\TokenizeCard;
use InvalidArgumentException;

class TokenizationFactory
{
    public function __construct(private readonly TenantContext $tenantContext, private readonly bool $adyenLiveMode)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(array $data): Tokenize
    {
        $tenant = $this->tenantContext->get();

        if (isset($data['card'])) {
            return new TokenizeCard($tenant, $this->adyenLiveMode);
        }
        if (isset($data['bank_account'])) {
            return new TokenizeAch($tenant, $this->adyenLiveMode);
        }

        throw new InvalidArgumentException();
    }
}
