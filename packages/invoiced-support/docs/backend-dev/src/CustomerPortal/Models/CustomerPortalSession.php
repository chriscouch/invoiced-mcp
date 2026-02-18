<?php

namespace App\CustomerPortal\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Utils\RandomString;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * @property string            $identifier
 * @property User|null         $user
 * @property string            $email
 * @property DateTimeInterface $expires
 */
class CustomerPortalSession extends MultitenantModel
{
    const COOKIE_NAME = 'CustomerPortalSession';

    protected static function getProperties(): array
    {
        return [
            'identifier' => new Property(),
            'user' => new Property(
                null: true,
                belongs_to: User::class
            ),
            'email' => new Property(
                null: true
            ),
            'expires' => new Property(
                type: Type::DATETIME,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'genIdentifier']);
    }

    public static function genIdentifier(AbstractEvent $event): void
    {
        // generate a random string for use as an external identifier
        $event->getModel()->identifier = RandomString::generate(32, RandomString::CHAR_ALNUM);
    }

    /**
     * Gets a non-expired session for the given identifier.
     */
    public static function getForIdentifier(string $identifier): ?self
    {
        return CustomerPortalSession::where('identifier', $identifier)
            ->where('expires', CarbonImmutable::now()->toDateTimeString(), '>')
            ->oneOrNull();
    }
}
