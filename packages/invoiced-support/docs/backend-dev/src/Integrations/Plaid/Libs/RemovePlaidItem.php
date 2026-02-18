<?php

namespace App\Integrations\Plaid\Libs;

use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Plaid\Models\PlaidItem;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class RemovePlaidItem implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private PlaidApi $plaid)
    {
    }

    public function remove(PlaidItem $plaidItem): void
    {
        try {
            $this->plaid->removeItem($plaidItem);
        } catch (IntegrationApiException $e) {
            $this->logger->error('Could not remove Plaid item', ['exception' => $e]);
        }

        $plaidItem->deleteOrFail();
    }
}
