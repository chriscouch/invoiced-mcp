<?php

namespace App\Tests\Imports;

use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Tests\AppTestCase;

class ImportHelperTest extends AppTestCase
{
    public function testParseDate(): void
    {
        $validDates = [
            'Jan-01-2014',
            '01-Jan-2014',
            '2014-01-01',
            '20140101',
            'January-01-2014',
        ];

        foreach ($validDates as $date) {
            $this->assertEquals((int) mktime(6, 0, 0, 1, 1, 2014), ImportHelper::parseDateUnixTimestamp($date, null, false));
        }
    }

    public function testParseDateUsCountry(): void
    {
        $validDates = [
            'Jan-01-2014',
            '01-Jan-2014',
            '2014-01-01',
            '20140101',
            'January-01-2014',
            '01/01/2014',
            '1/01/2014',
            '1/1/2014',
            '01/01/14',
            '1/01/14',
            '1/1/14',
        ];

        foreach ($validDates as $date) {
            $this->assertEquals((int) mktime(6, 0, 0, 1, 1, 2014), ImportHelper::parseDateUnixTimestamp($date, 'US', false));
        }
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testParseDateInvalid(): void
    {
        $invalidDates = [
            '01/01/2010',
            '04/21/2018',
            '03-04-2010',
            '12-13-2018',
            '12-13',
            '03-14-18',
            'Jan-01-14',
            '01-Mar-18',
        ];

        foreach ($invalidDates as $date) {
            try {
                $timestamp = ImportHelper::parseDateUnixTimestamp($date, null, false);
            } catch (ValidationException $e) {
                continue;
            }

            $this->fail("Invalid date format should have been rejected: $date (produced timestamp $timestamp)");
        }
    }

    public function testParseFloat(): void
    {
        $this->assertEquals(1.0, ImportHelper::parseFloat('1'));
        $this->assertEquals(1000, ImportHelper::parseFloat('1,000'));
        $this->assertEquals(5123.24, ImportHelper::parseFloat('$5,123.24'));
        $this->assertEquals(513.15, ImportHelper::parseFloat('£513.15'));
        $this->assertEquals(1001.10, ImportHelper::parseFloat('1,001.10'));
        $this->assertEquals(-1.23, ImportHelper::parseFloat('-1.23'));
        $this->assertEquals(-54.40, ImportHelper::parseFloat('-£54.40'));
        $this->assertEquals(-1, ImportHelper::parseFloat('-$1'));

        // handle excel's special number formatting
        $this->assertEquals(0, ImportHelper::parseFloat('$-   '));
        $this->assertEquals(-27.13, ImportHelper::parseFloat('$(27.13)'));
    }

    public function testParseFloatInvalid(): void
    {
        $this->expectException(ValidationException::class);
        ImportHelper::parseFloat('not a number');
    }

    public function testParseFloatInvalid2(): void
    {
        $this->expectException(ValidationException::class);
        ImportHelper::parseFloat('-1.23-');
    }

    public function testParseInt(): void
    {
        $this->assertEquals(1, ImportHelper::parseInt('1'));
        $this->assertEquals(1000, ImportHelper::parseInt('1,000'));
        $this->assertEquals(5123, ImportHelper::parseInt('$5,123.24'));
        $this->assertEquals(513, ImportHelper::parseInt('£513.15'));
        $this->assertEquals(1001, ImportHelper::parseInt('1,001.10'));
        $this->assertEquals(-1, ImportHelper::parseInt('-1.23'));
        $this->assertEquals(-54, ImportHelper::parseInt('-£54.40'));
        $this->assertEquals(-1, ImportHelper::parseInt('-$1'));

        // handle excel's special number formatting
        $this->assertEquals(0, ImportHelper::parseInt('$-   '));
        $this->assertEquals(-27, ImportHelper::parseInt('$(27.13)'));
    }

    public function testParseIntInvalid(): void
    {
        $this->expectException(ValidationException::class);
        ImportHelper::parseInt('not a number');
    }

    public function testParseIntInvalid2(): void
    {
        $this->expectException(ValidationException::class);
        ImportHelper::parseInt('-1.23-');
    }
}
