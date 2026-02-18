<?php

namespace App\Tests\Companies\Libs;

use App\Companies\ValueObjects\HighlightColor;
use App\Tests\AppTestCase;
use InvalidArgumentException;

class HighlightColorTest extends AppTestCase
{
    public function testGetMinBrightDiff(): void
    {
        $color = new HighlightColor('cccccc');
        $this->assertEquals(126, $color->getMinBrightDiff());
    }

    public function testGetMinColorDiff(): void
    {
        $color = new HighlightColor('000');
        $this->assertEquals(500, $color->getMinColorDiff());
    }

    public function testGetForeground(): void
    {
        $test = [
            '000' => 'ffffff',
            '#fff' => '404040',
            '#007dc3' => 'ffffff',
            '4b94d9' => '000000',
            'cccccc' => '000000',
        ];

        foreach ($test as $bg => $fg) {
            $color = new HighlightColor($bg);
            $this->assertEquals($fg, $color->getForeground(), "Background color #$bg did not produce foreground #$fg");
        }
    }

    public function testInvalidColor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $color = new HighlightColor('not a color');
    }
}
