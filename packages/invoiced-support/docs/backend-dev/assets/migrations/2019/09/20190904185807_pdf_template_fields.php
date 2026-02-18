<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PdfTemplateFields extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('PdfTemplates')
            ->addColumn('header_html', 'text', ['default' => null])
            ->addColumn('header_css', 'text', ['default' => null])
            ->addColumn('footer_html', 'text', ['default' => null])
            ->addColumn('footer_css', 'text', ['default' => null])
            ->addColumn('margin_top', 'string', ['default' => '0.5cm'])
            ->addColumn('margin_bottom', 'string', ['default' => '0.5cm'])
            ->addColumn('margin_left', 'string', ['default' => '0.5cm'])
            ->addColumn('margin_right', 'string', ['default' => '0.5cm'])
            ->update();
    }
}
