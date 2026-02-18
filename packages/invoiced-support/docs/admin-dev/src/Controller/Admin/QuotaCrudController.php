<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\Quota;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class QuotaCrudController extends AbstractCrudController
{
    use ReturnToCompanyTrait;

    private const QUOTAS = [
        'Users' => 1,
        'Transactions per Day' => 2,
        'New Company Limit' => 3,
        'Max Open Network Invitations' => 4,
        'Max Document Versions' => 5,
        'Vendor Pay Daily Limit' => 6,
        'Customer Email Daily Limit' => 7,
    ];

    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Quota::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Quotas')
            ->setSearchFields(['id', 'quota_type', 'limit', 'tenant.name'])
            ->setDefaultSort(['quota_type' => 'ASC', 'tenant_id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail');
    }

    public function configureFields(string $pageName): iterable
    {
        $quota = ChoiceField::new('quota_type', 'Quota')
            ->setChoices(self::QUOTAS);
        $limit = IntegerField::new('limit');
        $tenantId = IntegerField::new('tenant_id');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $quota, $limit];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$quota, $limit, $tenant];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $quota, $quota, $limit];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$limit];
        }

        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('tenant_id', 'Tenant ID'))
            ->add(ChoiceFilter::new('quota_type', 'Quota')->setChoices(self::QUOTAS))
            ->add(NumericFilter::new('limit', 'Limit'));
    }

    public function createEntity(string $entityFqcn): Quota
    {
        $quota = new Quota();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = (int) $request->query->get('tenant_id');
        if ($tenantId > 0) {
            $quota->setTenantId($tenantId);
        }

        return $quota;
    }

    public function delete(AdminContext $context): Response
    {
        parent::delete($context);

        // redirect to tenant page
        /** @var Quota $quota */
        $quota = $context->getEntity()->getInstance();

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($quota->getTenantId())
                ->generateUrl()
        );
    }
}
