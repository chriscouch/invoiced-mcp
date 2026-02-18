<?php

namespace App\Sending\Email\Interfaces;

use App\Companies\Models\Company;

interface IsEmailParticipantInterface
{
    public function tenant(): Company;

    public function getName(): string;

    public function getEmail(): ?string;
}
