<?php

namespace App\Tests\Metadata\ValueObjects;

use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\Metadata\Exception\MetadataStorageException;
use App\Metadata\ValueObjects\AttributeBoolean;
use App\Metadata\ValueObjects\AttributeDecimal;
use App\Metadata\ValueObjects\AttributeMoney;
use App\Metadata\ValueObjects\AttributeString;
use App\Tests\AppTestCase;

class AttributeTest extends AppTestCase
{
    public function testValueDecimal(): void
    {
        $attribute = new AttributeDecimal();
        foreach ([new Invoice(), [], new \stdClass(), 'test', null, Money::fromDecimal('usd', 1)] as $key => $item) {
            try {
                $attribute->setValue($item);
                $this->assertTrue(false, "No error thrown $key");
            } catch (MetadataStorageException $e) {
            }
        }

        foreach ([0, 1, 1.01, -1, true, false] as $key => $item) {
            $attribute->setValue($item);
        }

        $this->assertTrue(true);
    }

    public function testValueInteger(): void
    {
        $attribute = new AttributeBoolean();
        foreach ([new Invoice(), -1, [], new \stdClass(), 'test', null, 1.01, Money::fromDecimal('usd', 1)] as $key => $item) {
            try {
                $attribute->setValue($item);
                $this->assertTrue(false, "No error thrown $key");
            } catch (MetadataStorageException $e) {
            }
        }

        foreach ([0, 1, '0', '1', true, false] as $key => $item) {
            $attribute->setValue($item);
        }
        $this->assertTrue(true);
    }

    public function testValueMoney(): void
    {
        $attribute = new AttributeMoney();
        foreach ([new Invoice(), [], new \stdClass(), 'test', null, 1.01, 0, 1, -1, true, false] as $key => $item) {
            try {
                $attribute->setValue($item);
                $this->assertTrue(false, "No error thrown $key");
            } catch (MetadataStorageException $e) {
            }
        }

        foreach ([Money::fromDecimal('usd', 1), ['currency' => 'usd', 'amount' => 1]] as $key => $item) {
            $attribute->setValue($item);
        }
        $this->assertTrue(true);
    }

    public function testValueString(): void
    {
        $attribute = new AttributeString();
        foreach ([Money::fromDecimal('usd', 1), ['currency' => 'usd', 'amount' => 1], 'test', null, 1.01, 0, 1, -1, true, false, new Invoice(), [], new \stdClass()] as $key => $item) {
            $attribute->setValue($item);
        }
        $this->assertTrue(true);
    }
}
