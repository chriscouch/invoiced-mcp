<?php

namespace App\Sending\Mail\Interfaces;

use App\Sending\Mail\Exceptions\SendLetterException;
use CommerceGuys\Addressing\Address;
use mikehaertl\tmp\File;

interface AdapterInterface
{
    /**
     * Sends a letter.
     *
     * @throws SendLetterException when the letter cannot be sent
     */
    public function send(Address $from, Address $to, File $pdf, string $description): array;
}
