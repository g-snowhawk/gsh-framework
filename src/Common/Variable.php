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

/**
 * Complement the variable processing.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Variable
{
    /**
     * Check hash.
     *
     * @param array $var
     *
     * @return bool
     */
    public static function isHash(&$var)
    {
        if (is_null($var) || !is_array($var)) {
            return false;
        }

        return $var !== array_values($var);
    }

    /**
     * Check null or empty value.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isEmpty($value, bool $disallow_whitespace = false, bool $with_mbspace = false): bool
    {
        return self::empty($value, $disallow_whitespace, $with_mbspace);
    }

    public static function empty($value, bool $disallow_whitespace = false, bool $with_mbspace = false): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }
        if (is_array($value)) {
            return empty($value);
        }
        if ($disallow_whitespace !== false) {
            if ($with_mbspace !== false) {
                $value = mb_convert_kana($value, 's');
            }

            return (trim($value) === '');
        }

        return false;
    }

    /**
     * decimal to bits
     *
     * @param $decimal
     *
     * return array
     */
    public static function decToBitArray(int $decimal): array
    {
        $bin = decbin($decimal);
        $bits = array_filter(array_reverse(str_split($bin)));

        foreach ($bits as $i => $bit) {
            $bits[$i] = pow(2, $i);
        }

        $bits = array_values($bits);

        return $bits;
    }
}
