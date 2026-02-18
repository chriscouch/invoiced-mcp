<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\BilledVolume;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class BilledVolumeCrudController extends AbstractVolumeCrudController
{
    public static function getEntityFqcn(): string
    {
        return BilledVolume::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPageTitle(Crud::PAGE_INDEX, 'Money Billed Usage Records');
    }
}
