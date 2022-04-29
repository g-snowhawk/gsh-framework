<?php

// Load composer auto loader
require_once 'vendor/autoload.php';

use Gsnowhawk\Common\Lang;
use PHPUnit\Framework\TestCase;

class LangTest extends TestCase
{
    /**
     * @dataProvider translateProvider
     */
    public function testTranslate($key, $expected, $locale = null)
    {
        $actual = Lang::translate($key, null, $locale);
        $this->assertEquals($expected, $actual);
    }

    public function translateProvider(): array
    {
        return [
            ['greeting.good_morning', 'おはよう'],
            ['greeting.good_afternoon', 'こんにちは'],
            ['greeting.good_evening', 'こんばんわ'],
            ['greeting.good_night', 'おやすみ'],
            ['greeting.good_morning', "g'mornin", 'En'],
            ['greeting.good_afternoon', 'Good afternoon', 'En'],
            ['greeting.good_evening', 'Good evening', 'En'],
            ['greeting.good_night', "g'nite", 'En'],
        ];
    }
}
