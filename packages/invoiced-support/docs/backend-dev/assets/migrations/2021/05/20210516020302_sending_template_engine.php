<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SendingTemplateEngine extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('EmailTemplates')
            ->addColumn('template_engine', 'enum', ['values' => ['mustache', 'twig'], 'null' => true])
            ->update();

        $this->table('SmsTemplates')
            ->addColumn('template_engine', 'enum', ['values' => ['mustache', 'twig'], 'null' => true])
            ->update();
    }
}
