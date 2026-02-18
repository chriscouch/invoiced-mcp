<?php

namespace App\Webhooks\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\RandomString;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int    $id
 * @property string $url
 * @property string $secret
 * @property string $secret_enc
 * @property bool   $enabled
 * @property array  $events
 * @property bool   $protected
 */
class Webhook extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'url' => new Property(
                required: true,
                validate: 'url',
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'events' => new Property(
                type: Type::ARRAY,
                default: ['*'],
            ),
            'protected' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'secret_enc' => new Property(
                null: true,
                encrypted: true,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'generateSecret']);
    }

    public static function randSecret(): string
    {
        return RandomString::generate(32, RandomString::CHAR_ALNUM);
    }

    public static function generateSecret(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->secret_enc = self::randSecret();
    }

    /**
     * Gets the decrypted `secret` property value.
     *
     * @param mixed $secret current value
     *
     * @return mixed decrypted secret
     */
    protected function getSecretValue(mixed $secret): mixed
    {
        if ($secret) {
            return $secret;
        }

        if ($this->secret_enc) {
            return $this->secret_enc;
        }

        // no secret exists, we must create one
        return $this->getSecret(true);
    }

    /**
     * Gets the secret used to sign requests for this webhook.
     */
    public function getSecret(bool $rollSecret = false): string
    {
        if (!$rollSecret && $secret = $this->secret) {
            return $secret;
        }

        // generate a random secret and save
        $secret = self::randSecret();
        $this->secret_enc = $secret;
        $this->save();

        return $secret;
    }

    /**
     * Disables the webhook.
     */
    public function disable(): void
    {
        $this->enabled = false;
        $this->saveOrFail();
    }
}
