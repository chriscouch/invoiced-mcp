<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\CustomerVolume;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class CustomerVolumeCrudController extends AbstractVolumeCrudController
{
    public static function getEntityFqcn(): string
    {
        return CustomerVolume::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPageTitle(Crud::PAGE_INDEX, 'Customer Usage Records');
    }
}
