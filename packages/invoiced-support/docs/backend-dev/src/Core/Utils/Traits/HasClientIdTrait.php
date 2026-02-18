<?php

namespace App\Core\Utils\Traits;

use App\Core\Utils\RandomString;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property string $client_id
 * @property int    $client_id_exp
 */
trait HasClientIdTrait
{
    /**
     * Installs the client_id property.
     */
    protected function autoInitializeClientId(): void
    {
        // install the event listener for this model
        self::creating([static::class, 'genClientId']);
    }

    /**
     * Defines the `client_id` property in the $properties
     * variable of models which use this trait.
     */
    protected static function autoDefinitionClientId(): array
    {
        return [
            'client_id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                in_array: false,
            ),
            'client_id_exp' => new Property(
                type: Type::DATE_UNIX,
                in_array: false,
            ),
        ];
    }

    /**
     * Generates a fresh client ID and expiration date for this model
     * and saves it.
     * NOTE: this updates the DB directly.
     *
     * @param bool     $regenerateId when true generates a new client ID
     * @param int|null $exp          expiration date to set
     */
    public function refreshClientId(bool $regenerateId = true, ?int $exp = null): void
    {
        $values = [];

        // generate a new client ID
        if ($regenerateId) {
            $values['client_id'] = RandomString::generate(48, RandomString::CHAR_ALNUM);
        }

        // update the expiration date
        if (!$exp) {
            $exp = static::getClientIdExpiration();
        }
        $values['client_id_exp'] = $exp;

        // save the new values to the DB
        $db = self::getDriver()->getConnection(null);

        $db->update($this->getTablename(), $values, $this->ids());
        foreach ($values as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * Generates a client ID for a model event.
     */
    public static function genClientId(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // generate a unique identifier for use in customer portal
        $model->client_id = RandomString::generate(48, RandomString::CHAR_ALNUM);

        // and set the expiration date
        $model->client_id_exp = static::getClientIdExpiration();
    }

    /**
     * Gets the timestamp at which a refreshed client ID should expire.
     */
    public static function getClientIdExpiration(): int
    {
        return strtotime('+90 days');
    }

    /**
     * Finds a model with the given client ID.
     *
     * @return static|null
     */
    public static function findClientId(string $id): ?self
    {
        if (!$id) {
            return null;
        }

        return static::where('client_id', $id)
            ->oneOrNull();
    }
}
