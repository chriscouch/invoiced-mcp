<?php

namespace App\Controller\Admin;

use App\Entity\CustomerAdmin\Order;
use App\Entity\Invoiced\BillingProfile;
use App\Entity\Invoiced\Product;
use App\Enums\OrderStatus;
use App\Utilities\ChangesetUtility;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Vich\UploaderBundle\Handler\DownloadHandler;

class OrderCrudController extends AbstractCrudController
{
    private const STATUSES = [
        'Missing Info' => 'missing_info',
        'Open' => 'open',
         'Complete' => 'complete',
         'Canceled' => 'canceled',
    ];

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Orders')
            ->setSearchFields(['id', 'customer', 'sales_rep', 'fulfilled_by', 'attachment_name'])
            ->setDefaultSort(['id' => 'DESC'])
            ->overrideTemplate('crud/index', 'customizations/list/order_list.html.twig')
            ->overrideTemplate('crud/detail', 'customizations/show/order_show.html.twig')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $download = Action::new('downloadOrder', 'Download', 'fa fa-fw fa-arrow-down')
            ->linkToCrudAction('downloadOrder');
        $complete = Action::new('completeOrder', 'Mark Complete', 'fa fa-fw fa-check')
            ->linkToCrudAction('completeOrder')
            ->setCssClass('btn text-success')
            ->displayIf(fn (Order $order) => 'open' == $order->getStatus() && !$order->getTypeEnum()->hasNewAccount());
        $cancel = Action::new('cancelOrder', 'Cancel Order', 'fa fa-fw fa-trash-o')
            ->linkToCrudAction('cancelOrder')
            ->setCssClass('btn text-danger')
            ->displayIf(fn ($order) => 'open' == $order->getStatus() || 'missing_info' == $order->getStatus());
        $reopen = Action::new('reopenOrder', 'Reopen Order')
            ->linkToCrudAction('reopenOrder')
            ->displayIf(fn ($order) => 'open' != $order->getStatus() && 'missing_info' != $order->getStatus());

        return $actions
            ->add('index', 'detail')
            ->add('index', $download)
            ->add('detail', $reopen)
            ->add('detail', $cancel)
            ->add('detail', $complete)
            ->add('detail', $download)
            ->remove('detail', 'index')
            ->disable('new', 'delete');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('id')
            ->add('type')
            ->add('customer')
            ->add(DateTimeFilter::new('start_date', 'Start Date'))
            ->add('status');
    }

    public function configureFields(string $pageName): iterable
    {
        $date = DateField::new('date');
        $customer = TextField::new('customer');
        $salesRep = TextField::new('sales_rep');
        $startDate = DateField::new('start_date');
        $dateFulfilled = DateTimeField::new('date_fulfilled');
        $fulfilledBy = TextField::new('fulfilled_by');
        $status = ChoiceField::new('status')
            ->setChoices(self::STATUSES)
            ->setTemplatePath('customizations/fields/order_status.html.twig');
        $panel1 = FormField::addPanel('Order Details');
        $panel2 = FormField::addPanel('Fulfillment');
        $id = IntegerField::new('id', 'ID');
        $formattedType = TextareaField::new('formattedType', 'Type');
        $billingProfileId = IntegerField::new('billingProfileId');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $customer, $formattedType, $startDate, $salesRep, $status];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$panel1, $billingProfileId, $customer, $date, $salesRep, $panel2, $startDate, $status, $dateFulfilled, $fulfilledBy];
        }

        return [];
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_DETAIL === $responseParameters->get('pageName')) {
            /** @var Order $order */
            $order = $responseParameters->get('entity')->getInstance();

            $billingProfile = null;
            if ($id = $order->getBillingProfileId()) {
                $repository = $this->getDoctrine()->getRepository(BillingProfile::class);
                $billingProfile = $repository->find($id);
            }
            $responseParameters->set('billingProfile', $billingProfile);

            $changeset = null;
            if ($newAccount = $order->getNewAccount()) {
                /** @var ObjectManager $manager */
                $manager = $this->getDoctrine()->getManagerForClass(Product::class);
                $changeset = ChangesetUtility::toFriendlyString($newAccount->getChangesetObject(), $manager);
            }
            $responseParameters->set('changeset', $changeset);
        }

        return $responseParameters;
    }

    public function downloadOrder(AdminContext $context, DownloadHandler $downloadHandler): Response
    {
        /** @var Order $order */
        $order = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('show', $order);

        return $downloadHandler->downloadObject($order, 'attachment_file', null, $order->getAttachmentName());
    }

    public function cancelOrder(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var Order $order */
        $order = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $order);

        $order->setStatus(OrderStatus::Canceled->value);
        /** @var EntityManagerInterface $em */
        $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
        $em->persist($order);
        $em->flush();

        return $this->redirect(
            $adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    public function completeOrder(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var Order $order */
        $order = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $order);

        $order->setStatus(OrderStatus::Complete->value);
        $order->setDateFulfilled(CarbonImmutable::now());
        /** @var \App\Entity\CustomerAdmin\User $user */
        $user = $this->getUser();
        $order->setFulfilledBy($user->getUsername());
        $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
        $em->persist($order);
        $em->flush();

        return $this->redirect(
            $adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }

    public function reopenOrder(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var Order $order */
        $order = $context->getEntity()->getInstance();

        // IMPORTANT: verify the user has permission for this operation
        $this->denyAccessUnlessGranted('edit', $order);

        // New account orders require additional information
        if ($order->getTypeEnum()->hasNewAccount() && !$order->getNewAccount()) {
            $order->setStatus(OrderStatus::MissingInfo->value);
        } else {
            $order->setStatus(OrderStatus::Open->value);
        }
        $order->setDateFulfilled(null);
        $order->setFulfilledBy(null);
        $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
        $em->persist($order);
        $em->flush();

        return $this->redirect(
            $adminUrlGenerator->setAction('detail')
                ->generateUrl()
        );
    }
}
