<?php

namespace App\Controller;

use App\Controller\Admin\OrderCrudController;
use App\Entity\CustomerAdmin\NewAccount;
use App\Entity\CustomerAdmin\Order;
use App\Entity\Invoiced\BillingProfile;
use App\Enums\OrderType;
use App\Event\PostOrderEvent;
use App\Event\PreOrderEvent;
use App\Form\NewAccountType;
use App\Form\NewOrderType;
use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

class NewOrderController extends AbstractController
{
    #[Route(path: '/admin/new_order', name: 'new_order_form')]
    public function index(Request $request, UserInterface $user, EventDispatcherInterface $dispatcher, LoggerInterface $logger, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $form = $this->getForm($request, $user);
        try {
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var Order $order */
                $order = $form->getData();

                // Validate selected billing profile
                $repository = $this->getDoctrine()->getRepository(BillingProfile::class);
                $billingProfile = $repository->find($order->getBillingProfileId());
                if (!$billingProfile instanceof BillingProfile) {
                    throw new Exception('Could not find a billing profile with ID: '.$order->getBillingProfileId());
                }

                // Validate the start date
                if (OrderType::Imported != $order->getTypeEnum() && $order->getStartDate()->isBefore(CarbonImmutable::now()->setTime(0, 0))) {
                    throw new Exception('Start date cannot be in the past');
                }

                $dispatcher->dispatch(new PreOrderEvent($order));

                $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
                $em->persist($order);
                $em->flush();

                // new accounts have a second step to collect parameters
                // new accounts should not dispatch the order finalization event yet
                if ($order->getTypeEnum()->hasNewAccount()) {
                    $this->addFlash('warning', 'Created order for '.$order->getCustomer().'. Now you must fill in the details to provision the account.');
                    $url = $adminUrlGenerator->setRoute('new_account_for_order', ['order' => $order->getId()])
                        ->generateUrl();

                    return $this->redirect($url);
                }

                $dispatcher->dispatch(new PostOrderEvent($order));

                $this->addFlash('success', 'Created order for '.$order->getCustomer());

                $url = $adminUrlGenerator->setController(OrderCrudController::class)
                    ->setAction('detail')
                    ->setEntityId($order->getId())
                    ->generateUrl();

                return $this->redirect($url);
            }
        } catch (Throwable $e) {
            $logger->error('Uncaught exception', ['exception' => $e]);
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->render('new_order/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/admin/new_account_for_order', name: 'new_account_for_order')]
    public function newAccountForm(Request $request, EventDispatcherInterface $dispatcher, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $repository = $this->getDoctrine()->getRepository(Order::class);
        /** @var Order|null $order */
        $order = $repository->find($request->attributes->get('order'));
        if (!$order || 'missing_info' != $order->getStatus()) {
            throw new NotFoundHttpException();
        }
        $form = $this->getNewAccountForm($request, $order);
        if ($form->isSubmitted() && $form->isValid()) {
            // Save the new account parameters in the DB
            /** @var NewAccount $newAccount */
            $newAccount = $form->getData();
            $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
            $em->persist($newAccount);

            // Tie the new account to the order
            $order->setNewAccount($newAccount);
            $em->persist($order);

            $em->flush();

            try {
                $dispatcher->dispatch(new PostOrderEvent($order));
                $this->addFlash('success', 'Finished order for '.$newAccount->getFirstName().' '.$newAccount->getLastName().' <'.$newAccount->getEmail().'>');
            } catch (Exception $e) {
                $this->addFlash('danger', $e->getMessage());
            }

            $url = $adminUrlGenerator->setController(OrderCrudController::class)
                ->setAction('detail')
                ->setEntityId($order->getId())
                ->generateUrl();

            return $this->redirect($url);
        }

        return $this->render('new_account/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function getForm(Request $request, UserInterface $user): FormInterface
    {
        $order = new Order();
        $order->setSalesRep((string) $user); /* @phpstan-ignore-line */

        if ($billingProfileId = $request->query->get('billing_profile')) {
            $repository = $this->getDoctrine()->getRepository(BillingProfile::class);
            $billingProfile = $repository->find($billingProfileId);
            if (!$billingProfile instanceof BillingProfile) {
                throw new Exception('Could not find a billing profile with ID: '.$billingProfileId);
            }
            $order->setBillingProfileId($billingProfile->getId());
            $order->setCustomer($billingProfile->getName());
        }

        $form = $this->createForm(NewOrderType::class, $order);
        $form->handleRequest($request);

        return $form;
    }

    private function getNewAccountForm(Request $request, Order $order): FormInterface
    {
        $newAccount = new NewAccount();
        $newAccount->setProductPricingPlans(new ArrayCollection([[]]));
        $newAccount->setBillingProfileId($order->getBillingProfileId());
        $form = $this->createForm(NewAccountType::class, $newAccount);
        $form->handleRequest($request);

        return $form;
    }
}
