<?php

namespace App\Core\Utils\Enums;

use App\AccountsPayable\Models\ApprovalWorkflow;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorAdjustment;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsReceivable\Models\Comment;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\ContactRole;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\CreditNoteLineItem;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\DocumentView;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\EstimateLineItem;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDiscount;
use App\AccountsReceivable\Models\InvoiceLineItem;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\Note;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkSession;
use App\AccountsReceivable\Models\PaymentTerms;
use App\AccountsReceivable\Models\Shipping;
use App\AccountsReceivable\Models\ShippingDetail;
use App\AccountsReceivable\Models\ShippingRate;
use App\AccountsReceivable\Models\Tax;
use App\CashApplication\Models\BankFeedTransaction;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\RemittanceAdvice;
use App\CashApplication\Models\Transaction;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\ChasingStatistic;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\Models\LateFeeSchedule;
use App\Chasing\Models\PromiseToPay;
use App\Chasing\Models\Task;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\BillAttachment;
use App\Core\Files\Models\CustomerPortalAttachment;
use App\Core\Files\Models\File;
use App\Core\Files\Models\VendorCreditAttachment;
use App\Core\Files\Models\VendorPaymentAttachment;
use App\Core\Orm\Model;
use App\Core\Utils\ObjectTypes;
use App\Imports\Models\Import;
use App\Integrations\Flywire\Models\FlywireDisbursement;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Flywire\Models\FlywirePayout;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\Network\Models\NetworkDocument;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\CustomerPaymentBatch;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\Payout;
use App\PaymentProcessing\Models\Refund;
use App\SalesTax\Models\TaxRate;
use App\Sending\Email\Models\Email;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Mail\Models\Letter;
use App\Sending\Sms\Models\TextMessage;
use App\SubscriptionBilling\Models\MrrItem;
use App\SubscriptionBilling\Models\MrrMovement;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionAddon;
use RuntimeException;

/**
 * A central repository of the various object types supported
 * in our system. Each object type represents a core concept
 * within Invoiced as presented to the user. For example, this
 * includes Customers, Invoices, and Credit Notes. It is not
 * intended for there to be an object type registered for every
 * model, or even every API supported object.
 */
enum ObjectType: int
{
    // Latest ID: 83
    // (please update this as new objects are added)
    case ApprovalWorkflow = 68;
    case Attachment = 56;
    case BankAccount = 18;
    case BankFeedTransaction = 73;
    case Bill = 62;
    case BillAttachment = 70;
    case Card = 16;
    case Charge = 19;
    case ChasingCadence = 27;
    case ChasingCadenceStep = 48;
    case ChasingStatistic = 46;
    case Comment = 34;
    case Company = 20;
    case Contact = 15;
    case ContactRole = 44;
    case Coupon = 11;
    case CreditBalanceAdjustment = 42;
    case CreditNote = 3;
    case CreditNoteLineItem = 37;
    case Customer = 1;
    case CustomerPaymentBatch = 74;
    case CustomerPortalAttachment = 71;
    case Discount = 52;
    case Dispute = 81;
    case DocumentView = 28;
    case EmailParticipant = 50;
    case EmailThread = 29;
    case Estimate = 4;
    case EstimateLineItem = 38;
    case File = 55;
    case FlywireDisbursement = 76;
    case FlywirePayment = 75;
    case FlywirePayout = 78;
    case FlywireRefund = 77;
    case Import = 41;
    case InboxEmail = 30;
    case Invoice = 2;
    case InvoiceChasingCadence = 47;
    case InvoiceDiscount = 43;
    case InvoiceLineItem = 39;
    case Item = 8;
    case LateFeeSchedule = 36;
    case LegacyEmail = 13;
    case Letter = 31;
    case LineItem = 17;
    case Member = 22;
    case MerchantAccount = 25;
    case MerchantAccountTransaction = 83;
    case MrrItem = 64;
    case MrrMovement = 65;
    case NetworkDocument = 58;
    case Note = 14;
    case Payment = 7;
    case PaymentLink = 79;
    case PaymentLinkSession = 80;
    case PaymentPlan = 32;
    case PaymentPlanInstallment = 33;
    case PaymentTerms = 61;
    case Payout = 82;
    case PendingLineItem = 40;
    case Plan = 9;
    case PromiseToPay = 23;
    case Refund = 12;
    case RemittanceAdvice = 72;
    case Role = 26;
    case Shipping = 54;
    case ShippingDetail = 45;
    case ShippingRate = 57;
    case Subscription = 5;
    case SubscriptionAddon = 49;
    case Task = 24;
    case Tax = 53;
    case TaxRate = 10;
    case TextMessage = 35;
    case Transaction = 6;
    case User = 21;
    case Vendor = 51;
    case VendorAdjustment = 59;
    case VendorCredit = 63;
    case VendorCreditAttachment = 69;
    case VendorPayment = 60;
    case VendorPaymentAttachment = 66;
    case VendorPaymentBatch = 67;

    /**
     * @throws RuntimeException
     */
    public static function fromTypeName(string $typeName): self
    {
        foreach (self::cases() as $case) {
            if ($case->typeName() == $typeName) {
                return $case;
            }
        }

        throw new RuntimeException('Object type not recognized: '.$typeName);
    }

    public static function fromString(string $typeName): self
    {
        return self::fromTypeName($typeName);
    }

