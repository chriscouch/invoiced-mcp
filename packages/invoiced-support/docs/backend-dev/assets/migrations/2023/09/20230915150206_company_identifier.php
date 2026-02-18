<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;
use App\Core\Utils\RandomString;

final class CompanyIdentifier extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Companies')
            ->addColumn('identifier', 'string', ['length' => 24])
            ->update();
        $this->ensureInstantEnd();

        $rows = $this->fetchAll('SELECT id FROM Companies WHERE identifier=""');
        foreach ($rows as $row) {
            $externalId = RandomString::generate(24, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $this->execute('UPDATE Companies SET identifier="'.$externalId.'" WHERE id='.$row['id']);
        }

        $this->table('Companies')
            ->addIndex('identifier', ['unique' => true])
            ->update();
    }
}
