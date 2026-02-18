<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Core\Mailer\Mailer;
use App\Sending\Email\Exceptions\InboundParseException;
use App\Sending\Email\InboundParse\Router;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class SendGridController extends AbstractController
{
    #[Route(path: '/sendgrid/inbound', name: 'sendgrid_inbound', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendGridInbound(Request $request, Mailer $mailer, Router $router): Response
    {
        $envelope = json_decode((string) $request->request->get('envelope'));
        $to = (string) $envelope->to[0];

        try {
            $handler = $router->route($to);
        } catch (InboundParseException $e) {
            $router->notifyAboutException($mailer, $to, (string) $request->request->get('from'), (string) $request->request->get('subject'), $e);

            return new Response($e->getMessage());
        }

        try {
            $handler->processEmail($request);
        } catch (InboundParseException $e) {
            $router->notifyAboutException($mailer, $to, (string) $request->request->get('from'), (string) $request->request->get('subject'), $e);

            return new Response($e->getMessage());
        }

        return new Response('Inbound email processed');
    }
}
