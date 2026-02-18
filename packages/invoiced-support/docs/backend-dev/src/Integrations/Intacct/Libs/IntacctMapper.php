<?php

namespace App\Integrations\Intacct\Libs;

use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Enums\SyncDirection;
use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Models\AccountingSyncFieldMapping;
use App\Integrations\AccountingSync\ValueObjects\TransformField;
use App\Integrations\Enums\IntegrationType;
use App\Metadata\Libs\CustomFieldRepository;
use App\Metadata\Models\CustomField;
use App\PaymentProcessing\Models\PaymentMethod;
use Intacct\Functions\AccountsReceivable\AbstractArPayment;
use App\Core\Orm\Model;
use App\Core\Orm\Type;
use SimpleXMLElement;
use stdClass;

/**
 * Marshals data to and from Intacct into a format
 * that can be understood by our system.
 */
class IntacctMapper
{
    const DATE_FORMAT = 'm/d/Y';

    const ALTERNATE_DATE_FORMAT = 'Y-m-d';

    public static array $paymentMethodMap = [
        AbstractArPayment::PAYMENT_METHOD_CASH => PaymentMethod::CASH,
        AbstractArPayment::PAYMENT_METHOD_RECORD_TRANSFER => PaymentMethod::WIRE_TRANSFER,
        AbstractArPayment::PAYMENT_METHOD_ONLINE_CREDIT_CARD => PaymentMethod::CREDIT_CARD,
        AbstractArPayment::PAYMENT_METHOD_CREDIT_CARD => PaymentMethod::CREDIT_CARD,
        AbstractArPayment::PAYMENT_METHOD_CHECK => PaymentMethod::CHECK,
        AbstractArPayment::PAYMENT_METHOD_ONLINE_ACH_DEBIT => PaymentMethod::ACH,
        AbstractArPayment::PAYMENT_METHOD_ONLINE => PaymentMethod::ACH,
    ];

    private static array $paymentMethodMapToIntacct = [
        PaymentMethod::CASH => AbstractArPayment::PAYMENT_METHOD_CASH,
        PaymentMethod::WIRE_TRANSFER => AbstractArPayment::PAYMENT_METHOD_RECORD_TRANSFER,
        PaymentMethod::CREDIT_CARD => AbstractArPayment::PAYMENT_METHOD_CREDIT_CARD,
        PaymentMethod::CHECK => AbstractArPayment::PAYMENT_METHOD_CHECK,
        PaymentMethod::ACH => AbstractArPayment::PAYMENT_METHOD_RECORD_TRANSFER,
    ];

    /**
     * Map of non-model property keys to pulsar types.
     */
    private static array $customPropertyTypes = [
        'send' => Type::BOOLEAN,
    ];

    /**
     * Parses a date from Intacct in m/d/Y format.
     *
     * @throws TransformException
     */
    public function parseDate(string $date, bool $endOfDay = false): ?int
    {
        $dateTime = \DateTime::createFromFormat(self::DATE_FORMAT, $date);
        if (!$dateTime) {
            throw new TransformException('Could not parse date: '.$date);
        }

        $hour = $endOfDay ? 18 : 6;

        return $dateTime->setTime($hour, 0, 0)->getTimestamp();
    }

    /**
     * Parses a date from Intacct in YYYY-MM-DD format.
     *
     * @throws TransformException
     */
    public function parseIsoDate(string $date, bool $endOfDay = false): int
    {
        $dateTime = \DateTime::createFromFormat(self::ALTERNATE_DATE_FORMAT, $date);
        if (!$dateTime) {
            throw new TransformException('Could not parse date: '.$date);
        }

        $hour = $endOfDay ? 18 : 6;

        return $dateTime->setTime($hour, 0, 0)->getTimestamp();
    }

    /**
     * Parses a payment method from Intacct.
     */
    public function parsePaymentMethod(string $method): string
    {
        if (isset(self::$paymentMethodMap[$method])) {
            return self::$paymentMethodMap[$method];
        }

        return PaymentMethod::OTHER;
    }

    /**
     * Gets a payment method name for Intacct.
     */
    public function getPaymentMethodToIntacct(string $method, bool $isForeignCurrency): string
    {
        // INVD-2951: Intacct does not allow the "Credit Card" payment
        // method to be used with foreign currency payments. The workaround
        // is to select the EFT method in this scenario.
        if (PaymentMethod::CREDIT_CARD == $method && $isForeignCurrency) {
            return AbstractArPayment::PAYMENT_METHOD_RECORD_TRANSFER;
        }

        if (isset(self::$paymentMethodMapToIntacct[$method])) {
            return self::$paymentMethodMapToIntacct[$method];
        }

        return AbstractArPayment::PAYMENT_METHOD_RECORD_TRANSFER;
    }

