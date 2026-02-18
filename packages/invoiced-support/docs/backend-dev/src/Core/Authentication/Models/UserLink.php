<?php

namespace App\Core\Authentication\Models;

use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\AppUrl;
use App\Core\Utils\RandomString;

/**
 * @property int    $user_id
 * @property string $type
 * @property string $link
 */
class UserLink extends Model
{
    use AutoTimestamps;

    const FORGOT_PASSWORD = 'reset_password';
    const VERIFY_EMAIL = 'verify_email';
    const TEMPORARY = 'temporary';

    public static int $verifyTimeWindow = 86400; // one day in seconds
    public static int $forgotLinkTimeframe = 14400; // 4 hours in seconds

    protected static function getIDProperties(): array
    {
        return ['user_id', 'link'];
    }

    protected static function getProperties(): array
    {
        return [
            'user_id' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'type' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['reset_password', 'verify_email', 'temporary']],
            ),
            'link' => new Property(
                required: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'generateLink']);
    }

    public static function generateLink(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->link) {
            $model->link = RandomString::generate(32, RandomString::CHAR_ALNUM);
        }
    }

    /**
     * Gets the URL for this link.
     */
    public function url(): ?string
    {
        if (self::FORGOT_PASSWORD === $this->type) {
            return AppUrl::get()->build().'/users/forgot/'.$this->link;
        } elseif (self::TEMPORARY === $this->type) {
            return AppUrl::get()->build().'/users/signup/'.$this->link;
        }

        return null;
    }
}
