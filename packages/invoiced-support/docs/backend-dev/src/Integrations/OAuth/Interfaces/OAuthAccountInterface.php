<?php

namespace App\Integrations\OAuth\Interfaces;

use App\Integrations\OAuth\OAuthAccessToken;

interface OAuthAccountInterface
{
    /**
     * Gets an OAuth access token from the model.
     */
    public function getToken(): OAuthAccessToken;

    /**
     * Sets the token on the model. Do not save the model
     * until persistOAuth() is called.
     */
    public function setToken(OAuthAccessToken $token): void;

    /**
     * Saves the account model to the database.
     */
    public function persistOAuth(): void;

    /**
     * Deletes the account model in the database.
     */
    public function deleteOAuth(): void;
}
