<?php

namespace App\Integrations\Libs;

/**
 * Encrypts a company ID that will be passed through to
 * providers checkout process and returned in a callback.
 */
class IpnContext
{
    const CIPHER = 'AES-128-CBC';

    public function __construct(private string $paypalEncryptSecret)
    {
    }

    /**
     * Encodes a company ID.
     */
    public function encode(int $companyId): string
    {
        // iv
        $ivlen = (int) openssl_cipher_iv_length(self::CIPHER);
        $iv = (string) openssl_random_pseudo_bytes($ivlen);

        // encrypt
        // borrowed from http://php.net/manual/en/function.openssl-encrypt.php
        $ciphertext_raw = (string) openssl_encrypt((string) $companyId, self::CIPHER, $this->paypalEncryptSecret, $options = OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $this->paypalEncryptSecret, true);

        return base64_encode($iv.$hmac.$ciphertext_raw);
    }

    /**
     * Decodes a previously encoded company ID.
     */
    public function decode(string $encrypted): string
    {
        // decrypt
        // borrowed from http://php.net/manual/en/function.openssl-encrypt.php
        $c = (string) base64_decode($encrypted);
        $ivlen = (int) openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($c, 0, $ivlen);
        $sha2len = 32;
        $ciphertext_raw = substr($c, $ivlen + $sha2len);

        return (string) openssl_decrypt($ciphertext_raw, self::CIPHER, $this->paypalEncryptSecret, OPENSSL_RAW_DATA, $iv);
    }
}