    /**
     * Gets the value of an XML element matching the search pattern
     * with dot notation.
     * For example: ITEM.NAME will search for ITEM->NAME.
     */
    public function getNestedXmlValue(SimpleXMLElement $xml, string $key): ?string
    {
        $parts = explode('.', $key);

        foreach ($parts as $part) {
            if (!isset($xml->$part)) {
                return null;
            }

            $xml = $xml->$part;
        }

        return (string) $xml;
    }

    /**
     * @param class-string<Model> $model
     */
    public function makeLegacyMapping(string $model, string $source, string $destination, Company $company, string $objectType): TransformField
    {
        if (str_starts_with($destination, 'metadata.')) {
            // Looks up the type based on the custom field definition
            $id = substr($destination, 9);
            $repository = CustomFieldRepository::get($company);
            $field = $repository->getCustomField(ObjectType::fromModelClass($model)->typeName(), $id);
            $customFieldType = $field?->type;
            $type = match ($customFieldType) {
                CustomField::FIELD_TYPE_BOOLEAN => TransformFieldType::Boolean,
                CustomField::FIELD_TYPE_DOUBLE, CustomField::FIELD_TYPE_INTEGER => TransformFieldType::Float,
                default => TransformFieldType::String,
            };
        } else {
            // Looks up the type based on the model definition
            $property = $model::definition()->get($destination);
            $propertyType = $property?->type;
            $type = match ($propertyType) {
                Type::BOOLEAN => TransformFieldType::Boolean,
                Type::INTEGER, Type::FLOAT => TransformFieldType::Float,
                Type::DATE_UNIX => TransformFieldType::DateUnix,
                default => TransformFieldType::String,
            };
        }

        // Convert "." notation for nested fields to "/"
        $destination = str_replace('.', '/', $destination);

        // Create a mapping record in the new data model, if it does not exist yet
        $hasMapping = AccountingSyncFieldMapping::where('integration', IntegrationType::Intacct->value)
            ->where('direction', SyncDirection::Read->value)
            ->where('object_type', $objectType)
            ->where('source_field', $source)
            ->where('enabled', true)
            ->count();
        if (!$hasMapping) {
            $mapping = new AccountingSyncFieldMapping();
            $mapping->integration = IntegrationType::Intacct;
            $mapping->object_type = $objectType;
            $mapping->source_field = $source;
            $mapping->destination_field = $destination;
            $mapping->data_type = $type;
            $mapping->enabled = true;
            $mapping->save();
        }

        return new TransformField($source, $destination, $type);
    }

    /**
     * This parses an Intacct value into the appropriate format
     * to the appropriate data type. For example, if saving the
     * `autopay` property on the Invoice model it will be expecting
     * a boolean type field on Intacct and convert it to a boolean value.
     * As another example, a date field would convert the Intacct date
     * format into ours (UNIX timestamp).
     *
     * @throws TransformException
     */
    public function parseIntacctValue(string $model, string $modelProperty, mixed $value, Company $company): mixed
    {
        if ('' === $value) {
            return null;
        }

        // Type cast metadata fields based on the custom field definition
        if (str_starts_with($modelProperty, 'metadata.')) {
            $id = substr($modelProperty, 9);
            $repository = CustomFieldRepository::get($company);
            $field = $repository->getCustomField(ObjectType::fromModelClass($model)->typeName(), $id);
            if (!$field) {
                return $value;
            }

            $type = $field->type;

            if (CustomField::FIELD_TYPE_BOOLEAN == $type) {
                return 'true' == $value;
            }

            return $value;
        }

        // Otherwise this is a model property and the type is known through the ORM
        $property = $model::definition()->get($modelProperty);
        if (!$property) {
            if (!isset(self::$customPropertyTypes[$modelProperty])) {
                return $value;
            }

            $type = self::$customPropertyTypes[$modelProperty];
        } else {
            $type = $property->type;
        }

        if (Type::BOOLEAN == $type) {
            return 'true' == $value;
        }

        if (Type::DATE_UNIX == $type) {
            return $this->parseIsoDate($value);
        }

        return $value;
    }

    /**
     * We have to parse the mapping to see which format is used.
     *
     * Support different document types: (new format)
     * The '*' key is the default mapping when a document type does not match
     *
     * {"intacct_document_type":{"intacct_field_name":"invoiced_field_name"},"*":{"intacct_field_2_name":"invoiced_field_2_name"}}
     *
     * Simple format: (old format)
     * {"intacct_field_name":"invoiced_field_name"}
     */
    public function parseDocumentFieldMapping(stdClass $mapping, string $documentType): ?stdClass
    {
        if ($this->isSimpleFormat($mapping)) {
            return $mapping;
        }
        if (isset($mapping->$documentType)) {
            return $mapping->$documentType;
        } elseif (isset($mapping->{'*'})) {
            return $mapping->{'*'};
        }

        return null;
    }

    private function isSimpleFormat(object $mapping): bool
    {
        foreach ((array) $mapping as $value) {
            if (is_object($value)) {
                return false;
            }
        }

        return true;
    }
}
