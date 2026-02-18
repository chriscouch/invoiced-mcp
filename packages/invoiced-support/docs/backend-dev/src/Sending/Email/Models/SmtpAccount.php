<?php

namespace App\Sending\Email\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * @property string      $host
 * @property string      $username
 * @property string      $password
 * @property string      $password_enc
 * @property int         $port
 * @property string      $encryption
 * @property string      $auth_mode
 * @property bool        $fallback_on_failure
 * @property string|null $last_error_message
 * @property int|null    $last_error_timestamp
 * @property bool|null   $last_send_successful
 */
class SmtpAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'host' => new Property(
                required: true,
            ),
            'username' => new Property(
                required: true,
            ),
            'password_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'port' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'encryption' => new Property(
                required: true,
            ),
            'auth_mode' => new Property(
                required: true,
            ),
            'fallback_on_failure' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'last_error_message' => new Property(
                null: true,
            ),
            'last_error_timestamp' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'last_send_successful' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    /**
     * Sets the `password` property by encrypting it
     * and storing it on `password_enc`.
     *
     * @return mixed token
     */
    protected function setPasswordValue(string $secret)
    {
        if ($secret) {
            $this->password_enc = $secret;
        }

        return $secret;
    }

    /**
     * Gets the decrypted `password` property value.
     *
     * @param mixed $secret current value
     *
     * @return mixed decrypted token
     */
    protected function getPasswordValue($secret)
    {
        if ($secret || !$this->password_enc) {
            return $secret;
        }

        return $this->password_enc;
    }

    public function toDsn(): Dsn
    {
        return new Dsn(
            // When SSL is used then we must use "smtps". Otherwise,
            // STARTTLS will be tried on the connetion.
            scheme: 'ssl' == $this->encryption ? 'smtps' : 'smtp',
            host: $this->host,
            user: $this->username,
            password: $this->password,
            port: $this->port,
        );
    }

    public static function fromDsn(Dsn $dsn): self
    {
        $smtpAccount = new SmtpAccount();
        $smtpAccount->encryption = 'smtps' == $dsn->getScheme() ? 'ssl' : 'tls';
        $smtpAccount->host = $dsn->getHost();
        $smtpAccount->username = (string) $dsn->getUser();
        $smtpAccount->password = (string) $dsn->getPassword();
        $smtpAccount->port = (int) $dsn->getPort();

        return $smtpAccount;
    }
}
