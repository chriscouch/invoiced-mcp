<?php

namespace App\EntryPoint\Controller;

use App\Sending\Email\Libs\EmailOpenTracker;
use App\Sending\Email\ValueObjects\TrackingPixel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailTrackingController extends AbstractController
{
    #[Route(path: '/email/open/{id}', name: 'email_open', schemes: '%app.protocol%', host: '%app.domain%', methods: ['GET'])]
    public function open(EmailOpenTracker $tracker, string $id): Response
    {
        $pixel = new TrackingPixel($id);
        $tracker->recordOpen($pixel);

        return $pixel->buildResponse();
    }
}
