<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Sending\Email\Libs\SesWebhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class AwsSesController extends AbstractController
{
    #[Route(path: '/ses/webhook', name: 'ses_webhook', methods: ['POST'])]
    public function sesWebhook(Request $request, SesWebhook $webhook): Response
    {
        $webhook->handle($request);

        return new Response('');
    }
}
