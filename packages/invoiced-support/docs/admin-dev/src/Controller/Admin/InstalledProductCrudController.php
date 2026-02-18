<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\InstalledProduct;
use App\Entity\Invoiced\Product;
use App\Service\CsAdminApiClient;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InstalledProductCrudController extends AbstractCrudController
{
    use ReturnToCompanyTrait;
    use CrudControllerTrait;

    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator,
        private CsAdminApiClient $csAdminApiClient,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return InstalledProduct::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Installed Products')
            ->setEntityLabelInSingular('Installed Product')
            ->setSearchFields(['id', 'tenant.name'])
            ->setDefaultSort(['id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'edit')
            ->setPermission('delete', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        $tenant = AssociationField::new('tenant');
        $product = AssociationField::new('product');
        $installedOn = DateTimeField::new('installed_on');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $product, $installedOn];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$tenant, $product, $installedOn];
        }

        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('tenant_id', 'Tenant ID'))
            ->add(EntityFilter::new('product', 'Product'));
    }

    public function delete(AdminContext $context): Response
    {
        /** @var Response $response */
        $response = parent::delete($context);

        // redirect to tenant page
        /** @var InstalledProduct $installedProduct */
        $installedProduct = $context->getEntity()->getInstance();

        if (!$installedProduct->getTenantId()) {
            return $response;
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($installedProduct->getTenantId())
                ->generateUrl()
        );
    }

    public function addProduct(AdminContext $context, AdminUrlGenerator $adminUrlGenerator, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager('Invoiced_ORM');
        /** @var Company $company */
        $company = $em->getRepository(Company::class)->find($request->query->get('tenant_id'));

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $company);

        $installedIds = [];
        foreach ($company->getInstalledProducts() as $installedProduct) {
            $installedIds[] = $installedProduct->getProduct()->getId();
        }

        $form = $this->createFormBuilder()
            ->add('product', EntityType::class, [
                'label' => 'Product',
                'class' => Product::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) use ($installedIds): QueryBuilder {
                    $qb = $er->createQueryBuilder('p')
                        ->orderBy('p.name', 'ASC');
                    if ($installedIds) {
                        $qb->andWhere('p.id NOT IN ('.implode(',', $installedIds).')');
                    }

                    return $qb;
                },
                'required' => false,
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Product $product */
            $product = $form->get('product')->getData();
            $response = $this->csAdminApiClient->request('/_csadmin/install_product', [
                'tenant_id' => $company->getId(),
                'product' => $product->getId(),
            ]);

            if (isset($response->error)) {
                $this->addFlash('danger', 'Adding the product failed: '.$response->error);
            } else {
                $this->addAuditEntry('add_product', $company->getId().'; product='.$product->getName());
                $this->addFlash('success', 'The '.$product->getName().' product has been added');
            }

            return $this->redirect(
                $adminUrlGenerator
                    ->setController(CompanyCrudController::class)
                    ->setAction('detail')
                    ->setEntityId($company->getId())
                    ->generateUrl()
            );
        }

        return $this->render('customizations/actions/add_product.html.twig', [
            'companyId' => $company->getId(),
            'companyName' => $company->getName(),
            'form' => $form->createView(),
        ]);
    }
}
