<?php

use Phinx\Migration\AbstractMigration;

final class CompanySamlSettingsFix2 extends AbstractMigration
{
    public function change()
    {
        if ($this->table('CompanySamlSettings')->hasColumn('sso_binding')) {
            $this->table('CompanySamlSettings')
                ->removeColumn('sso_binding')
                ->removeColumn('sls_url')
                ->removeColumn('sls_binding')
                ->update();
        }
    }
}
