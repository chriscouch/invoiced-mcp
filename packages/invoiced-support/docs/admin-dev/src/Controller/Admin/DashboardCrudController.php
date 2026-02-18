<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\Dashboard;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class DashboardCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Dashboard::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Dashboards')
            ->setSearchFields(['name', 'tenant.name'])
            ->setDefaultSort(['name' => 'ASC', 'tenant_id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail');
    }

    public function configureFields(string $pageName): iterable
    {
        $name = TextField::new('name');
        $definition = CodeEditorField::new('definition')
            ->setNumOfRows(100)
            ->setLanguage('js');
        $tenantId = IntegerField::new('tenant_id');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $name, $definition];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$name, $definition, $tenant];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $name, $definition];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$definition];
        }

        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('tenant_id')
            ->add('name');
    }

    /**
     * @param Dashboard $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Company $company */
        $company = $this->getDoctrine()->getRepository(Company::class)->find($entityInstance->getTenantId());
        $entityInstance->setTenant($company);

        parent::persistEntity($entityManager, $entityInstance);
    }
}
