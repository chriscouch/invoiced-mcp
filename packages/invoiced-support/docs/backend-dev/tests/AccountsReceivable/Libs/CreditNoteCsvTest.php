<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Libs\CreditNoteCsv;
use App\AccountsReceivable\Models\CreditNote;
use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Models\CustomField;
use App\Tests\AppTestCase;

class CreditNoteCsvTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasCoupon();
        self::hasTaxRate();

        self::$creditNote = new CreditNote();
        self::$creditNote->setCustomer(self::$customer);
        self::$creditNote->number = 'INV-001';
        self::$creditNote->date = (int) gmmktime(0, 0, 0, 6, 12, 2020);
        self::$creditNote->metadata = (object) [
            'credit_note_custom_field' => 'Hello',
        ];
        self::$creditNote->items = [
            [
                'quantity' => 1,
                'name' => 'test',
                'unit_cost' => 105.26,
                'discounts' => [
                    [
                        'coupon' => 'coupon',
                    ],
                ],
                'metadata' => [
                    'line_item_custom_field' => 'World',
                ],
            ],
            [
                'quantity' => 12.045,
                'description' => 'fractional item',
                'unit_cost' => 1,
            ],
            [
                'quantity' => 10,
                'description' => 'negative item',
                'unit_cost' => -1,
            ],
        ];
        self::$creditNote->discounts = [
            [
                'coupon' => 'coupon',
            ],
        ];
        self::$creditNote->taxes = [
            [
                'tax_rate' => 'tax',
            ],
        ];
        self::$creditNote->currency = 'eur';
        self::$creditNote->notes = 'test';
        self::$creditNote->saveOrFail();

        $customField1 = new CustomField();
        $customField1->object = ObjectType::CreditNote->typeName();
        $customField1->id = 'credit_note_custom_field';
        $customField1->name = 'Credit Note Custom Field';
        $customField1->saveOrFail();

        $customField2 = new CustomField();
        $customField2->object = ObjectType::LineItem->typeName();
        $customField2->id = 'line_item_custom_field';
        $customField2->name = 'Line Item Custom Field';
        $customField2->saveOrFail();

        $customField3 = new CustomField();
        $customField3->object = ObjectType::CreditNote->typeName();
        $customField3->id = 'generic_custom_field';
        $customField3->name = 'Generic Custom Field';
        $customField3->saveOrFail();
    }

    private function getCsv(bool $forCustomer): CreditNoteCsv
    {
        return new CreditNoteCsv(self::$creditNote, $forCustomer, self::getService('translator'));
    }

    public function testToCsvForCustomer(): void
    {
        $csv = $this->getCsv(true);

        $expected = [
            [
                'from',
                'email',
                'address_1',
                'address_2',
                'city',
                'state',
                'postal_code',
                'country',
                'number',
                'date',
                'currency',
                'total',
                'credit_note_custom_field',
                'generic_custom_field',
            ],
            [
                self::$company->name,
                self::$company->email,
                self::$company->address1,
                self::$company->address2,
                self::$company->city,
                self::$company->state,
                self::$company->postal_code,
                self::$company->country,
                self::$creditNote->number,
                '2020-06-12',
                'eur',
                101.80,
                'Hello',
                null,
            ],
            [
                ' ',
            ],
            [
                'item',
                'description',
                'quantity',
                'unit_cost',
                'line_total',
                'discount',
                'tax',
                'line_item_custom_field',
            ],
            [
                'test',
                '',
                1.0,
                105.26,
                105.26,
                5.26,
                '',
                'World',
            ],
            [
                '',
                'fractional item',
                12.045,
                1.0,
                12.05,
                '',
                '',
                null,
            ],
            [
                '',
                'negative item',
                10.0,
                -1.0,
                -10.0,
                '',
                '',
                null,
            ],
            [
                'Coupon',
                '',
                '',
                '',
                '',
                5.1,
                '',
            ],
            [
                'Tax',
                '',
                '',
                '',
                '',
                '',
                4.85,
            ],
        ];

        $this->assertEquals($expected, $csv->buildLines());
    }

    public function testToCsvForBusiness(): void
    {
        $csv = $this->getCsv(false);

        $expected = [
            [
                'customer',
                'email',
                'address_1',
                'address_2',
                'city',
                'state',
                'postal_code',
                'country',
                'number',
                'date',
                'currency',
                'total',
                'credit_note_custom_field',
                'generic_custom_field',
            ],
            [
                self::$customer->name,
                self::$customer->email,
                self::$customer->address1,
                self::$customer->address2,
                self::$customer->city,
                self::$customer->state,
                self::$customer->postal_code,
                self::$customer->country,
                self::$creditNote->number,
                '2020-06-12',
                'eur',
                101.80,
                'Hello',
                null,
            ],
            [
                ' ',
            ],
            [
                'item',
                'description',
                'quantity',
                'unit_cost',
                'line_total',
                'discount',
                'tax',
                'line_item_custom_field',
            ],
            [
                'test',
                '',
                1.0,
                105.26,
                105.26,
                5.26,
                '',
                'World',
            ],
            [
                '',
                'fractional item',
                12.045,
                1.0,
                12.05,
                '',
                '',
                null,
            ],
            [
                '',
                'negative item',
                10.0,
                -1.0,
                -10.0,
                '',
                '',
                null,
            ],
            [
                'Coupon',
                '',
                '',
                '',
                '',
                5.1,
                '',
            ],
            [
                'Tax',
                '',
                '',
                '',
                '',
                '',
                4.85,
            ],
        ];

        $this->assertEquals($expected, $csv->buildLines());
    }
}
