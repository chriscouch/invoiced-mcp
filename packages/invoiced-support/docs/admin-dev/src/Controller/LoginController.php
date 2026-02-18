<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'login', methods: ['GET'])]
    public function attemptLogin(AuthenticationUtils $authenticationUtils, string $environment): Response
    {
        if ('prod' == $environment) {
            return $this->redirectToRoute('saml_login');
        }
        /* Get the last username entered */
        $lastUsername = $authenticationUtils->getLastUsername();
        /* Get any authentication errors */
        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('login/login.html.twig', [
            'lastUsername' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/signed_out', name: 'signed_out', methods: ['GET'])]
    public function signedOut(): Response
    {
        return $this->render('login/signed_out.html.twig');
    }

    #[Route(path: '/admin/attempt', name: 'login_check', methods: ['POST'])]
    #[Route(path: '/admin/saml', name: 'saml_check', methods: ['GET'])]
    public function loginCheck(): Response
    {
        throw new \RuntimeException('You must configure the check path to be handled by the firewall.');
    }

    #[Route(path: '/admin/logout', name: 'logout', methods: ['GET'])]
    public function logout(): Response
    {
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }
}
