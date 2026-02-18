<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\AccountsPayableSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AccountsPayableSettingsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccountsPayableSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('A/P Settings')
            ->setEntityLabelInPlural('A/P Settings')
            ->setSearchFields(['tenant_id'])
            ->setDefaultSort(['tenant_id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'index', 'edit', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        $agingBuckets = TextField::new('aging_buckets');
        $agingDate = TextField::new('aging_date');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_DETAIL === $pageName) {
            return [
                $tenant,
                $agingBuckets,
                $agingDate,
            ];
        }

        return [];
    }
}
