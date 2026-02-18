<?php

namespace App\Controller;

use App\Controller\Admin\PurchasePageCrudController;
use App\Entity\Forms\NewPurchasePage;
use App\Entity\Invoiced\BillingProfile;
use App\Entity\Invoiced\Company;
use App\Form\NewPurchasePageType;
use App\Service\PurchasePageCreator;
use Doctrine\Common\Collections\ArrayCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class NewPurchasePageController extends AbstractController
{
    private function getForm(Request $request, ?BillingProfile $billingProfile, ?Company $company, int $reason): FormInterface
    {
        if (!$billingProfile) {
            throw new Exception('Company missing billing profile');
        }

        $page = new NewPurchasePage();
        $page->setBillingProfile($billingProfile);
        $page->setTenant($company);
        $page->setReason($reason);
        $page->setProductPricingPlans(new ArrayCollection([[]]));

        if ($company) {
            $page->setCountry((string) $company->getCountry());
        }

        $form = $this->createForm(NewPurchasePageType::class, $page, [
            'existing_tenant' => null != $company,
        ]);

        $form->handleRequest($request);

        return $form;
    }

    #[Route(path: '/admin/new_purchase_page', name: 'new_purchase_page')]
    public function newPurchasePage(Request $request, PurchasePageCreator $purchasePageCreator, UserInterface $user, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $billingProfile = null;
        if ($billingProfileId = $request->query->get('billing_profile')) {
            $repository = $this->getDoctrine()->getRepository(BillingProfile::class);
            /** @var BillingProfile $billingProfile */
            $billingProfile = $repository->find($billingProfileId);
        }

        $reason = 1; // New Company
        $company = null;
        if ($tenantId = $request->query->get('tenant_id')) {
            $repository = $this->getDoctrine()->getRepository(Company::class);
            /** @var Company $company */
            $company = $repository->find($tenantId);
            $billingProfile = $company->getBillingProfile();
            if ($company->isCanceled()) {
                $reason = 4; // Reactivate
            } elseif ($billingProfile?->getBillingSystem()) {
                $reason = 3; // Upgrade
            } else {
                $reason = 2; // Activate
            }
        }

        $reasonName = array_search($reason, PurchasePageCrudController::REASONS);

        $form = $this->getForm($request, $billingProfile, $company, $reason);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var NewPurchasePage $newPurchasePage */
            $newPurchasePage = $form->getData();

            try {
                $page = $purchasePageCreator->create($newPurchasePage, $user);
            } catch (Exception $error) {
                $form->addError(new FormError($error->getMessage()));

                return $this->render('new_purchase_page/index.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            return $this->redirect(
                $adminUrlGenerator->setController(PurchasePageCrudController::class)
                    ->setAction(Action::DETAIL)
                    ->setEntityId($page->getId())
                    ->generateUrl()
            );
        }

        return $this->render('new_purchase_page/index.html.twig', [
            'billingProfile' => $billingProfile,
            'company' => $company,
            'reasonName' => $reasonName,
            'form' => $form->createView(),
        ]);
    }
}
