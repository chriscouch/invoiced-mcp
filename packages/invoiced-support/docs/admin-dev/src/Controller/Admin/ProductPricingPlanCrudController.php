<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\ProductPricingPlan;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ProductPricingPlanCrudController extends AbstractCrudController
{
    use ReturnToCompanyTrait;

    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return ProductPricingPlan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Product Pricing Plans')
            ->setEntityLabelInSingular('Product Pricing Plan')
            ->setSearchFields(['id', 'tenant.name'])
            ->setDefaultSort(['id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->setPermission('new', 'new')
            ->setPermission('edit', 'edit')
            ->setPermission('delete', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        $tenantId = IntegerField::new('tenant_id');
        $tenant = AssociationField::new('tenant');
        $effectiveDate = DateField::new('effective_date');
        $postedOn = DateField::new('posted_on');
        $price = NumberField::new('price');
        $isAnnual = BooleanField::new('annual')->renderAsSwitch(false);
        $product = AssociationField::new('product');
        $customPricing = BooleanField::new('custom_pricing')->renderAsSwitch(false);

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $product, $price, $isAnnual, $customPricing];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$tenant, $product, $effectiveDate, $postedOn, $price, $isAnnual, $customPricing];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $effectiveDate, $product, $price, $isAnnual, $customPricing];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$effectiveDate, $price, $isAnnual, $customPricing];
        }

        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('tenant_id', 'Tenant ID'))
            ->add(EntityFilter::new('product', 'Product'))
            ->add(NumericFilter::new('price', 'Price'))
            ->add(BooleanFilter::new('annual', 'Is Annual'));
    }

    public function createEntity(string $entityFqcn): ProductPricingPlan
    {
        $pricingPlan = new ProductPricingPlan();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = (int) $request->query->get('tenant_id', $request->request->all('ProductPricingPlan')['tenant_id'] ?? null);
        if ($tenantId > 0) {
            $pricingPlan->setTenantId($tenantId);
        }

        return $pricingPlan;
    }

    public function delete(AdminContext $context): Response
    {
        /** @var Response $response */
        $response = parent::delete($context);

        // redirect to tenant page
        /** @var ProductPricingPlan $pricingPlan */
        $pricingPlan = $context->getEntity()->getInstance();

        if (!$pricingPlan->getTenantId()) {
            return $response;
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($pricingPlan->getTenantId())
                ->generateUrl()
        );
    }
}
