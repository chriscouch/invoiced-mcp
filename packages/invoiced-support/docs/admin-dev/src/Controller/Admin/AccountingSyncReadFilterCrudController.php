<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\AccountingSyncReadFilter;
use App\Entity\Invoiced\Company;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class AccountingSyncReadFilterCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccountingSyncReadFilter::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Accounting Sync Read Filters')
            ->setEntityLabelInSingular('Accounting Sync Read Filter')
            ->setSearchFields(['tenant_id', 'tenant.name'])
            ->setDefaultSort(['tenant_id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('integration', 'Integration')->setChoices(AccountingSyncFieldMappingCrudController::INTEGRATION_CHOICES))
            ->add(NumericFilter::new('tenant_id', 'Tenant ID'))
            ->add('object_type')
            ->add('enabled');
    }

    public function configureFields(string $pageName): iterable
    {
        $tenant = AssociationField::new('tenant');
        $tenantId = IntegerField::new('tenant_id');
        $integration = ChoiceField::new('integration')
            ->setChoices(AccountingSyncFieldMappingCrudController::INTEGRATION_CHOICES);
        $objectType = TextField::new('object_type', 'Object Type');
        $formula = TextareaField::new('formula', 'Formula')
            ->setNumOfRows(3);
        $enabled = BooleanField::new('enabled', 'Enabled')
            ->renderAsSwitch(false);

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $integration, $objectType, $formula, $enabled];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$tenant, $integration, $objectType, $formula, $enabled];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $integration, $objectType, $formula, $enabled];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$objectType, $formula, $enabled];
        }

        return [];
    }

    /**
     * @param AccountingSyncReadFilter $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Company $company */
        $company = $this->getDoctrine()->getRepository(Company::class)->find($entityInstance->getTenantId());
        $entityInstance->setTenant($company);

        parent::persistEntity($entityManager, $entityInstance);
    }
}
