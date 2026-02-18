<?php

namespace App\Sending\Email\Models;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Exception\DriverException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Search\Traits\SearchableTrait;

/**
 * An EmailParticipant represents a email contact that is associated with an email either through
 * a 'to', 'from', 'cc', or 'bcc' relationship.
 *
 * @property int      $id
 * @property string   $email_address
 * @property string   $name
 * @property int|null $user_id
 */
class EmailParticipant extends MultitenantModel
{
    use SearchableTrait;

    const TO = 'to';
    const FROM = 'from';
    const CC = 'cc';
    const BCC = 'bcc';

    protected static function getProperties(): array
    {
        return [
            'email_address' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'name' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'user_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: User::class,
            ),
        ];
    }

    public static function getOrCreate(Company $company, string $emailAddress, string $name): self
    {
        $emailParticipant = EmailParticipant::queryWithTenant($company)
            ->where('email_address', $emailAddress)
            ->oneOrNull();

        if (!($emailParticipant instanceof EmailParticipant)) {
            $emailParticipant = new EmailParticipant();
            $emailParticipant->tenant_id = $company->id;
            $emailParticipant->email_address = $emailAddress;
            $emailParticipant->name = $name;

            $user = User::where('email', $emailAddress)->oneOrNull();
            if ($user instanceof User) {
                $emailParticipant->user_id = (int) $user->id();
            }

            $emailParticipant->saveOrFail();
        }

        return $emailParticipant;
    }

    public function create(array $data = []): bool
    {
        try {
            return parent::create($data);
        } catch (DriverException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return true;
            }

            throw $e;
        }
    }
}
