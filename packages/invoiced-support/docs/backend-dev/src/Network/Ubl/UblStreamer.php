<?php

namespace App\Network\Ubl;

use Symfony\Component\HttpFoundation\Response;

class UblStreamer
{
    /**
     * Streams the UBL document.
     */
    public function stream(string $xml, string $filename): Response
    {
        return new Response($xml, 200, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'public, must-revalidate, max-age=0',
            'Pragma' => 'public',
            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Content-Length' => strlen($xml),
            'Content-Disposition' => 'attachment; filename="'.$filename.'";',
        ]);
    }
}
