<?php

namespace App\Network\Models;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use DateTimeInterface;
use App\Core\Orm\Event\ModelDeleting;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                    $id
 * @property string                 $uuid
 * @property string|null            $email
 * @property Company                $from_company
 * @property int                    $from_company_id
 * @property Company|null           $to_company
 * @property int|null               $to_company_id
 * @property Member|null            $sent_by_user
 * @property Customer|null          $customer
 * @property Vendor|null            $vendor
 * @property bool                   $is_customer
 * @property DateTimeInterface      $expires_at
 * @property bool                   $declined
 * @property DateTimeInterface|null $declined_at
 */
class NetworkInvitation extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'uuid' => new Property(
                type: Type::STRING,
                in_array: false,
            ),
            'email' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'from_company' => new Property(
                belongs_to: Company::class,
            ),
            'from_company_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'to_company' => new Property(
                null: true,
                belongs_to: Company::class,
            ),
            'to_company_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'vendor' => new Property(
                null: true,
                belongs_to: Vendor::class,
            ),
            'is_customer' => new Property(
                type: Type::BOOLEAN,
            ),
            'sent_by_user' => new Property(
                null: true,
                belongs_to: Member::class,
            ),
            'expires_at' => new Property(
                type: Type::DATETIME,
            ),
            'declined' => new Property(
                type: Type::BOOLEAN,
            ),
            'declined_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::deleting([self::class, 'canDelete']);
    }

    public static function canDelete(ModelDeleting $event): void
    {
        /** @var NetworkInvitation $invitation */
        $invitation = $event->getModel();
        if ($invitation->declined) {
            throw new ListenerException('This invitation cannot be deleted because it was already declined.');
        }
    }

    protected function getToUsernameValue(): ?string
    {
        if ($company = $this->to_company) {
            return $company->username;
        }

        return null;
    }
}
