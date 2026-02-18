<?php

namespace App\ActivityLog\Libs\Messages;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Comment;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\PaymentLink;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Interfaces\AttributedValueInterface;
use App\ActivityLog\ValueObjects\AttributedMoneyAmount;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\Network\Models\NetworkDocument;
use App\Sending\Email\Models\Email;
use App\Sending\Mail\Models\Letter;
use App\Sending\Sms\Models\TextMessage;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use ICanBoogie\Inflector;

abstract class BaseMessage implements \Stringable
{
    private const ASSOCIATION_CLASSES = [
        'credit_note' => CreditNote::class,
        'customer' => Customer::class,
        'email' => Email::class,
        'estimate' => Estimate::class,
        'invoice' => Invoice::class,
        'plan' => Plan::class,
        'vendor' => Vendor::class,
    ];

    protected ?CreditNote $credit_note = null;
    protected ?Customer $customer = null;
    protected ?Email $email = null;
    protected ?Estimate $estimate = null;
    protected ?Invoice $invoice = null;
    protected ?Plan $plan = null;
    protected ?Vendor $vendor = null;

    /**
     * @param string $type         event type, i.e. invoice.created
     * @param array  $object       subject of the event
     * @param array  $associations event associations
     * @param array  $previous     previous attributes for updated events
     */
    public function __construct(
        protected Company $company,
        protected string $type,
        protected array $object,
        protected array $associations,
        protected array $previous)
    {
        $this->buildObjectsFromEvent();
    }

    /**
     * Builds the object classes associated with this event.
     */
    private function buildObjectsFromEvent(): void
    {
        // rebuild the original object from the event object
        $objectType = explode('.', $this->type);
        $objectType = $objectType[0];
        $class = array_value(self::ASSOCIATION_CLASSES, $objectType);
        if ($class) {
            $this->$objectType = new $class($this->object);
        }

        // rebuild nested objects out of the event object
        // to save a database call
        foreach (self::ASSOCIATION_CLASSES as $k => $class) {
            $values = array_value($this->object, $k);
            if (is_array($values)) {
                $this->$k = new $class($values);
            }
        }

        // load any remaining associations from the DB
        foreach ($this->associations as $object => $objectId) {
            if (!isset(self::ASSOCIATION_CLASSES[$object]) || $this->$object) {
                continue;
            }

            $class = self::ASSOCIATION_CLASSES[$object];

            if (ObjectType::Plan->typeName() == $object) {
                $this->$object = Plan::getCurrent($objectId);
            } else {
                $this->$object = new $class(['id' => $objectId]);
            }
        }
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Generates the message for this event.
     *
     * @return AttributedValueInterface[]
     */
    public function generate(): array
    {
        // infer the event function name from the event type
        // i.e. invoice.created -> invoiceCreated()
        $inflector = Inflector::get();
        $fun = $inflector->camelize(str_replace('.', '_', $this->type), true);

        if (!method_exists($this, $fun)) {
            return [new AttributedString($this->type)];
        }

        return call_user_func([$this, $fun]); /* @phpstan-ignore-line */
    }

    /**
     * Generates the string message for this event.
     */
    public function toString(bool $encodeHtmlEntities = true): string
    {
        $parts = [];
        foreach ($this->generate() as $part) {
            $parts[] = $this->attributedValueToString($part, $encodeHtmlEntities);
        }

        return implode($parts);
    }

    /**
     * Converts an attributed value to a formatted string.
     */
    public function attributedValueToString(AttributedValueInterface $item, bool $encodeHtmlEntities): string
    {
        $value = (string) $item;
        if ($encodeHtmlEntities) {
            $value = htmlentities($value, ENT_QUOTES);
        }

        return (!$item instanceof AttributedString) ? '<b>'.$value.'</b>' : $value;
    }

    //
    // Getters
    //

    /**
     * Gets the event type.
     */
    public function getEventType(): string
    {
        return $this->type;
    }

    /**
     * Gets the event object values.
     */
    public function getObject(): array
    {
        return $this->object;
    }

    /**
     * Gets the previous values.
     */
    public function getPrevious(): array
    {
        return $this->previous;
    }

    /**
     * Gets the event associations.
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }

    //
    // Associations
    //

    /**
     * Sets the customer associated with this message.
     */
    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;
        $this->associations['customer'] = $customer->id();

        return $this;
    }

