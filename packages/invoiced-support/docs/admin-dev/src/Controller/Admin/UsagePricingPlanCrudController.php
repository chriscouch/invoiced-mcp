<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\BillingProfile;
use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\UsagePricingPlan;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class UsagePricingPlanCrudController extends AbstractCrudController
{
    use ReturnToCompanyTrait;

    private ManagerRegistry $managerRegistry;

    public const USAGE_TYPES = [
        'Invoices/Month' => 1,
        'Customers/Month' => 2,
        'Users' => 3,
        'Money Billed/Month' => 4,
        'Entities' => 5,
    ];

    public function __construct(private RequestStack $requestStack, ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public static function getEntityFqcn(): string
    {
        return UsagePricingPlan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Usage Pricing Plans')
            ->setEntityLabelInSingular('Usage Pricing Plan')
            ->setSearchFields(['id', 'tenant.name'])
            ->setDefaultSort(['id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail');
    }

    public function configureFields(string $pageName): iterable
    {
        $fieldsData = $this->prepareDataForDropdowns();
        $usageTypeName = TextField::new('usageTypeName');
        $usageTypeChoice = ChoiceField::new('usage_type')
            ->setChoices(self::USAGE_TYPES);
        $threshold = IntegerField::new('threshold', 'Included');
        $unitPrice = NumberField::new('unit_price');
        $billingProfileId = ChoiceField::new('billing_profile_id')
            ->setChoices($fieldsData['billing_profiles'])->setRequired(false);
        $billingProfile = AssociationField::new('billingProfile');
        $tenantId = ChoiceField::new('tenant_id')
            ->setChoices($fieldsData['tenants'])->setRequired(false);
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$billingProfile, $tenant, $usageTypeName, $threshold, $unitPrice];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$usageTypeName, $threshold, $unitPrice, $billingProfile, $tenant];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$billingProfileId, $tenantId, $usageTypeChoice, $threshold, $unitPrice];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$threshold, $unitPrice];
        }

        return [];
    }

    private function prepareDataForDropdowns(): array
    {
        $tenantEntities = $this->managerRegistry->getRepository(Company::class)->findAll();
        $billingProfilesEntities = $this->managerRegistry->getRepository(BillingProfile::class)->findAll();

        $tenants = [];
        foreach ($tenantEntities as $tenant) {
            $tenants[$tenant->getId() . ' - ' . $tenant->getName()] = $tenant->getId();
        }

        $billingProfiles = [];
        foreach ($billingProfilesEntities as $billingProfile) {
            $billingProfiles[$billingProfile->getId() . ' - ' .$billingProfile->getName()] = $billingProfile->getId();
        }

        return [
            'tenants' => $tenants,
            'billing_profiles' => $billingProfiles,
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('tenant_id', 'Tenant ID'))
            ->add(ChoiceFilter::new('usage_type', 'Usage Type')->setChoices(self::USAGE_TYPES))
            ->add(NumericFilter::new('threshold', 'Threshold'))
            ->add(NumericFilter::new('unit_price', 'Unit Price'));
    }

    public function createEntity(string $entityFqcn): UsagePricingPlan
    {
        $pricingPlan = new UsagePricingPlan();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = (int) $request->query->get('tenant_id', $request->request->all('UsagePricingPlan')['tenant_id'] ?? null);
        if ($tenantId > 0) {
            $pricingPlan->setTenantId($tenantId);
        }

        $billingProfileId = (int) $request->query->get('billing_profile_id', $request->request->all('UsagePricingPlan')['billing_profile_id'] ?? null);
        if ($billingProfileId > 0) {
            $pricingPlan->setBillingProfileId($billingProfileId);
        }

        return $pricingPlan;
    }

    public function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'];

        if (Action::SAVE_AND_RETURN === $submitButtonName) {
            /** @var UsagePricingPlan $pricingPlan */
            $pricingPlan = $context->getEntity()->getInstance();
            if ($billingProfileId = $pricingPlan->getBillingProfileId()) {
                return $this->redirectToBillingProfile($billingProfileId);
            } elseif ($tenantId = $pricingPlan->getTenantId()) {
                return $this->redirectToCompany($tenantId);
            }
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    protected function redirectToBillingProfile(int $billingProfileId): RedirectResponse
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->get(AdminUrlGenerator::class);

        return $this->redirect(
            $adminUrlGenerator
                ->setController(BillingProfileCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($billingProfileId)
                ->generateUrl()
        );
    }

    public function delete(AdminContext $context): Response
    {
        /** @var Response $response */
        $response = parent::delete($context);

        // redirect to owner page
        /** @var UsagePricingPlan $pricingPlan */
        $pricingPlan = $context->getEntity()->getInstance();

        if ($billingProfileId = $pricingPlan->getBillingProfileId()) {
            return $this->redirectToBillingProfile($billingProfileId);
        } elseif ($tenantId = $pricingPlan->getTenantId()) {
            return $this->redirectToCompany($tenantId);
        }

        return $response;
    }
}
