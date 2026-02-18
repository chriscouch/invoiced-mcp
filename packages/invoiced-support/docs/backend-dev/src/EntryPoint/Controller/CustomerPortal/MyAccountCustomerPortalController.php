<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\CustomerPortalAttachment;
use App\Core\I18n\PhoneFormatter;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CardFactory;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use Doctrine\DBAL\Connection;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class MyAccountCustomerPortalController extends AbstractCustomerPortalController
{
    #[Route(path: '/account', name: 'account', methods: ['GET'])]
    public function account(CardFactory $cardFactory, Request $request): Response
    {
        // we clean up session data that might be left over from previous payment attempts
        $request->getSession()->set('payment_form_return', '');
        $portal = $this->customerPortalContext->getOrFail();
        $customer = $portal->getSignedInCustomer();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        // load the card layout
        $cardLayout = $cardFactory->getCardLayout();
        $cardData = $cardFactory->makeCardData($cardLayout, $portal);

        return $this->render('customerPortal/account.twig', [
            'cardLayout' => $cardLayout,
            'cardData' => $cardData,
        ]);
    }

    #[Route(path: '/billingInfo/{id}', name: 'billing_info_form', methods: ['GET'])]
    public function billingInfo(Request $request, string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = Customer::findClientId($id);
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        $countries = $this->getCountries();

        $backUrl = null;
        $redirectType = $request->query->get('r');
        $redirectTarget = $request->query->get('t');
        if ('estimate' == $redirectType) {
            $backUrl = '/estimates/'.$redirectTarget;
        }

        $contacts = Contact::where('customer_id', $customer->id)
            ->with('role')
            ->all();

        $showAccountNumber = $portal->company()->defaultTheme()->show_customer_no;

        return $this->render('customerPortal/billingDetails.twig', [
            'billingInfoTab' => true,
            'countries' => $countries,
            'customer' => $customer,
            'showAccountNumber' => $showAccountNumber,
            'r' => $redirectType,
            't' => $redirectTarget,
            'backUrl' => $backUrl,
            'contacts' => array_map(function (Contact $contact) {
                $arr = $contact->toArray();
                $arr['address'] = $contact->address;
                $arr['phone'] = PhoneFormatter::format(
                    (string) $contact->phone,
                    $contact->country,
                );

                return $arr;
            }, $contacts->toArray()),
        ]);
    }

    #[Route(path: '/billingInfo/{id}', name: 'update_billing_info', methods: ['POST'])]
    public function updateBillingInfo(Request $request, string $id, CustomerPortalEvents $events, Connection $database): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = Customer::findClientId($id);
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_billing_info', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $update = [
            'email',
            'phone',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'country',
        ];
        $values = [];
        foreach ($update as $key) {
            $values[$key] = $request->request->get($key);
        }

        // Unset state which is a dropdown, if there is no address line 1
        if (!$values['address1']) {
            $values['state'] = null;
        }

        $success = $customer->set($values);

        if (!$success) {
            $database->setRollbackOnly();
            foreach ($customer->getErrors()->all() as $error) {
                $this->addFlash('billing_details_error', $error);
            }

            return $this->billingInfo($request, $id);
        }

        // track the event
        $events->track($customer, CustomerPortalEvent::UpdateContactInfo);
        $this->statsd->increment('billing_portal.update_contact_info');

        $redirectType = $request->request->get('r');
        if ('estimate' == $redirectType) {
            $target = $request->request->get('t');

            return new RedirectResponse('/estimates/'.$target);
        }

        $this->addFlash('billing_details_success', 'messages.billing_details_updated');

        return $this->redirectToRoute(
            'customer_portal_billing_info_form',
            [
                'subdomain' => $portal->company()->getSubdomainUsername(),
                'id' => $customer->client_id,
            ]
        );
    }

    #[Route(path: '/contacts/{id}', name: 'update_contact', defaults: ['id' => null], methods: ['GET', 'POST'])]
    public function updateContact(Request $request, CustomerPortalEvents $events, ?string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = $portal->getSignedInCustomer();
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        $contact = $id ? Contact::findOrFail($id) : new Contact([
            'country' => $customer->country,
        ]);
        $contact->customer = $customer;

        $countries = $this->getCountries();
        $countryChoices = [];
        foreach ($countries as $country) {
            $countryChoices[$country['country']] = $country['code'];
        }

        $form = $this->createFormBuilder(
            $contact,
            [
                'translation_domain' => 'customer_portal',
                'data_class' => Contact::class,
            ])
            ->add('name', TextType::class, [
                'label' => 'labels.name',
            ])
            ->add('phone', TelType::class, [
                'label' => 'labels.phone',
                'label_html' => true,
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'labels.email_address',
                'label_html' => true,
                'required' => false,
            ])
            ->add('address1', TextType::class, [
                'label' => 'labels.address_line1',
                'required' => false,
            ])
            ->add('address2', TextType::class, [
                'label' => 'labels.address_line2',
                'required' => false,
                'attr' => [
                    'placeholder' => 'labels.optional',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'labels.address_city',
                'required' => false,
            ])
            ->add('state', TextType::class, [
                'label' => 'labels.address_state',
                'required' => false,
                'attr' => [
                    'class' => 'form-control state-text',
                ],
                'row_attr' => [
                    'class' => 'nothing',
                ],
            ])
            ->add('postal_code', TextType::class, [
                'label' => 'labels.address_postal_code',
                'required' => false,
            ])
            ->add('country', ChoiceType::class, [
                'label' => 'labels.address_country',
                'required' => false,
                'choices' => $countryChoices,
                'attr' => [
                    'class' => 'form-select country-selector contact',
                    'data-section' => 'contact',
                ],
            ])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact = $form->getData();

            // Unset state which is a dropdown, if there is no address line 1
            if (!$contact->address1) {
                $contact->state = null;
            }

            if ($contact->save()) {
                $this->addFlash('billing_details_success', 'messages.contact_saved');

                // track the event
                $events->track($customer, CustomerPortalEvent::UpdateContactInfo);
                $this->statsd->increment('billing_portal.update_contact_info');

                return $this->redirectToRoute('customer_portal_billing_info_form',
                    [
                        'subdomain' => $portal->company()->getSubdomainUsername(),
                        'id' => $customer->client_id,
                    ]);
            }

            $errors = $contact->getErrors()->all();
        }

        return $this->render('customerPortal/editContact.twig', [
            'form' => $form->createView(),
            'errors' => $errors ?? [],
        ]);
    }

    #[Route(path: '/contacts/{id}/remove', name: 'delete_contact', methods: ['GET'])]
    public function deleteContact(Request $request, CustomerPortalEvents $events, ?string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = $portal->getSignedInCustomer();
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        $contact = Contact::where('customer_id', $customer->id)
            ->where('id', $id)
            ->oneOrNull();
        if ($contact) {
            $contact->deleteOrFail();
            $this->addFlash('billing_details_success', 'messages.contact_deleted');

            // track the event
            $events->track($customer, CustomerPortalEvent::UpdateContactInfo);
            $this->statsd->increment('billing_portal.update_contact_info');
        }

        return $this->redirectToRoute('customer_portal_billing_info_form',
            [
                'subdomain' => $portal->company()->getSubdomainUsername(),
                'id' => $customer->client_id,
            ]);
    }

    #[Route(path: '/attachments/{id}', name: 'attachments', methods: ['GET'])]
    public function attachments(Request $request, string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = Customer::findClientId($id);
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        $attachments = [];
        /** @var CustomerPortalAttachment $attachment */
        foreach (CustomerPortalAttachment::all() as $attachment) {
            $attachments[] = [
                'file' => $attachment->file->toArray(),
                'date' => date($portal->company()->date_format, $attachment['created_at']),
            ];
        }
        foreach (Attachment::allForObject($customer, Attachment::LOCATION_ATTACHMENT, true) as $attachment) {
            $attachments[] = [
                'file' => $attachment->file()->toArray(),
                'date' => date($portal->company()->date_format, $attachment['created_at']),
            ];
        }

        return $this->render('customerPortal/attachments.twig', [
            'billingInfoTab' => true,
            'customer' => $customer,
            'attachments' => $attachments,
        ]);
    }
}
