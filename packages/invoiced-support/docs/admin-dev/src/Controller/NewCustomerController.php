<?php

namespace App\Controller;

use App\Controller\Admin\BillingProfileCrudController;
use App\Entity\Forms\NewCustomer;
use App\Form\NewCustomerType;
use App\Service\NewCustomerCreator;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

class NewCustomerController extends AbstractController
{
    #[Route(path: '/admin/new_customer', name: 'new_customer_form')]
    public function index(Request $request, UserInterface $user, LoggerInterface $logger, AdminUrlGenerator $adminUrlGenerator, NewCustomerCreator $creator): Response
    {
        $form = $this->getForm($request, $user);
        try {
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var NewCustomer $newCustomer */
                $newCustomer = $form->getData();

                $billingProfile = $creator->create($newCustomer);

                $this->addFlash('success', 'Created billing profile for '.$newCustomer->getName());

                $url = $adminUrlGenerator->setController(BillingProfileCrudController::class)
                    ->setAction('detail')
                    ->setEntityId($billingProfile->getId())
                    ->generateUrl();

                return $this->redirect($url);
            }
        } catch (Throwable $e) {
            $logger->error('Uncaught exception', ['exception' => $e]);
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->render('new_customer/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function getForm(Request $request, UserInterface $user): FormInterface
    {
        $newCustomer = new NewCustomer();
        $newCustomer->setSalesRep((string) $user); /* @phpstan-ignore-line */
        $form = $this->createForm(NewCustomerType::class, $newCustomer);
        $form->handleRequest($request);

        return $form;
    }
}
