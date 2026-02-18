<?php

namespace App\Network\Enums;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use InvalidArgumentException;
use App\Core\Orm\Model;

enum NetworkDocumentType: int
{
    case ApplicationResponse = 1;
    case AwardedNotification = 2;
    case BillOfLading = 3;
    case BusinessCard = 4;
    case CallForTenders = 5;
    case Catalogue = 6;
    case CatalogueDeletion = 7;
    case CatalogueItemSpecificationUpdate = 8;
    case CataloguePricingUpdate = 9;
    case CatalogueRequest = 10;
    case CertificateOfOrigin = 11;
    case ContractAwardNotice = 12;
    case ContractNotice = 13;
    case CreditNote = 14;
    case DebitNote = 15;
    case DespatchAdvice = 16;
    case DigitalAgreement = 17;
    case DigitalCapability = 18;
    case DocumentStatus = 19;
    case DocumentStatusRequest = 20;
    case Enquiry = 21;
    case EnquiryResponse = 22;
    case ExceptionCriteria = 23;
    case ExceptionNotification = 24;
    case ExpressionOfInterestRequest = 25;
    case ExpressionOfInterestResponse = 26;
    case Forecast = 27;
    case ForecastRevision = 28;
    case ForwardingInstructions = 29;
    case FreightInvoice = 30;
    case FulfilmentCancellation = 31;
    case GoodsItemItinerary = 32;
    case GuaranteeCertificate = 33;
    case InstructionForReturns = 34;
    case InventoryReport = 35;
    case Invoice = 36;
    case ItemInformationRequest = 37;
    case Order = 38;
    case OrderCancellation = 39;
    case OrderChange = 40;
    case OrderResponse = 41;
    case OrderResponseSimple = 42;
    case PackingList = 43;
    case PriorInformationNotice = 44;
    case ProductActivity = 45;
    case QualificationApplicationRequest = 46;
    case QualificationApplicationResponse = 47;
    case Quotation = 48;
    case ReceiptAdvice = 49;
    case Reminder = 50;
    case RemittanceAdvice = 51;
    case RequestForQuotation = 52;
    case RetailEvent = 53;
    case SelfBilledCreditNote = 54;
    case SelfBilledInvoice = 55;
    case Statement = 56;
    case StockAvailabilityReport = 57;
    case Tender = 58;
    case TenderContract = 59;
    case TenderReceipt = 60;
    case TenderStatus = 61;
    case TenderStatusRequest = 62;
    case TenderWithdrawal = 63;
    case TendererQualification = 64;
    case TendererQualificationResponse = 65;
    case TradeItemLocationProfile = 66;
    case TransportExecutionPlan = 67;
    case TransportExecutionPlanRequest = 68;
    case TransportProgressStatus = 69;
    case TransportProgressStatusRequest = 70;
    case TransportServiceDescription = 71;
    case TransportServiceDescriptionRequest = 72;
    case TransportationStatus = 73;
    case TransportationStatusRequest = 74;
    case UnawardedNotification = 75;
    case UnsubscribeFromProcedureRequest = 76;
    case UnsubscribeFromProcedureResponse = 77;
    case UtilityStatement = 78;
    case Waybill = 79;
    case WeightStatement = 80;

    public static function fromName(string $name): self
    {
        foreach (self::cases() as $type) {
            if ($type->name == $name) {
                return $type;
            }
        }

        throw new InvalidArgumentException('Name not recognized: '.$name);
    }

    public static function fromModel(Model $model): self
    {
        return match (get_class($model)) {
            Invoice::class => self::Invoice,
            CreditNote::class => self::CreditNote,
            Estimate::class => self::Quotation,
            default => throw new InvalidArgumentException('Model not supported: '.$model::modelName()),
        };
    }
}
