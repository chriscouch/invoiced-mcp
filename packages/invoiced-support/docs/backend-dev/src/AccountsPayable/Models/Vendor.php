<?php

namespace App\AccountsPayable\Models;

use App\Companies\Traits\HasAutoNumberingTrait;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\AddressFormatter;
use App\Core\I18n\Countries;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Search\Traits\SearchableTrait;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use App\Network\Models\NetworkConnection;

/**
 * @property int                    $id
 * @property string                 $name
 * @property bool                   $active
 * @property NetworkConnection|null $network_connection
 * @property int|null               $network_connection_id
 * @property ApprovalWorkflow|null  $approval_workflow
 * @property string|null            $address1
 * @property string|null            $address2
 * @property string|null            $city
 * @property string|null            $state
 * @property string|null            $postal_code
 * @property string|null            $country
 * @property string|null            $email
 * @property string                 $address
 * @property VendorBankAccount|null $bank_account
 * @property int|null               $bank_account_id
 */
class Vendor extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use HasAutoNumberingTrait;
    use EventModelTrait;
    use SearchableTrait;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'active' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'number' => new Property(
                validate: ['string', 'min' => 1, 'max' => 32],
            ),
            'network_connection' => new Property(
                null: true,
                belongs_to: NetworkConnection::class,
            ),
            'approval_workflow' => new Property(
                null: true,
                belongs_to: ApprovalWorkflow::class,
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
                null: true
            ),
            'country' => new Property(
                null: true,
                validate: ['callable', 'fn' => [Countries::class, 'validateCountry']],
            ),
            'email' => new Property(
                null: true,
                validate: 'email',
            ),
            'bank_account' => new Property(
                null: true,
                belongs_to: VendorBankAccount::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::saving([self::class, 'verifyNetworkConnection']);
    }

    /**
     * Gets the formatted address with the `address` property.
     *
     * @param string|null $address
     */
    protected function getAddressValue($address): string
    {
        if ($address) {
            return $address;
        }

        return $this->address(false);
    }

    /**
     * Verifies the network connection relationship when saving.
     */
    public static function verifyNetworkConnection(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $connection = $model->network_connection;
        if ($connection && $connection->customer_id != $model->tenant_id) {
            throw new ListenerException('Network connection not found: '.$connection->id);
        }
    }

    public function getVendorAddress(): array
    {
        $company = $this->network_connection ? $this->network_connection->vendor : $this;

        return [
            'email' => $company->email,
            'address1' => $company->address1,
            'address2' => $company->address2,
            'city' => $company->city,
            'state' => $company->state,
            'postal_code' => $company->postal_code,
            'country' => $company->country,
        ];
    }

    /**
     * Generates the address for the vendor.
     */
    public function address(bool $showName = true): string
    {
        // cannot generate an address if we do not know the country
        // inherit the company country, if set
        $companyCountry = $this->tenant()->country;
        if (!$this->country) {
            if (!$companyCountry) {
                return '';
            }

            $this->country = $companyCountry;
        }

        // only show the country line when the vendor and
        // company are in different countries
        $showCountry = $this->country != $companyCountry;

        $af = new AddressFormatter();

        return $af->setFrom($this)->format([
            'showCountry' => $showCountry,
            'showName' => $showName,
        ]);
    }
}
