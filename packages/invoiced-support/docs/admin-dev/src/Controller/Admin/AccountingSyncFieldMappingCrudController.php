<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\AccountingSyncFieldMapping;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class AccountingSyncFieldMappingCrudController extends AbstractCrudController
{
    const INTEGRATION_CHOICES = [
        'Business Central' => 14,
        'FreshBooks' => 16,
        'Intacct' => 1,
        'NetSuite' => 2,
        'QuickBooks Desktop' => 3,
        'QuickBooks Online' => 4,
        'Sage Accounting' => 17,
        'Wave' => 15,
        'Xero' => 5,
    ];

    const DIRECTION_CHOICES = [
        'Read' => 1,
        'Write' => 2,
    ];

    public static function getEntityFqcn(): string
    {
        return AccountingSyncFieldMapping::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Accounting Sync Field Mappings')
            ->setEntityLabelInSingular('Accounting Sync Field Mapping')
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
            ->add(ChoiceFilter::new('integration', 'Integration')->setChoices(self::INTEGRATION_CHOICES))
            ->add(NumericFilter::new('tenant_id', 'Tenant ID'))
            ->add(ChoiceFilter::new('direction', 'Direction')->setChoices(self::DIRECTION_CHOICES))
            ->add('object_type')
            ->add('source_field')
            ->add('destination_field')
            ->add('enabled')
            ->add('data_type');
    }

    public function configureFields(string $pageName): iterable
    {
        $tenant = AssociationField::new('tenant');
        $tenantId = IntegerField::new('tenant_id');
        $integration = ChoiceField::new('integration')
            ->setChoices(self::INTEGRATION_CHOICES);
        $direction = ChoiceField::new('direction')
            ->setChoices(self::DIRECTION_CHOICES);
        $objectType = TextField::new('object_type', 'Object Type');
        $sourceField = TextField::new('source_field', 'Source Field');
        $destinationField = TextField::new('destination_field', 'Destination Field');
        $dataType = ChoiceField::new('data_type', 'Data Type')
            ->setChoices([
                'Array' => 'array',
                'Boolean' => 'boolean',
                'Country' => 'country',
                'Currency' => 'currency',
                'Date (unix timestamp)' => 'date_unix',
                'Email list' => 'email_list',
                'Float' => 'float',
                'String' => 'string',
                'Integer' => 'integer',
            ]);
        $enabled = BooleanField::new('enabled', 'Enabled')
            ->renderAsSwitch(false);
        $value = TextField::new('value', 'Value (Source Field must be __value__)');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $integration, $direction, $objectType, $sourceField, $destinationField, $dataType, $enabled];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$tenant, $integration, $direction, $objectType, $sourceField, $destinationField, $dataType, $value, $enabled];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $integration, $direction, $objectType, $sourceField, $destinationField, $dataType, $value, $enabled];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$direction, $objectType, $sourceField, $destinationField, $dataType, $value, $enabled];
        }

        return [];
    }

    /**
     * @param AccountingSyncFieldMapping $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Company $company */
        $company = $this->getDoctrine()->getRepository(Company::class)->find($entityInstance->getTenantId());
        $entityInstance->setTenant($company);

        parent::persistEntity($entityManager, $entityInstance);
    }
}
