<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PdfTemplate extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PdfTemplates');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('html', 'text')
            ->addColumn('css', 'text')
            ->addColumn('template_engine', 'enum', ['values' => ['mustache', 'twig'], 'default' => 'twig'])
            ->addTimestamps()
            ->create();

        $this->table('Themes')
            ->addForeignKey('invoice_template_id', 'PdfTemplates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('estimate_template_id', 'PdfTemplates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('statement_template_id', 'PdfTemplates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('receipt_template_id', 'PdfTemplates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
