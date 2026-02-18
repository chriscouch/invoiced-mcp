<?php

namespace App\Sending\Email\Interfaces;

use App\Sending\Email\Models\EmailTemplate;

/**
 * This interface provides a contract for generating the variables
 * generated into email templates, given a model like an Invoice.
 * Classes implementing this interface will only work with a
 * specific type of model.
 */
interface EmailVariablesInterface
{
    /**
     * Generates the email variables to be injected into the
     * given email for the model this class represents.
     */
    public function generate(EmailTemplate $template): array;

    /**
     * Gets the currency associated with this email.
     */
    public function getCurrency(): string;
}
