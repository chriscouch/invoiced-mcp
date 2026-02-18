<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EarthClassMailLastSync extends MultitenantModelMigration
{
    public function change()
    {
        // Sets the last_retrieved_data_at back 30 days. 2592000 is 30 days
        $this->execute('UPDATE EarthClassMailAccounts SET last_retrieved_data_at = last_retrieved_data_at - 2592000 WHERE last_retrieved_data_at >= 2592000');
    }
}
