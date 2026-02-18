<?php

namespace App\PaymentProcessing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\Currencies;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;
use App\PaymentProcessing\EmailVariables\RefundEmailVariables;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Traits\SendableDocumentTrait;
use App\Themes\Interfaces\PdfBuilderInterface;

/**
 * A refund represents the return of money through a payment gateway.
 *
 * @property int                             $id
 * @property int                             $charge_id
 * @property Charge                          $charge
 * @property string                          $currency
 * @property float                           $amount
 * @property string                          $status
 * @property string|null                     $failure_message
 * @property string                          $gateway
 * @property string                          $gateway_id
 * @property int                             $created_at
 * @property int                             $customer
 * @property MerchantAccountTransaction|null $merchant_account_transaction
 */
class Refund extends MultitenantModel implements EventObjectInterface, PdfDocumentInterface, SendableDocumentInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use SendableDocumentTrait;
    use EventObjectTrait;

    protected static function getProperties(): array
    {
        return [
            'charge' => new Property(
                required: true,
                belongs_to: Charge::class,
            ),
            'currency' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'status' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['enum', 'choices' => ['succeeded', 'pending', 'failed', 'voided']],
            ),
            'failure_message' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'gateway' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'gateway_id' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'merchant_account_transaction' => new Property(
                null: true,
                belongs_to: MerchantAccountTransaction::class,
            ),
        ];
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }

    /**
     * @deprecated needed for legacy email hook
     */
    protected function getCustomerValue(): ?int
    {
        return $this->charge->customer_id;
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $charge = $this->charge;

        $result = [
            ['customer', $charge->customer_id],
            ['charge', $charge->id()],
        ];

        if ($payment = $charge->payment_id) {
            $result[] = ['payment', $payment];
        }

        return $result;
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['charge.customer']);
    }

    //
    // SendableDocumentInterface
    //

    public function customer(): Customer
    {
        return $this->charge->customer ?? new Customer();
    }

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new RefundEmailVariables($this);
    }

    public function schemaOrgActions(): ?string
    {
        return null; // not used for payment receipts
    }

    public function getSendClientUrl(): ?string
    {
        return null;
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        if ($payment = $this->charge->payment) {
            return $payment->getPdfBuilder();
        }

        return null;
    }

    public function getThreadName(): string
    {
        return 'Refund for '.MoneyFormatter::get()->format($this->getAmount());
    }

    public function relation(string $name): ?Model
    {
        if ('customer' === $name) {
            return $this->charge->customer;
        }
        if ('payment' === $name) {
            return $this->charge->payment;
        }

        return parent::relation($name);
    }
}
