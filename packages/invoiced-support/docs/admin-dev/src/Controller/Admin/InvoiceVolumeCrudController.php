<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\InvoiceVolume;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class InvoiceVolumeCrudController extends AbstractVolumeCrudController
{
    public static function getEntityFqcn(): string
    {
        return InvoiceVolume::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPageTitle(Crud::PAGE_INDEX, 'Invoice Usage Records');
    }
}
