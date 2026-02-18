<?php

namespace App\EntryPoint\Controller;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthConnectionManager;
use App\Integrations\OAuth\OAuthIntegrationFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class OAuthController extends AbstractController
{
    #[Route(path: '/oauth/{id}/start', name: 'oauth_start', methods: ['GET'])]
    public function oAuthStart(string $dashboardUrl, OAuthConnectionManager $oauthManager, OAuthIntegrationFactory $factory, string $id): Response
    {
        try {
            return $oauthManager->start($factory->get($id));
        } catch (OAuthException $e) {
            return $this->render('integrations/error.twig', [
                'title' => 'Connection Error',
                'message' => $e->getMessage(),
                'returnUrl' => $dashboardUrl,
            ]);
        }
    }

    #[Route(path: '/oauth/{id}/connect', name: 'oauth_finish', methods: ['GET'])]
    public function oAuthFinish(string $dashboardUrl, OAuthConnectionManager $oauthManager, OAuthIntegrationFactory $factory, string $id): Response
    {
        try {
            return $oauthManager->handleAccessToken($factory->get($id));
        } catch (OAuthException $e) {
            return $this->render('integrations/error.twig', [
                'title' => 'Connection Error',
                'message' => $e->getMessage(),
                'returnUrl' => $dashboardUrl,
            ]);
        }
    }

    #[Route(path: '/oauth/{id}/disconnect', name: 'oauth_disconnect', methods: ['GET'])]
    public function oAuthDisconnect(OAuthConnectionManager $oauthManager, OAuthIntegrationFactory $factory, string $id): Response
    {
        return $oauthManager->disconnect($factory->get($id));
    }
}
