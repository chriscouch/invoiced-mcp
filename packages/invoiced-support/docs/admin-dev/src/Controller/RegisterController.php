<?php

namespace App\Controller;

use App\Controller\Admin\CsUserCrudController;
use App\Entity\CustomerAdmin\User;
use App\Form\RegisterType;
use App\Security\RandomString;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{
    #[Route(path: '/register', name: 'register')]
    public function register(Request $request, UserPasswordHasherInterface $encoder, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $routeName = $request->get('_route');
        $newUser = new User();
        $newPassword = RandomString::generate(47, RandomString::CHAR_ALPHA).RandomString::generate(8, RandomString::CHAR_NUMERIC).RandomString::generate(8, '!@#$%^&*');
        $newUser->setPassword($newPassword);
        $form = $this->createForm(RegisterType::class, $newUser);
        if ('register' == $routeName) {
            $templatePath = 'register/initial_register.html.twig';
            if ($this->hasAdmin()) {
                return new RedirectResponse('/admin/register');
            }
        } else {
            $templatePath = 'register/new_user.html.twig';
            if ('administrator' != $this->getAdmin()->getRole()) {
                return new RedirectResponse($this->generateUrl('access_denied'));
            }

            $form->add('role', ChoiceType::class, [
                'choices' => [
                    'Administrator' => 'administrator',
                    'Customer Support' => 'cs',
                    'Marketing' => 'marketing',
                    'Sales' => 'sales',
                ],
                'placeholder' => 'Click To Select User Role',
                'label' => false,
                'attr' => ['autocomplete' => 'off',
                'error_bubbling' => true, ], ]);
        }
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $email = $newUser->getEmail();

            if ($this->userExists($email)) {
                $error = new FormError('The email address provided is already registered.');
                $form->addError($error);
            } else {
                if ('register' == $routeName) {
                    $newUser->setRole('administrator');
                }
                $password = $encoder->hashPassword($newUser, (string) $newUser->getPassword());
                $newUser->setPassword($password);

                $entityManager = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
                $entityManager->persist($newUser);
                $entityManager->flush();

                $this->addFlash('success', 'User was created for '.$newUser->getEmail().'. Password: '.$newPassword);

                return $this->redirect(
                    $adminUrlGenerator->setController(CsUserCrudController::class)
                        ->setAction('index')
                        ->generateUrl()
                );
            }
        }

        return $this->render($templatePath, ['form' => $form->createView()]);
    }

    /**
     * Checks if the database has any current administrators (users).
     *
     * @return bool - whether or not there are current administrators registered in DB
     */
    private function hasAdmin(): bool
    {
        $repository = $this->getDoctrine()->getRepository(User::class);
        $admins = $repository->findAll();

        return count($admins) > 0;
    }

    /**
     * Checks if there is a user registered with given email.
     *
     * @param string $email - Email from form. Being checked if in database
     *
     * @return bool - whether or not the email is already registered
     */
    private function userExists(string $email): bool
    {
        $repository = $this->getDoctrine()->getRepository(User::class);
        $data = ['email' => $email];

        return null !== $repository->findOneBy($data);
    }

    /**
     * Returns logged in user (used to check if logged in user is
     * administrator when attempting to register a new user to the system)
     * Only administrator can register a new user to the system.
     */
    private function getAdmin(): User
    {
        $currentUser = $this->container->get('security.token_storage')->getToken()->getUser();
        $email = $currentUser->getEmail();

        $repository = $this->getDoctrine()->getRepository(User::class);
        $data = ['email' => $email];

        $user = $repository->findOneBy($data);
        if (!$user instanceof User) {
            throw new Exception('Could not find user');
        }

        return $user;
    }
}
