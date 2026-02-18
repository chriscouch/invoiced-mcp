<?php

namespace App\Controller\Admin;

use App\Entity\CustomerAdmin\User;
use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\CompanyNote;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class CompanyNoteCrudController extends AbstractCrudController
{
    use ReturnToCompanyTrait;
    use CrudControllerTrait;

    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return CompanyNote::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Company Notes')
            ->setSearchFields(['note', 'created_by', 'tenant.name'])
            ->setDefaultSort(['created_at' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail');
    }

    public function configureFields(string $pageName): iterable
    {
        $tenantId = IntegerField::new('tenant_id');
        $note = TextareaField::new('note');
        $createdBy = TextField::new('created_by');
        $tenant = AssociationField::new('tenant');
        $createdAt = DateTimeField::new('created_at');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $note, $createdBy, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$tenant, $createdBy, $createdAt, $note];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$tenantId, $note];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$note];
        }

        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('tenant_id')
            ->add('created_by');
    }

    public function createEntity(string $entityFqcn): CompanyNote
    {
        $note = new CompanyNote();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = (int) $request->query->get('tenant_id');
        if ($tenantId > 0) {
            $note->setTenantId($tenantId);
        }

        return $note;
    }

    /**
     * @param CompanyNote $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Company $company */
        $company = $this->getDoctrine()->getRepository(Company::class)->find($entityInstance->getTenantId());
        $entityInstance->setTenant($company);

        /** @var User $user */
        $user = $this->getUser();
        $entityInstance->setCreatedBy($user->getUsername());

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function delete(AdminContext $context): Response
    {
        parent::delete($context);

        // redirect to tenant page
        /** @var CompanyNote $note */
        $note = $context->getEntity()->getInstance();

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($note->getTenantId())
                ->generateUrl()
        );
    }
}
