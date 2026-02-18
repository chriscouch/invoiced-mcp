<?php

namespace App\AccountsPayable\Models;

use App\Core\I18n\Countries;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\InfuseUtility as Utility;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use Throwable;

/**
 * @property int                $id
 * @property string             $hash
 * @property VendorPayment      $payment
 * @property int                $payment_id
 * @property CompanyBankAccount $account
 * @property int                $viewed
 * @property string|null        $address1
 * @property string|null        $address2
 * @property string|null        $city
 * @property string|null        $state
 * @property int|null           $postal_code
 * @property string|null        $country
 * @property string             $email
 * @property string             $signature
 * @property float              $amount
 * @property int                $check_number
 */
class ECheck extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'hash' => new Property(
                required: true,
            ),
            'payment' => new Property(
                null: true,
                belongs_to: VendorPayment::class,
            ),
            'account' => new Property(
                null: true,
                belongs_to: CompanyBankAccount::class,
            ),
            'viewed' => new Property(
                type: Type::INTEGER,
                default: 0,
            ),
            /* Address */
            'address1' => new Property(
                null: true,
            ),
            'address2' => new Property(
                null: true,
            ),
            'city' => new Property(
                null: true,
            ),
            'state' => new Property(
                null: true,
            ),
            'postal_code' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'country' => new Property(
                null: true,
                validate: ['callable', 'fn' => [Countries::class, 'validateCountry']],
            ),
            'email' => new Property(
                required: true,
                validate: 'email',
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'check_number' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'setHashOnCreate']);
    }

    public static function setHashOnCreate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->setHash();
    }

    public function setHash(): void
    {
        $this->hash = dechex(time()).'-'.Utility::guid();
    }

    public function isExpired(): bool
    {
        try {
            $time = hexdec(explode('-', $this->hash, 2)[0]);
        } catch (Throwable) {
            return true;
        }

        return $time < (time() - 86400);
    }
}
