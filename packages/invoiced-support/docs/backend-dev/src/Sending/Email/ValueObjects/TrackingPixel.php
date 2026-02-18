<?php

namespace App\Sending\Email\ValueObjects;

use App\Core\Utils\AppUrl;
use App\Sending\Email\Libs\EmailHtml;
use App\Core\Utils\InfuseUtility as Utility;
use Symfony\Component\HttpFoundation\Response;

class TrackingPixel implements \Stringable
{
    private string $id;

    public function __construct(?string $id = null)
    {
        if (!$id) {
            $id = strtolower(Utility::guid(false));
        }

        $this->id = $id;
    }

    public function __toString(): string
    {
        return EmailHtml::trackingPixel($this->getOpenUrl());
    }

    /**
     * Gets the id.
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getOpenUrl(): string
    {
        return AppUrl::get()->build().'/email/open/'.$this->id;
    }

    /**
     * Builds the response, a transparent 1px image.
     */
    public function buildResponse(): Response
    {
        ignore_user_abort(true);

        // this is a 1px transparent GIF
        $img = base64_decode('R0lGODlhAQABAID/AMDAwAAAACH5BAEAAAAALAAAAAABA‌​AEAAAICRAEA');

        // make sure not to allow caching of the response
        $headers = [
            'Content-Type' => 'image/gif',
            'Content-encoding' => 'none',
            'Content-Length' => strlen($img),
            'Cache-Control' => 'private, no-cache, no-cache=Set-Cookie, proxy-revalidate',
            'Expires' => 'Wed, 11 Jan 2000 12:59:00 GMT',
            'Last-Modified' => 'Wed, 11 Jan 2006 12:59:00 GMT',
            'Pragma' => 'no-cache',
        ];

        return new Response($img, 200, $headers);
    }
}
