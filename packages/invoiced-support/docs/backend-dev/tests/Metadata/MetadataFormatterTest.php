<?php

namespace App\Tests\Metadata;

use App\Metadata\Libs\MetadataFormatter;
use App\Metadata\Models\CustomField;
use App\Tests\AppTestCase;

class MetadataFormatterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$company->useTimezone();
    }

    public function getFormatter(): MetadataFormatter
    {
        return new MetadataFormatter(self::$company, self::$customer);
    }

    public function testFormat(): void
    {
        $formatter = $this->getFormatter();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_BOOLEAN;

        $this->assertEquals('Yes', $formatter->format($customField, '1'));
    }

    public function testFormatString(): void
    {
        $formatter = $this->getFormatter();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_STRING;

        $this->assertEquals('blah', $formatter->format_string('blah', $customField));
    }

    public function testFormatEnum(): void
    {
        $formatter = $this->getFormatter();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_ENUM;

        $this->assertEquals('test', $formatter->format_enum('test', $customField));
        $this->assertEquals('test 2', $formatter->format_enum('test 2', $customField));
    }

    public function testFormatBoolean(): void
    {
        $formatter = $this->getFormatter();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_BOOLEAN;

        $this->assertEquals('Yes', $formatter->format_boolean('1', $customField));
        $this->assertEquals('No', $formatter->format_boolean('0', $customField));
    }

    public function testFormatDouble(): void
    {
        $formatter = $this->getFormatter();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_DOUBLE;

        $this->assertEquals('1', $formatter->format_double('1', $customField));
        $this->assertEquals('1.234', $formatter->format_double('1.234', $customField));
    }

    public function testFormatDate(): void
    {
        $formatter = $this->getFormatter();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_DATE;

        $this->assertEquals('Jun 28, 2018', $formatter->format_date('1530156392', $customField));
    }

    public function testFormatMoney(): void
    {
        $formatter = $this->getFormatter();

        $customField = new CustomField();
        $customField->type = CustomField::FIELD_TYPE_MONEY;

        $this->assertEquals('$100.00', $formatter->format_money('usd,100', $customField));
        $this->assertEquals('€100.00', $formatter->format_money('eur,100', $customField));
    }
}
