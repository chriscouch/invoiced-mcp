<?php

namespace App\Integrations\Plaid\Libs;

use App\Integrations\Plaid\Models\PlaidItem;

class AddPlaidItem
{
    /**
     * Adds a Plaid item to our database.
     */
    public function saveAccount(array $account, array $metadata, object $result): PlaidItem
    {
        $plaidItem = new PlaidItem();
        $plaidItem->access_token = $result->access_token;
        $plaidItem->item_id = $result->item_id;
        $plaidItem->institution_id = $metadata['institution']['institution_id'] ?? null;
        $plaidItem->institution_name = $metadata['institution']['name'] ?? null;
        $plaidItem->account_id = $account['id'];
        $plaidItem->account_name = $account['name'];
        $plaidItem->account_last4 = $account['mask'];
        $plaidItem->account_type = $account['type'];
        $plaidItem->account_subtype = $account['subtype'];
        $plaidItem->verified = 'pending_manual_verification' !== $account['verification_status'];
        $plaidItem->saveOrFail();

        return $plaidItem;
    }
}
