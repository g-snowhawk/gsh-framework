<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common;

use PDO;

define('UTF32_BIG_ENDIAN_BOM', chr(0x00).chr(0x00).chr(0xFE).chr(0xFF));
define('UTF32_LITTLE_ENDIAN_BOM', chr(0xFF).chr(0xFE).chr(0x00).chr(0x00));
define('UTF16_BIG_ENDIAN_BOM', chr(0xFE).chr(0xFF));
define('UTF16_LITTLE_ENDIAN_BOM', chr(0xFF).chr(0xFE));
define('UTF8_BOM', chr(0xEF).chr(0xBB).chr(0xBF));

/**
 * Text control class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Text
{
    /**
     * Map for mb_encode_numericentity|mb_decode_numericentity
     */
    public const CONVMAP = [0x80, 0x10ffff, 0, 0x1fffff];

    /**
     * supported encodings.
     *
     * @param string $enc
     *
     * @return mixed
     */
    public static function checkEncodings($enc)
    {
        $encodings = mb_list_encodings();
        foreach ($encodings as $encoding) {
            if (strtoupper($encoding) === strtoupper($enc)) {
                return $encoding;
            }
        }

        return false;
    }

    /**
     * convert encoding.
     *
     * @param string $str
     * @param string $encodingTo
     * @param string $encodingFrom
     *
     * @return string
     */
    public static function convert($str, $encodingTo = 'utf-8', $encodingFrom = null)
    {
        if (empty($encodingTo)) {
            $encodingTo = mb_internal_encoding();
        }
        $defaultSetting = mb_detect_order();
        mb_detect_order('ASCII, JIS, UTF-16, UTF-8, EUC-JP, SJIS-WIN, SJIS');
        if (is_array($str)) {
            foreach ($str as $n => $value) {
                $str[$n] = self::convert($value, $encodingTo, $encodingFrom);
            }
        } else {
            if (is_null($encodingFrom)) {
                $encodingFrom = preg_replace('/BOM$/', '', self::detectEncoding($str));
            } elseif (strtoupper($encodingFrom) === 'UTF-8BOM') {
                $encodingFrom = 'UTF-8';
                $str = str_replace(UTF8_BOM, '', $str);
            }

            if (!empty($encodingTo) && !empty($encodingFrom) && $encodingTo != $encodingFrom) {
                $str = mb_convert_encoding($str, $encodingTo, $encodingFrom);
            }
        }
        // rewind setting
        mb_detect_order($defaultSetting);

        return $str;
    }

    /**
     * convert string to boolean.
     *
     * @param string $str
     *
     * @return bool
     */
    public static function convertBoolean($str)
    {
        $trues = ['true', 'yes', 'on', '1'];

        return in_array(strtolower($str), $trues);
    }

    /**
     * detect encoding.
     *
     * @param string $str
     *
     * @return string
     */
    public static function detectEncoding($str)
    {
        // Unicode with BOM
        $encoding = self::detectUtfEncoding($str);
        if (!empty($encoding)) {
            return $encoding;
        }

        // ASCII
        if (preg_match('/^[\x21-\x7e\s]+$/', $str)) {
            return 'ASCII';
        }

        return mb_detect_encoding(
            $str,
            'JIS,UTF-16BE,UTF-16LE,UTF-16,UTF-8,CP932,EUC-JP,SJIS',
            true
        );
    }

    /**
     * Detect UTF Encoding.
     *
     * @param string $str
     *
     * @return string
     */
    public static function detectUtfEncoding($str)
    {
        $f2 = substr($str, 0, 2);
        $f3 = substr($str, 0, 3);
        $f4 = substr($str, 0, 3);

        if ($f3 === UTF8_BOM) {
            return 'UTF-8BOM';
        } elseif ($f4 === UTF32_BIG_ENDIAN_BOM) {
            return 'UTF-32BE';
        } elseif ($f4 === UTF32_LITTLE_ENDIAN_BOM) {
            return 'UTF-32LE';
        } elseif ($f2 === UTF16_BIG_ENDIAN_BOM) {
            return 'UTF-16BE';
        } elseif ($f2 === UTF16_LITTLE_ENDIAN_BOM) {
            return 'UTF-16LE';
        }
    }

    /**
     * explode string.
     *
     * @return array
     */
    public static function explode()
    {
        $asset = func_get_args();
        $sep = array_shift($asset);
        $str = array_shift($asset);

        $rep = ($sep === '.') ? ',' : '.';

        $str = str_replace('\\'.$sep, '\\'.$rep, $str);

        return array_map(function ($value) use ($sep, $rep) {
            return str_replace('\\'.$rep, $sep, trim($value));
        }, explode($sep, $str));
    }

    /**
     * HTML specialchars.
     *
     * @param string $str
     *
     * @return string
     */
    public static function htmlspecialchars($str)
    {
        $pattern = ['/&([a-z]+);/', '/&#([0-9]+);/'];
        $replace = ['{$amp}'.'$1;', '{$amp}#'.'$1;'];
        $str = preg_replace($pattern, $replace, $str);
        $str = htmlspecialchars($str);
        $str = str_replace('{$amp}', '&', $str);

        return $str;
    }

    /**
     * implode array.
     *
     * @return string
     */
    public static function implode()
    {
        $asset = func_get_args();
        $sep = array_shift($asset);

        return implode($sep, array_filter($asset));
    }

    /**
     * check empty variables.
     *
     * @var mixed
     *
     * @return bool
     */
    public static function is_blank($var)
    {
        if ($var === 0 || $var === '0' || $var === '0.0') {
            return false;
        }

        return empty($var);
    }

    /**
     * Removing Unicode BOM.
     *
     * @param string $str
     * @param string $bom
     *
     * @return string
     */
    public static function removeBOM($str, $bom = UTF8_BOM)
    {
        return preg_replace('/^'.preg_quote($bom, '/').'/', '', $str);
    }

    /**
     * Text wrapping.
     *
     * @param string $text
     * @param number $col
     * @param string $delimiter
     * @param string $enc
     *
     * @return string
     */
    public static function wrap($text, $col, $delimiter, $enc = 'utf-8')
    {
        $tmp = preg_split("/(\r\n|\r|\n)/", $text);
        $line = '';
        foreach ($tmp as $str) {
            $bytes = 0;
            for ($i = 0, $len = mb_strlen($str, $enc); $i < $len; ++$i) {
                $char = mb_substr($str, $i, 1, $enc);
                $bytes += mb_strwidth($char, $enc);
                if ($bytes > $col) {
                    $line .= $delimiter;
                    $bytes = mb_strwidth($char, $enc);
                }
                $line .= $char;
            }
            $line .= $delimiter;
        }
        $pattern = '/'.preg_quote($delimiter, '/').'$/';

        return preg_replace($pattern, '', $line);
    }

    public static function strtotime($str)
    {
        if (false !== $time = strtotime($str)) {
            return $time;
        }
        $text = mb_convert_kana($str, 'a');
        $text = str_replace(['年', '月'], '/', $text);
        $text = str_replace(['時', '分'], ':', $text);
        $text = str_replace(['日', '秒'], ' ', $text);
        $text = preg_replace('/[ ]+/', ' ', $text);

        return strtotime(trim($text));
    }

    public static function formatPhonenumber($value, $separator = '-')
    {
        $source = __DIR__ . '/db/areacode';

        if (empty($value) || !file_exists($source)) {
            return $value;
        }

        $db = new PDO("sqlite:$source");
        $stat = $db->prepare('SELECT * FROM phone WHERE areacode = ?');

        $number = preg_replace('/[^0-9]+/', '', $value);

        for ($i = 5; $i > 0; $i--) {
            $areacode = substr($number, 0, $i);
            $stat->execute([$areacode]);
            while ($unit = $stat->fetch(PDO::FETCH_ASSOC)) {
                $len = strlen($unit['digits']);
                $local = substr($number, $i, $len);
                $phone = substr($number, $i + $len);
                $value = implode($separator, [$areacode, $local, $phone]);
            }
        }

        return $value;
    }
}
