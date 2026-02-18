<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\CashApplication\Enums\PaymentItemType;
use App\Companies\Models\Company;
use App\Core\I18n\Countries;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Imports\Libs\ImportHelper;
use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\AccountingPaymentItem;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\AccountingSync\ValueObjects\TransformField;
use App\Integrations\Enums\IntegrationType;
use Carbon\CarbonImmutable;
use SimpleXMLElement;
use Throwable;

final class TransformerHelper
{
    /**
     * Transforms a Model using the
     * given mapping configuration.
     *
     * @param TransformField[] $mapping
     */
    public static function transformModel(AccountingWritableModel $record, array $mapping): array
    {
        $values = [];
        foreach ($mapping as $field) {
            if (TransformField::VALUE_ID == $field->sourceField) {
                $value = $field->value;
            } else {
                $value = self::getModelValue($record, $field->sourceField);
            }

            $destinationField = $field->destinationField;
            if (str_contains($destinationField, '[]')) {
                if (null == $value) {
                    $value = [];
                } elseif (is_array($value)) {
                    foreach ($value as &$value2) {
                        $value2 = self::transformValue($field, $value2);
                    }
                }
            }
            if (is_object($value)) {
                $value = self::transformValue($field, $value);
            }

            // Do not set null values if the null behavior is "ignore".
            if (null === $value && 'ignore' == $field->nullBehavior) {
                continue;
            }

            self::setValue($values, $destinationField, $value);
        }

        return $values;
    }

    /**
     * Transforms a JSON record extracted from the accounting system using the
     * given mapping configuration.
     *
     * @param TransformField[] $mapping
     */
    public static function transformJson(AccountingJsonRecord $record, array $mapping): array
    {
        $values = [];
        foreach ($mapping as $field) {
            if (TransformField::VALUE_ID == $field->sourceField) {
                $value = $field->value;
            } elseif ($field->documentContext) {
                $value = self::getJsonValue($record->document, $field->sourceField);
            } else {
                $value = self::getJsonValue($record->supportingData, $field->sourceField);
            }

            $destinationField = $field->destinationField;
            if (str_contains($destinationField, '[]')) {
                if (is_array($value)) {
                    foreach ($value as &$value2) {
                        $value2 = self::transformValue($field, $value2);
                    }
                }
            } else {
                $value = self::transformValue($field, $value);
            }

            // Do not set null values if the null behavior is "ignore".
            if (null === $value && 'ignore' == $field->nullBehavior) {
                continue;
            }

            self::setValue($values, $destinationField, $value);
        }

        return $values;
    }

    /**
     * Transforms an XML record extracted from the accounting system using the
     * given mapping configuration.
     *
     * @param TransformField[] $mapping
     */
    public static function transformXml(AccountingXmlRecord $record, array $mapping): array
    {
        $values = [];
        foreach ($mapping as $field) {
            if (TransformField::VALUE_ID == $field->sourceField) {
                $value = $field->value;
            } else {
                $value = self::getXmlValue($record->document, $field->sourceField, $field->xmlEmptyAsNull);
            }

            $destinationField = $field->destinationField;
            if (str_contains($destinationField, '[]')) {
                if (is_array($value)) {
                    foreach ($value as &$value2) {
                        $value2 = self::transformValue($field, $value2);
                    }
                }
            } else {
                $value = self::transformValue($field, $value);
            }

            // Do not set null values if the null behavior is "ignore".
            if (null === $value && 'ignore' == $field->nullBehavior) {
                continue;
            }

            self::setValue($values, $destinationField, $value);
        }

        return $values;
    }