    /**
     * Sets the estimate associated with this message.
     */
    public function setEstimate(Estimate $estimate): self
    {
        $this->estimate = $estimate;
        $this->associations['estimate'] = $estimate->id();

        return $this;
    }

    /**
     * Sets the invoice associated with this message.
     */
    public function setInvoice(Invoice $invoice): self
    {
        $this->invoice = $invoice;
        $this->associations['invoice'] = $invoice->id();

        return $this;
    }

    /**
     * Sets the credit note associated with this message.
     */
    public function setCreditNote(CreditNote $creditNote): self
    {
        $this->credit_note = $creditNote;
        $this->associations['credit_note'] = $creditNote->id();

        return $this;
    }

    /**
     * Sets the payment associated with this message.
     */
    public function setPayment(Payment $payment): self
    {
        $this->associations['payment'] = $payment->id();

        return $this;
    }

    /**
     * Sets the transaction associated with this message.
     */
    public function setTransaction(Transaction $transaction): self
    {
        $this->associations['transaction'] = $transaction->id();

        return $this;
    }

    /**
     * Sets the plan associated with this message.
     */
    public function setPlan(Plan $plan): self
    {
        $this->plan = $plan;
        $this->associations['plan'] = $plan->id;

        return $this;
    }

    /**
     * Sets the subscription associated with this message.
     */
    public function setSubscription(Subscription $subscription): self
    {
        $this->associations['subscription'] = $subscription->id();

        return $this;
    }

    /**
     * Sets the comment associated with this message.
     */
    public function setComment(Comment $comment): self
    {
        $this->associations['comment'] = $comment->id();

        return $this;
    }

    /**
     * Sets the email associated with this message.
     */
    public function setEmail(Email $email): self
    {
        $this->email = $email;
        $this->associations['email'] = $email->id();

        return $this;
    }

    /**
     * Sets the text message associated with this message.
     */
    public function setTextMessage(TextMessage $text_message): self
    {
        $this->associations['text_message'] = $text_message->id();

        return $this;
    }

    /**
     * Sets the letter associated with this message.
     */
    public function setLetter(Letter $letter): self
    {
        $this->associations['letter'] = $letter->id();

        return $this;
    }

    /**
     * Sets the vendor associated with this message.
     */
    public function setVendor(Vendor $vendor): void
    {
        $this->vendor = $vendor;
        $this->associations['vendor'] = $vendor->id();
    }

    public function setNetworkDocument(NetworkDocument $network_document): void
    {
        $this->associations['network_document'] = $network_document->id();
    }

    public function setPaymentLink(PaymentLink $paymentLink): void
    {
        $this->associations['payment_link'] = $paymentLink->id();
    }

    //
    // Utility Functions
    //

    /**
     * Determines whether "a" or "an" should be used for a string.
     */
    protected function an(string $str): string
    {
        // not entirely correct but close enough for our purposes
        if (in_array(strtolower($str[0]), ['a', 'e', 'i', 'o', 'u'])) {
            return 'an';
        }

        return 'a';
    }

    //
    // Attributed Values
    //

    /**
     * Builds an attributed value for the money value associated
     * with this message.
     *
     * First looks for the "amount" key. If that's not present
     * then the "total" key is tried. Also relies on the
     * "currency" key.
     */
    protected function moneyAmount(): AttributedMoneyAmount
    {
        $amount = 0;
        if (isset($this->object['amount'])) {
            $amount = $this->object['amount'];
        } elseif (isset($this->object['total'])) {
            $amount = $this->object['total'];
        }

        $currency = $this->object['currency'] ?? $this->company->currency;

        return new AttributedMoneyAmount($currency, $amount, $this->company->moneyFormat());
    }

