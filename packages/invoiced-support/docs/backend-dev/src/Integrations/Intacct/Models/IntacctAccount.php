<?php

namespace App\Integrations\Intacct\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string      $intacct_company_id
 * @property string      $entity_id
 * @property boolean     $sync_all_entities
 * @property string|null $sender_id
 * @property string|null $sender_password
 * @property string      $user_id
 * @property string      $user_password
 * @property string      $user_password_enc
 * @property string|null $name
 */
class IntacctAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'intacct_company_id' => new Property(
                required: true,
            ),
            'entity_id' => new Property(
                null: true,
            ),
            'sync_all_entities' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'sender_id' => new Property(
                null: true,
            ),
            'sender_password' => new Property(
                null: true,
                encrypted: true,
                in_array: false,
            ),
            'user_id' => new Property(
                required: true,
            ),
            'user_password_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'name' => new Property(
                null: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::saving([self::class, 'validateSenderId']);
    }

    public static function validateSenderId(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ('invoiced' == strtolower((string) $model->sender_id) && !str_starts_with(strtolower($model->intacct_company_id), 'invoiced-dev')) {
            throw new ListenerException('Invalid sender ID: '.$model->sender_id);
        }
    }

    /**
     * Sets the `user_password` property by encrypting it
     * and storing it on `user_password_enc`.
     *
     * @param string $token
     *
     * @return mixed token
     */
    protected function setUserPasswordValue($token)
    {
        if ($token) {
            $this->user_password_enc = $token;
        }

        return $token;
    }

    /**
     * Gets the decrypted `user_password` property value.
     *
     * @param mixed $token current value
     *
     * @return mixed decrypted token
     */
    protected function getUserPasswordValue($token)
    {
        if ($token || !$this->user_password_enc) {
            return $token;
        }

        return $this->user_password_enc;
    }
}
