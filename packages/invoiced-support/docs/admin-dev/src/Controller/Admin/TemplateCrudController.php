<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\Template;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Template::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Templates')
            ->setSearchFields(['filename', 'tenant.name'])
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add('index', 'detail')
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IntegerField::new('id');
        $tenantId = IntegerField::new('tenant_id');
        $tenant = AssociationField::new('tenant');
        $filename = TextField::new('filename');
        $content = CodeEditorField::new('content')
            ->setNumOfRows(100)
            ->setLanguage('js')
            ->setRequired(false);
        $enabled = BooleanField::new('enabled')->renderAsSwitch(false);
        $createdAt = DateTimeField::new('created_at');
        $updatedAt = DateTimeField::new('updated_at', 'Updated At');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $tenant, $filename, $enabled, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $tenant, $filename, $enabled, $content, $createdAt, $updatedAt];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $filename, $enabled, $content];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$filename, $enabled, $content];
        }

        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('tenant_id');
    }
}