    /**
     * Builds an attributed value for the customer associated
     * with this message.
     */
    protected function customer(string $cachedNameProperty = 'name'): AttributedObject
    {
        // try to get the name from the customer object
        $name = $this->customer?->name;

        // next try the value embedded in the message object
        if (!$name) {
            $name = (string) array_value($this->object, $cachedNameProperty);
        }

        // if all else fails, then use the generic deleted name
        if (empty(trim($name))) {
            $name = '[deleted customer]';
        }

        return new AttributedObject('customer', $name, array_value($this->associations, 'customer'));
    }

    /**
     * Builds an attributed value for the estimate associated
     * with this message.
     */
    protected function estimate(): AttributedObject
    {
        $name = '';

        // try to get the name from the estimate object
        if ($this->estimate) {
            $name = $this->estimate->name.' '.$this->estimate->number;
        }

        // next try the value embedded in the message object
        if (!$name) {
            $name = array_value($this->object, 'name').' '.array_value($this->object, 'number');
        }

        // if all else fails, then use the generic deleted name
        if (empty(trim($name))) {
            $name = '[deleted estimate]';
        }

        return new AttributedObject('estimate', $name, array_value($this->associations, 'estimate'));
    }

    /**
     * Builds an attributed value for the invoice associated
     * with this message.
     */
    protected function invoice(): AttributedObject
    {
        $name = '';

        // try to get the name from the invoice object
        if ($this->invoice) {
            $name = $this->invoice->name.' '.$this->invoice->number;
        }

        // next try the value embedded in the message object
        if (!$name) {
            $name = array_value($this->object, 'name').' '.array_value($this->object, 'number');
        }

        // if all else fails, then use the generic deleted name
        if (empty(trim($name))) {
            $name = '[deleted invoice]';
        }

        return new AttributedObject('invoice', $name, array_value($this->associations, 'invoice'));
    }

    /**
     * Builds an attributed value for the credit note associated
     * with this message.
     */
    protected function creditNote(): AttributedObject
    {
        $name = '';

        // try to get the name from the credit note object
        if ($this->credit_note) {
            $name = $this->credit_note->name.' '.$this->credit_note->number;
        }

        // next try the value embedded in the message object
        if (!$name) {
            $name = array_value($this->object, 'name').' '.array_value($this->object, 'number');
        }

        // if all else fails, then use the generic deleted name
        if (empty(trim($name))) {
            $name = '[deleted credit note]';
        }

        return new AttributedObject('credit_note', $name, array_value($this->associations, 'credit_note'));
    }

    protected function paymentSource(array $source): AttributedObject
    {
        $objType = $source['object'] ?? '';
        if (ObjectType::Card->typeName() === $objType) {
            $str = $source['brand'].' *'.$source['last4'];
        } elseif (ObjectType::BankAccount->typeName() === $objType) {
            $str = $source['bank_name'].' *'.$source['last4'];
        } else {
            $str = '(not recognized)';
        }

        return new AttributedObject($objType, $str, $source['id'] ?? '');
    }

    /**
     * Builds an attributed value for the vendor associated
     * with this message.
     */
    protected function vendor(string $cachedNameProperty = 'name'): AttributedObject
    {
        // try to get the name from the vendor object
        $name = $this->vendor?->name;

        // next try the value embedded in the message object
        if (!$name) {
            $name = (string) array_value($this->object, $cachedNameProperty);
        }

        // if all else fails, then use the generic deleted name
        if (empty(trim($name))) {
            $name = '[deleted vendor]';
        }

        return new AttributedObject('vendor', $name, array_value($this->associations, 'vendor'));
    }
}
