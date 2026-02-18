<?php

use Phinx\Migration\AbstractMigration;

final class CompanySamlSettingsFix extends AbstractMigration
{
    public function change()
    {
        $this->table('CompanySamlSettings')->changeColumn('cert', 'text')->update();
    }
}
