<?php

namespace App\Core\RestApi\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\RandomString;

/**
 * @property int         $id
 * @property string      $secret
 * @property string      $secret_enc
 * @property string      $secret_hash
 * @property string|null $description
 * @property bool        $protected
 * @property int         $last_used
 * @property int|null    $expires
 * @property bool        $remember_me
 * @property string      $source
 * @property int         $user_id
 */
class ApiKey extends MultitenantModel
{
    use AutoTimestamps;

    const SOURCE_DASHBOARD = 'dashboard';
    const SOURCE_SYNC = 'sync';

    protected static function getProperties(): array
    {
        return [
            'secret_enc' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'secret_hash' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
            ),
            'description' => new Property(
                null: true,
            ),
            'protected' => new Property(
                type: Type::BOOLEAN,
                mutable: Property::MUTABLE_CREATE_ONLY,
                in_array: false,
            ),
            'last_used' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'expires' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'source' => new Property(
                in_array: false,
            ),
            'remember_me' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'user_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                relation: User::class,
            ),
        ];
    }

    /**
     * @template T
     *
     * @param Query<T> $query
     *
     * @return Query<T>
     */
    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->where('protected', false);
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'genSecret']);
        self::deleting([self::class, 'deleteProtected']);

        parent::initialize();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['secret'] = $this->secret;

        return $result;
    }

    /**
     * Generates a unique secret for an api key.
     */
    public static function genSecret(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $model->secret = '';
        $db = self::getDriver()->getConnection(null);
        $sql = 'SELECT COUNT(*) FROM ApiKeys WHERE secret_hash = ?';

        while (!$model->secret || $db->fetchOne($sql, [$model->secret_hash]) > 0) {
            $model->secret = RandomString::generate(32, RandomString::CHAR_ALNUM);
        }
    }

    public static function deleteProtected(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->protected) {
            throw new ListenerException('Cannot delete protected API keys');
        }
    }

    /**
     * Sets the `secret` property by encrypting it on `secret_enc` and
     * hashing it on `secret_hash`.
     *
     * @param string $secret
     *
     * @return mixed secret
     */
    protected function setSecretValue($secret)
    {
        if ($secret) {
            // encrypt it (used to retrieve original key)
            $this->secret_enc = $secret;

            // hash it (used for lookups)
            $this->secret_hash = self::hash($secret);
        }

        return $secret;
    }

    /**
     * Gets the decrypted `secret` property value.
     *
     * @param mixed $secret current value
     *
     * @return mixed decrypted secret
     */
    protected function getSecretValue($secret)
    {
        if ($secret || !$this->secret_enc) {
            return $secret;
        }

        return $this->secret_enc;
    }

    /**
     * Gets the API key matching a given secret.
     *
     * @param string $secret
     */
    public static function getFromSecret($secret): ?self
    {
        // create a blank query that does not have the
        // protected=false constraint or multitenancy check
        $model = new self();
        $query = new Query($model);

        return $query->where('secret_hash', self::hash($secret))
            ->where('(`expires` IS NULL OR `expires` > '.time().')')
            ->oneOrNull();
    }

    /**
     * Removes all API keys for a given user.
     */
    public static function removeAllForUser(User $user): void
    {
        // create a blank query that does not have the
        // protected=false constraint or multitenancy check
        $model = new self();
        $query = new Query($model);
        $apiKeys = $query->where('user_id', $user)
            ->sort('id DESC')
            ->first(1000);
        foreach ($apiKeys as $apiKey) {
            $apiKey->delete();
        }
    }

    /**
     * Hashes a secret.
     *
     * @param string $secret
     */
    public static function hash($secret): string
    {
        return hash_hmac('sha512', $secret, (string) getenv('APP_SALT'));
    }

    //
    // Relationships
    //

    /**
     * Gets the user.
     */
    public function user(): ?User
    {
        return $this->relation('user_id');
    }
}
