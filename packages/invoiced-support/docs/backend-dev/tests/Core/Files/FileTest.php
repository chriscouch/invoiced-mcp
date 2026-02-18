<?php

namespace App\Tests\Core\Files;

use App\Core\Files\Models\File;
use App\Tests\AppTestCase;

class FileTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function provideUrls(): array
    {
        $input = [
            'https://invoiced-attachments.s3.us-east-2.amazonaws.com/OcV0itPjT2uTi3ModZyd_Invoiced TOS Enterprise #1234.docx',
            'https://invoiced-attachments.s3.us-east-2.amazonaws.com/1MdT2ps8RRyotp3G9RFw_Invoiced TOS Enterprise.docx',
            'https://invoiced-attachments.s3.us-east-2.amazonaws.com/Px3yiDqQQ2S9Fn9snIQy_invoiced-icon-v2-light.png',
            // should not touch URLs of files stored outside of our AWS bucket
            'http://invoiced.com/isystem/upload/ CNAMay 21st, 2017.pdf',
        ];

        $output = [
            'https://invoiced-attachments.s3.us-east-2.amazonaws.com/OcV0itPjT2uTi3ModZyd_Invoiced+TOS+Enterprise+%231234.docx',
            'https://invoiced-attachments.s3.us-east-2.amazonaws.com/1MdT2ps8RRyotp3G9RFw_Invoiced+TOS+Enterprise.docx',
            'https://invoiced-attachments.s3.us-east-2.amazonaws.com/Px3yiDqQQ2S9Fn9snIQy_invoiced-icon-v2-light.png',
            'http://invoiced.com/isystem/upload/ CNAMay 21st, 2017.pdf',
        ];

        return [
            'undefined behavior' => [
                [
                    'date' => null,
                    'input' => $input,
                ],
                $output,
            ],
            'old behavior' => [
                [
                    'date' => 1691157240,
                    'input' => $input,
                ],
                $output,
            ],
            'new behavior' => [
                [
                    'date' => 1691157241,
                    'input' => $input,
                ],
                $input,
            ],
        ];
    }

    /**
     * @dataProvider provideUrls
     */
    public function testUrlEncoding(array $input, array $output): void
    {
        $file = new File();
        $file->created_at = $input['date'];

        foreach ($input['input'] as $key => $url) {
            $file->url = $url;
            $this->assertEquals($output[$key], $file->url);
        }
    }

    public function testCreate(): void
    {
        self::$file = new File();
        $this->assertTrue(self::$file->create([
            'name' => 'something.pdf',
            'size' => 100000,
            'type' => 'application/pdf',
            'url' => 'https://files.invoiced.com/somehash',
            'bucket_name' => 'bucket_name',
            'bucket_region' => 'us-1',
            'key' => 'somekey',
            's3_environment' => 'test',
        ]));

        $this->assertEquals(self::$company->id(), self::$file->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $files = File::all();

        $this->assertCount(1, $files);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$file->id(),
            'object' => 'file',
            'name' => 'something.pdf',
            'size' => 100000,
            'type' => 'application/pdf',
            'url' => 'https://files.invoiced.com/somehash',
            'created_at' => self::$file->created_at,
            'updated_at' => self::$file->updated_at,
            'bucket_name' => 'bucket_name',
            'bucket_region' => 'us-1',
            'key' => 'somekey',
            's3_environment' => 'test',
        ];

        $this->assertEquals($expected, self::$file->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$file->name = 'test.pdf';
        $this->assertTrue(self::$file->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$file->delete());
    }
}
