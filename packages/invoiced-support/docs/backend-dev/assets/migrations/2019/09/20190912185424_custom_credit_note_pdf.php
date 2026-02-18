<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomCreditNotePdf extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Themes')
            ->addColumn('credit_note_template_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('credit_note_template_id', 'PdfTemplates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
