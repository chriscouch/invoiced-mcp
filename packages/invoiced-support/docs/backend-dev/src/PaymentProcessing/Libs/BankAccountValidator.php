<?php

namespace App\PaymentProcessing\Libs;

use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;

/**
 * Validates information contained with a bank account
 * value object.
 */
class BankAccountValidator
{
    /**
     * Checks if a card is valid.
     *
     * @throws InvalidBankAccountException if the bank account is invalid
     */
    public function validate(BankAccountValueObject $bankAccount): void
    {
        $this->checkAccountNumber($bankAccount);
        $this->checkRoutingNumber($bankAccount);
        $this->checkCurrency($bankAccount);
        $this->checkAccountType($bankAccount);
        $this->checkAccountHolderType($bankAccount);
    }

    /**
     * Checks that the bank account number is valid.
     * NOTE: This assumes a U.S. bank account number. Does not work with IBAN.
     *
     * @throws InvalidBankAccountException if the account number is invalid
     */
    public function checkAccountNumber(BankAccountValueObject $bankAccount): void
    {
        $number = $bankAccount->accountNumber;
        if (!$number) {
            throw new InvalidBankAccountException('Missing bank account number.', 'account_number');
        }

        // bank account numbers do not have a specified length
        // the range of 5 - 17 digits should cover all cases
        if (!preg_match('/^[\d]{5,17}$/', $number)) {
            throw new InvalidBankAccountException('Invalid bank account number.', 'account_number');
        }
    }

    /**
     * Checks that the bank routing number is a valid ABA routing number.
     *
     * @throws InvalidBankAccountException if the routing number is invalid
     */
    public function checkRoutingNumber(BankAccountValueObject $bankAccount): void
    {
        $number = $bankAccount->routingNumber;
        if (!$number) {
            throw new InvalidBankAccountException('Missing routing number.', 'routing_number');
        }

        // ABA routing numbers are 9 digits long
        if (!preg_match('/^[\d]{9}$/', $number)) {
            throw new InvalidBankAccountException('Invalid routing number.', 'routing_number');
        }
    }

    /**
     * Checks that the currency is valid.
     *
     * @throws InvalidBankAccountException if the currency is invalid
     */
    public function checkCurrency(BankAccountValueObject $bankAccount): void
    {
        $currency = $bankAccount->currency;
        if (!$currency) {
            throw new InvalidBankAccountException('Missing bank account currency.', 'currency');
        }

        if (3 != strlen($currency)) {
            throw new InvalidBankAccountException('Invalid bank account currency.', 'currency');
        }
    }

    /**
     * Checks that the account type is valid.
     *
     * @throws InvalidBankAccountException if the type is invalid
     */
    public function checkAccountType(BankAccountValueObject $bankAccount): void
    {
        $type = $bankAccount->type;

        // account type can be empty
        if (!$type) {
            return;
        }

        if (!in_array($type, [BankAccountValueObject::TYPE_CHECKING, BankAccountValueObject::TYPE_SAVINGS])) {
            throw new InvalidBankAccountException('Invalid account holder type. Must be `checking` or `savings`.', 'type');
        }
    }

    /**
     * Checks that the account holder type is valid.
     *
     * @throws InvalidBankAccountException if the account holder type is invalid
     */
    public function checkAccountHolderType(BankAccountValueObject $bankAccount): void
    {
        $type = $bankAccount->accountHolderType;

        // the account holder type can be empty
        if (!$type) {
            return;
        }

        if (!in_array($type, [BankAccountValueObject::TYPE_COMPANY, BankAccountValueObject::TYPE_INDIVIDUAL])) {
            throw new InvalidBankAccountException('Invalid account holder type. Must be `individual` or `company`.', 'account_holder_type');
        }
    }
}
