<?php

namespace App\Controller;

use App\Controller\Admin\CompanyCrudController;
use App\Entity\CustomerAdmin\NewAccount;
use App\Entity\Invoiced\BillingProfile;
use App\Form\NewAccountType;
use App\Service\NewCompanyCreator;
use Doctrine\Common\Collections\ArrayCollection;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NewAccountController extends AbstractController
{
    private function getForm(Request $request): FormInterface
    {
        $newAccount = new NewAccount();
        $newAccount->setProductPricingPlans(new ArrayCollection([[]]));

        if ($billingProfileId = $request->query->get('billing_profile')) {
            $repository = $this->getDoctrine()->getRepository(BillingProfile::class);
            $billingProfile = $repository->find($billingProfileId);
            if (!$billingProfile instanceof BillingProfile) {
                throw new Exception('Could not find a billing profile with ID: '.$billingProfileId);
            }
            $newAccount->setBillingProfileId($billingProfile->getId());
        }

        $form = $this->createForm(NewAccountType::class, $newAccount);
        $form->handleRequest($request);

        return $form;
    }

    #[Route(path: '/admin/new_account', name: 'new_account_form')]
    public function newAccountForm(Request $request, NewCompanyCreator $creator, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $form = $this->getForm($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var NewAccount $newAccount */
            $newAccount = $form->getData();

            // Validate selected billing profile
            $repository = $this->getDoctrine()->getRepository(BillingProfile::class);
            $billingProfile = $repository->find($newAccount->getBillingProfileId());
            if (!$billingProfile instanceof BillingProfile) {
                throw new Exception('Could not find a billing profile with ID: '.$newAccount->getBillingProfileId());
            }

            try {
                $result = $creator->create($newAccount);
                $this->addFlash('success', 'Created new account for '.$newAccount->getFirstName().' '.$newAccount->getLastName().' <'.$newAccount->getEmail().'>');

                $url = $adminUrlGenerator->setController(CompanyCrudController::class)
                    ->setAction('detail')
                    ->setEntityId($result->id)
                    ->generateUrl();

                return $this->redirect($url);
            } catch (Exception $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('new_account/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
