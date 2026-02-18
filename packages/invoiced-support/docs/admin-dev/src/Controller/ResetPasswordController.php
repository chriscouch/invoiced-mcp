<?php

namespace App\Controller;

use App\Entity\CustomerAdmin\User;
use App\Form\ResetPasswordType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ResetPasswordController extends AbstractController
{
    #[Route(path: 'forgot/find_by_email', name: 'find_by_email')]
    public function initialResetRequest(): Response
    {
        return $this->render('reset_password/find_account.html.twig');
    }

    #[Route(path: 'forgot/verify', name: 'reset_password_verification')]
    public function checkForUser(Request $request, UserPasswordHasherInterface $encoder, MailerInterface $mailer): Response
    {
        $referer = $request->headers->get('Referer');
        /* Route to email form if no referer or csrf token */
        if (!isset($referer)) {
            return new RedirectResponse($this->generateUrl('find_by_email'));
        }
        $session = $request->getSession();
        $repository = $this->getDoctrine()->getRepository(User::class);
        /* If _email parameter is set (request coming from route 'find_by_email') then use parameter to find User
         * Otherwise retrieve email from session storage. */
        if (null !== $request->request->get('_email')) {
            $email = $request->request->get('_email');
        } else {
            $email = $session->get('reset_email');
        }
        /* Find user by email */
        $user = $repository->findOneBy(['email' => $email]);
        if ($user) {
            /* Reset password */
            $tempPassword = $this->generateTempPassword();
            $encodedPassword = $encoder->hashPassword($user, $tempPassword);
            $user->setPassword($encodedPassword);

            /* Update user entity in DB */
            $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
            $em->persist($user);
            $em->flush();

            /* Send temporary password to User's email */
            $this->sendPasswordResetEmail($request, $mailer, $user, $tempPassword);

            /* Clear session storage */
            $session->clear();

            return new RedirectResponse($this->generateUrl('login'));
        }

        return $this->render('reset_password/find_account.html.twig', [
            'error' => 'The specified email is not registered.',
        ]);
    }

    #[Route(path: 'admin/reset_password', name: 'new_password')]
    public function setNewPassword(Request $request, UserPasswordHasherInterface $encoder): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        /* DO NOT MOVE THE LINE BELOW THIS COMMENT! Storing current password from token has to be done before handling the form request. */
        $currentPassword = (string) $user->getPassword();
        $form = $this->createForm(ResetPasswordType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /* Input form 'old password' field */
            $currentPassAttempt = (string) $request->request->get('_old_password');
            if (password_verify($currentPassAttempt, $currentPassword)) {
                $newPassword = $encoder->hashPassword($user, (string) $user->getPassword());
                $user->setPassword($newPassword);

                $em = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
                $em->persist($user);
                $em->flush();

                return $this->redirectToRoute('index');
            }

            $error = new FormError('The old password entered is invalid.');
            $form->addError($error);
        }
        /*
         * If the form is invalid, the password for the user stored
         * in the token needs to be changed back to its original password.
         * Line 43 changes the password after handling the form request.
         * Removing the 73 will invalidate credentials after invalid form request
         */
        $user->setPassword($currentPassword);

        return $this->render('reset_password/reset_password.html.twig', ['form' => $form->createView()]);
    }

    // ======================================================================
    //	PASSWORD RESET AUTHENTICATION HELPER FUNCTIONS
    // ======================================================================

    /**
     * Sends email to user with new password.
     *
     * @param Request $request      - request to the route calling this function. Used to get base url.
     * @param User    $user         - the current user whose password has been reset
     * @param string  $tempPassword - the new password for the user
     */
    private function sendPasswordResetEmail(Request $request, MailerInterface $mailer, User $user, string $tempPassword): void
    {
        $baseUrl = $request->getHttpHost();
        $resetLink = 'https://'.$baseUrl.$this->generateUrl('new_password');
        $message = 'Please visit '.$resetLink.' to reset your password.'."\n\nYour new temporary password is: ".$tempPassword;

        $email = (new Email())
            ->to(new Address($user->getEmail(), $user->getFirstName()))
            ->priority(Email::PRIORITY_HIGH)
            ->subject("We've Reset Your Password!")
            ->text($message);

        $mailer->send($email);
    }

    /**
     * Generates random password.
     */
    private function generateTempPassword(): string
    {
        $isValid = 0;
        $password = '';
        $lengthRequirement = 16;

        $requirements = [
                range(0, 9),
                ['!', '?', '&', '@', '*', '(', ')'],
                range('a', 'z'),
                range('A', 'Z'),
            ];

        while ($isValid < 15 || strlen($password) < $lengthRequirement) {
            $i = mt_rand(0, count($requirements) - 1);
            $j = mt_rand(0, count($requirements[$i]) - 1);
            $password .= $requirements[$i][$j];
            $isValid |= (1 << $i);
        }

        return $password;
    }
}
