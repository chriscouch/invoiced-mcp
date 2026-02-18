<?php

namespace App\Tests\Core\I18n;

use App\Core\I18n\PhoneFormatter;
use App\Tests\AppTestCase;
use libphonenumber\PhoneNumberFormat;

class PhoneFormatterTest extends AppTestCase
{
    /**
     * @dataProvider provideFormatData
     */
    public function testFormat(string $expectedResult, string $input, ?string $country, int $format = PhoneNumberFormat::NATIONAL): void
    {
        $this->assertEquals($expectedResult, PhoneFormatter::format($input, $country, $format));
    }

    public function provideFormatData(): array
    {
        return [
            [
                '',
                '',
                'US',
            ],
            [
                '1234',
                '1234',
                'US',
            ],
            [
                '(512) 123-4567',
                '512-123-4567',
                'US',
            ],
            [
                '(512) 123-4567',
                '(512) 123-4567',
                'US',
            ],
            [
                '(512) 123-4567',
                '5121234567',
                'US',
            ],
            [
                '+1 512-123-4567',
                '15121234567',
                'US',
                PhoneNumberFormat::INTERNATIONAL,
            ],
            [
                '07942 327249',
                '07942327249',
                'GB',
            ],
        ];
    }
}