    public static function transformValue(TransformField $field, mixed $value): mixed
    {
        $type = $field->type;
        if (TransformFieldType::String == $type && $value) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value);
            }

            return trim((string) $value);
        }

        if (TransformFieldType::Float == $type && null !== $value) {
            return (float) $value;
        }

        if (TransformFieldType::Boolean == $type && null !== $value) {
            if (is_string($value)) {
                return match (strtolower($value)) {
                    '0', 'false', 'no', '' => false,
                    default => true,
                };
            }
        }

        if (TransformFieldType::DateUnix == $type) {
            if (is_string($value) && str_contains($value, '-')) {
                try {
                    $date = new CarbonImmutable($value);

                    return $date->setTime($field->timeOfDay, 0)->getTimestamp();
                } catch (Throwable) {
                    // do nothing
                }
            }
        }

        if (TransformFieldType::Array == $type) {
            if (!is_array($value)) {
                return explode(',', (string) $value);
            }
        }

        if (TransformFieldType::Currency == $type && $value) {
            return strtolower((string) $value);
        }

        if (TransformFieldType::Country == $type && $value) {
            $countries = new Countries();
            if (2 == strlen($value)) {
                // Verify the 2-digit country code is valid per our database.
                if ($country = $countries->get($value)) {
                    return $country['code'];
                }

                return null;
            } elseif (3 == strlen($value)) {
                // If a 3-digit country is provided then attempt
                // to convert to a 2-digit code.
                if ($country = $countries->getFromAlpha3($value)) {
                    return $country['code'];
                }

                return null;
            }

            // In all other cases attempt to convert the name to a 2-digit code.
            if ($country = $countries->getFromName($value)) {
                return $country['code'];
            }

            return null;
        }

        if (TransformFieldType::EmailList == $type) {
            if (null === $value) {
                return null;
            }

            return $value ? ImportHelper::parseEmailAddress($value) : [];
        }

        return $value;
    }

    /**
     * Makes a customer record ready to be loaded into Invoiced
     * using the output from one of the transform methods.
     */
    public static function makeCustomer(IntegrationType $integration, array $input): AccountingCustomer
    {
        // Accounting ID
        $accountingId = $input['accounting_id'] ?? '';
        unset($input['accounting_id']);
        unset($input['accounting_system']);

        // Emails
        $emails = $input['emails'] ?? null;
        unset($input['emails']);

        // Contacts
        $contacts = $input['contacts'] ?? null;
        unset($input['contacts']);

        // Parent Customer
        $parentCustomer = false;
        if (isset($input['parent_customer'])) {
            $parentCustomer = self::makeCustomer($integration, $input['parent_customer']);
            unset($input['parent_customer']);
        }

        // Deleted
        $deleted = $input['deleted'] ?? false;
        unset($input['deleted']);

        return new AccountingCustomer(
            integration: $integration,
            accountingId: $accountingId,
            values: $input,
            emails: $emails,
            contacts: $contacts,
            parentCustomer: $parentCustomer,
            deleted: $deleted,
        );
    }

    public static function makeInvoice(IntegrationType $integration, array $input, Company $company): AccountingInvoice
    {
        // Accounting ID
        $accountingId = $input['accounting_id'] ?? '';
        unset($input['accounting_id']);
        unset($input['accounting_system']);

        // Customer
        $customer = null;
        if (isset($input['customer'])) {
            $customer = self::makeCustomer($integration, $input['customer']);
            unset($input['customer']);
        }

        // Voided
        $voided = $input['voided'] ?? false;
        unset($input['voided']);

        // PDF
        $pdf = $input['pdf'] ?? null;
        unset($input['pdf']);

        // Installments
        $installments = $input['installments'] ?? [];
        unset($input['installments']);

        // Deleted
        $deleted = $input['deleted'] ?? false;
        unset($input['deleted']);

        // Delivery
        $delivery = $input['delivery'] ?? [];
        unset($input['delivery']);

        // Balance
        $balance = $input['balance'] ?? null;
        unset($input['balance']);
        if (null !== $balance) {
            $currency = $input['currency'] ?? $company->currency;
            $balance = Money::fromDecimal($currency, $balance);
        }

        return new AccountingInvoice(
            integration: $integration,
            accountingId: $accountingId,
            customer: $customer,
            values: $input,
            voided: $voided,
            pdf: $pdf,
            installments: $installments,
            deleted: $deleted,
            delivery: $delivery,
            balance: $balance,
        );
    }

    public static function makeCreditNote(IntegrationType $integration, array $input, Company $company): AccountingCreditNote
    {
        // Accounting ID
        $accountingId = $input['accounting_id'] ?? '';
        unset($input['accounting_id']);
        unset($input['accounting_system']);

        // Customer
        $customer = null;
        if (isset($input['customer'])) {
            $customer = TransformerHelper::makeCustomer($integration, $input['customer']);
            unset($input['customer']);
        }

        // Voided
        $voided = $input['voided'] ?? false;
        unset($input['voided']);

        // PDF
        $pdf = $input['pdf'] ?? null;
        unset($input['pdf']);

        // Payments
        $payments = null;
        if (isset($input['payments'])) {
            foreach ($input['payments'] as $paymentInput) {
                $payments[] = self::makePayment($integration, $paymentInput, $company);
            }
            unset($input['payments']);
        }

        // Deleted
        $deleted = $input['deleted'] ?? false;
        unset($input['deleted']);

        // Balance
        $balance = $input['balance'] ?? null;
        unset($input['balance']);
        if (null !== $balance) {
            $currency = $input['currency'] ?? $company->currency;
            $balance = Money::fromDecimal($currency, $balance);
        }

        return new AccountingCreditNote(
            integration: $integration,
            accountingId: $accountingId,
            customer: $customer,
            values: $input,
            voided: $voided,
            pdf: $pdf,
            payments: $payments,
            deleted: $deleted,
            balance: $balance,
        );
    }

    public static function makePayment(IntegrationType $integration, array $input, Company $company): AccountingPayment
    {
        // Accounting ID
        $accountingId = $input['accounting_id'] ?? '';
        unset($input['accounting_id']);
        unset($input['accounting_system']);

        // Customer
        $customer = null;
        if (isset($input['customer'])) {
            $customer = TransformerHelper::makeCustomer($integration, $input['customer']);
            unset($input['customer']);
        }

        // Currency
        $currency = $input['currency'] ?? $company->currency;
        unset($input['currency']);

        // Splits
        $splits = [];
        if (isset($input['applied_to'])) {
            foreach ($input['applied_to'] as $splitInput) {
                $splits[] = self::deserializeSplit($splitInput, $currency, $integration, $company);
            }
            unset($input['applied_to']);
        }

        // Voided
        $voided = $input['voided'] ?? false;
        unset($input['voided']);

        // Deleted
        $deleted = $input['deleted'] ?? false;
        unset($input['deleted']);

        return new AccountingPayment(
            integration: $integration,
            accountingId: $accountingId,
            values: $input,
            currency: $currency,
            customer: $customer,
            appliedTo: $splits,
            voided: $voided,
            deleted: $deleted,
        );
    }

    private static function deserializeSplit(array $input, string $currency, IntegrationType $integration, Company $company): AccountingPaymentItem
    {
        // Amount
        $amount = Money::fromDecimal($currency, $input['amount'] ?? 0);

        // Type
        $type = $input['type'] ?? PaymentItemType::Invoice->value;

        // Invoice
        $invoice = null;
        if (isset($input['invoice'])) {
            $invoice = TransformerHelper::makeInvoice($integration, $input['invoice'], $company);
        }

        // Credit Note
        $creditNote = null;
        if (isset($input['credit_note'])) {
            $creditNote = TransformerHelper::makeCreditNote($integration, $input['credit_note'], $company);
        }

        // Document Type
        $documentType = $input['document_type'] ?? '';

        return new AccountingPaymentItem(
            amount: $amount,
            type: $type,
            invoice: $invoice,
            creditNote: $creditNote,
            documentType: $documentType,
        );
    }

    /**
     * Gets a value from a JSON object. If the value does not
     * exist then null is returned.
     * Nested values are set with a "/" separator.
     * e.g. customer/disabled_payment_methods/credit_card.
     */
    public static function getJsonValue(object $input, string $path): mixed
    {
        $paths = explode('/', $path);
        $field = array_shift($paths);

        // Process any field ending in [] as a collection of values instead of a single value
        if (str_ends_with($field, '[]')) {
            $field = substr($field, 0, -2);
            if (!isset($input->$field)) {
                return null;
            }

            $result = [];
            foreach ($input->$field as $value) {
                if (is_object($value) || count($paths)) {
                    $result[] = self::getJsonValue($value, implode('/', $paths));
                } else {
                    $result[] = $value;
                }
            }

            return $result;
        }

        if (!isset($input->$field)) {
            return null;
        }

        $value = $input->$field;
        if (count($paths) > 0) {
            if (!is_object($value)) {
                return null;
            }

            return self::getJsonValue($value, implode('/', $paths));
        }

        return $value;
    }

    /**
     * Gets a value from a Model. If the value does not
     * exist then null is returned.
     * Nested values are set with a "/" separator.
     * e.g. customer/disabled_payment_methods/credit_card.
     */
    public static function getModelValue(Model $input, string $path): mixed
    {
        $paths = explode('/', $path);
        $field = array_shift($paths);

        // Process any field ending in [] as a collection of values instead of a single value
        if (str_ends_with($field, '[]')) {
            $field = substr($field, 0, -2);
            if (!isset($input->$field)) {
                return null;
            }

            return self::getItemValue($input->$field, $paths);
        }

        if (str_ends_with($field, '()')) {
            $field = substr($field, 0, -2);
            if (!is_callable([$input, $field])) {
                return null;
            }

            $value = $input->$field();
        } else {
            $value = $input->$field;
        }

        return self::getItemValue($value, $paths);
    }

    public static function getArrayValue(array $input, mixed $path): mixed
    {
        $paths = explode('/', $path);
        $field = array_shift($paths);

        // Process any field ending in [] as a collection of values instead of a single value
        if (str_ends_with($field, '[]')) {
            $field = substr($field, 0, -2);
            if (!isset($input[$field])) {
                return null;
            }

            return self::getItemValue($input[$field], $paths);
        }

        return self::getItemValue($input[$field], $paths);
    }

    private static function getItemValue(mixed $value, array $paths): mixed
    {
        if (!$value) {
            return null;
        }

        if (is_array($value)) {
            foreach ($value as &$value2) {
                $value2 = self::getSingleItemValue($value2, $paths);
            }
        } else {
            $value = self::getSingleItemValue($value, $paths);
        }

        return $value;
    }

    private static function getSingleItemValue(mixed $value, array $paths): mixed
    {
        if (count($paths) > 0) {
            if ($value instanceof Model) {
                return self::getModelValue($value, implode('/', $paths));
            }
            if (is_object($value)) {
                return self::getJsonValue($value, implode('/', $paths));
            }
            if (is_array($value)) {
                return self::getArrayValue($value, implode('/', $paths));
            }

            return null;
        }

        // we do not want to return whole object, so we return id
        if ($value instanceof Model) {
            return $value->id();
        }

        return $value;
    }

    /**
     * Gets a value from a SimpleXML object. If the value does not
     * exist then null is returned. There is an option to specify
     * whether empty values are treated as null or empty string.
     * Nested values are set with a "/" separator.
     * e.g. customer/disabled_payment_methods/credit_card.
     */
    public static function getXmlValue(SimpleXMLElement $input, string $path, bool $treatEmptyAsNull): SimpleXMLElement|string|array|null
    {
        $paths = explode('/', $path);
        $field = array_shift($paths);

        // Process any field ending in [] as a collection of values instead of a single value
        if (str_ends_with($field, '[]')) {
            $field = substr($field, 0, -2);
            if (!isset($input->$field)) {
                return null;
            }

            $result = [];
            foreach ($input->$field as $value) {
                $result[] = self::getXmlValue($value, implode('/', $paths), $treatEmptyAsNull);
            }

            return $result;
        }

        if (!isset($input->$field)) {
            return null;
        }

        $value = $input->$field;
        if (count($paths) > 0) {
            return self::getXmlValue($value, implode('/', $paths), $treatEmptyAsNull);
        }

        $value = (string) $value;
        if ($treatEmptyAsNull) {
            return strlen($value) > 0 ? $value : null;
        }

        return $value;
    }

    /**
     * Sets the value on the output array with support for nesting.
     * Nested values are set with a "/" separator.
     * e.g. customer/disabled_payment_methods/credit_card.
     */
    public static function setValue(array &$output, string $path, mixed $value): void
    {
        $paths = explode('/', $path);
        $field = array_shift($paths);

        // Set a collection of values
        if (str_ends_with($field, '[]')) {
            if (!is_array($value)) {
                return;
            }

            $field = substr($field, 0, -2);
            if (!isset($output[$field]) || !is_array($output[$field])) {
                $output[$field] = [];
            }

            foreach ($value as $k => $value2) {
                if (!isset($output[$field][$k]) || !is_array($output[$field][$k])) {
                    $output[$field][$k] = [];
                }
                self::setValue($output[$field][$k], implode('/', $paths), $value2);
            }

            return;
        }

        // Set a single value
        if (count($paths) > 0) {
            if (!isset($output[$field]) || !is_array($output[$field])) {
                $output[$field] = [];
            }

            self::setValue($output[$field], implode('/', $paths), $value);
        } else {
            $output[$field] = $value;
        }
    }
}