    /**
     * @throws RuntimeException
     */
    public static function fromModelClass(string $className): self
    {
        foreach (self::cases() as $case) {
            if ($case->modelClass() == $className) {
                return $case;
            }
        }

        throw new RuntimeException('Model class not supported');
    }

    /**
     * @throws RuntimeException
     */
    public static function fromModel(Model $model): self
    {
        return self::fromModelClass($model::class);
    }

    /**
     * Converts the enum name from TitleCase to snake_case that is
     * used in user-facing scenarios.
     */
    public function typeName(): string
    {
        // memoize the result to avoid constantly regenerating the name
        if (isset(ObjectTypes::$nameCache[$this->name])) {
            return ObjectTypes::$nameCache[$this->name];
        }

        $result = '';
        foreach (str_split($this->name) as $i => $char) {
            if (ctype_upper($char)) {
                if ($i > 0) {
                    $result .= '_';
                }

                $result .= strtolower($char);
            } else {
                $result .= $char;
            }
        }
        ObjectTypes::$nameCache[$this->name] = $result;

        return $result;
    }

    public function toString(): string
    {
        return $this->typeName();
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::ApprovalWorkflow => ApprovalWorkflow::class,
            self::Attachment => Attachment::class,
            self::BankAccount => BankAccount::class,
            self::BankFeedTransaction => BankFeedTransaction::class,
            self::Bill => Bill::class,
            self::BillAttachment => BillAttachment::class,
            self::Card => Card::class,
            self::Charge => Charge::class,
            self::ChasingCadence => ChasingCadence::class,
            self::ChasingCadenceStep => ChasingCadenceStep::class,
            self::ChasingStatistic => ChasingStatistic::class,
            self::Comment => Comment::class,
            self::Company => Company::class,
            self::Contact => Contact::class,
            self::ContactRole => ContactRole::class,
            self::Coupon => Coupon::class,
            self::CreditBalanceAdjustment => CreditBalanceAdjustment::class,
            self::CreditNote => CreditNote::class,
            self::CreditNoteLineItem => CreditNoteLineItem::class,
            self::Customer => Customer::class,
            self::CustomerPaymentBatch => CustomerPaymentBatch::class,
            self::CustomerPortalAttachment => CustomerPortalAttachment::class,
            self::Discount => Discount::class,
            self::Dispute => Dispute::class,
            self::DocumentView => DocumentView::class,
            self::EmailParticipant => EmailParticipant::class,
            self::EmailThread => EmailThread::class,
            self::Estimate => Estimate::class,
            self::EstimateLineItem => EstimateLineItem::class,
            self::File => File::class,
            self::FlywireDisbursement => FlywireDisbursement::class,
            self::FlywirePayment => FlywirePayment::class,
            self::FlywirePayout => FlywirePayout::class,
            self::FlywireRefund => FlywireRefund::class,
            self::Import => Import::class,
            self::InboxEmail => InboxEmail::class,
            self::Invoice => Invoice::class,
            self::InvoiceChasingCadence => InvoiceChasingCadence::class,
            self::InvoiceDiscount => InvoiceDiscount::class,
            self::InvoiceLineItem => InvoiceLineItem::class,
            self::Item => Item::class,
            self::LateFeeSchedule => LateFeeSchedule::class,
            self::LegacyEmail => Email::class,
            self::Letter => Letter::class,
            self::LineItem => LineItem::class,
            self::Member => Member::class,
            self::MerchantAccount => MerchantAccount::class,
            self::MerchantAccountTransaction => MerchantAccountTransaction::class,
            self::MrrItem => MrrItem::class,
            self::MrrMovement => MrrMovement::class,
            self::NetworkDocument => NetworkDocument::class,
            self::Note => Note::class,
            self::Payment => Payment::class,
            self::PaymentLink => PaymentLink::class,
            self::PaymentLinkSession => PaymentLinkSession::class,
            self::PaymentPlan => PaymentPlan::class,
            self::PaymentPlanInstallment => PaymentPlanInstallment::class,
            self::PaymentTerms => PaymentTerms::class,
            self::Payout => Payout::class,
            self::PendingLineItem => PendingLineItem::class,
            self::Plan => Plan::class,
            self::PromiseToPay => PromiseToPay::class,
            self::Refund => Refund::class,
            self::RemittanceAdvice => RemittanceAdvice::class,
            self::Role => Role::class,
            self::Shipping => Shipping::class,
            self::ShippingDetail => ShippingDetail::class,
            self::ShippingRate => ShippingRate::class,
            self::Subscription => Subscription::class,
            self::SubscriptionAddon => SubscriptionAddon::class,
            self::Task => Task::class,
            self::Tax => Tax::class,
            self::TaxRate => TaxRate::class,
            self::TextMessage => TextMessage::class,
            self::Transaction => Transaction::class,
            self::User => User::class,
            self::Vendor => Vendor::class,
            self::VendorAdjustment => VendorAdjustment::class,
            self::VendorCredit => VendorCredit::class,
            self::VendorCreditAttachment => VendorCreditAttachment::class,
            self::VendorPayment => VendorPayment::class,
            self::VendorPaymentAttachment => VendorPaymentAttachment::class,
            self::VendorPaymentBatch => VendorPaymentBatch::class,
        };
    }
}
