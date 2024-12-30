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

use Gsnowhawk\Common\Text;

/**
 * Date class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Date
{
    /**
     * Check expire.
     *
     * @param string $date
     * @param number $expire
     * @param string $ymd
     *
     * @return bool
     */
    public static function expire($date, $expire, $ymd = 'month')
    {
        if ((int) $expire === 0) {
            return true;
        }
        $a = strtotime("+{$expire} {$ymd}", strtotime($date));

        return $a - time() > 0;
    }

    /**
     * Convert to wareki from timestamp
     *
     * @param string $format
     * @param int $timestamp
     * @param bool $gannen
     *
     * @return string
     */
    public static function wareki($format, $timestamp = null, $gannen = false)
    {
        if (is_null($timestamp)) {
            $timestamp = time();
        }

        $year = (int)date('Y', $timestamp);
        $gengo = '';
        $gengo_short = '';
        if ($timestamp < strtotime('1868-01-25')) {
            $gengo = '';
            $gengo_short = '';
        }

        $decode = function ($entity) {
            return mb_decode_numericentity($entity, Text::CONVMAP, 'UTF-8');
        };

        // Meiji
        if ($timestamp < strtotime('1912-07-30')) {
            $gengo = $decode('&#26126;&#27835;');
            $gengo_short = 'M';
            $year -= 1867;
        }
        // Taisho
        elseif ($timestamp < strtotime('1926-12-25')) {
            $gengo = $decode('&#22823;&#27491;');
            $gengo_short = 'T';
            $year -= 1911;
        }
        // Showa
        elseif ($timestamp < strtotime('1989-0l-08')) {
            $gengo = $decode('&#26157;&#21644;');
            $gengo_short = 'S';
            $year -= 1925;
        }
        // Heisei
        elseif ($timestamp < strtotime('2019-05-01')) {
            $gengo = $decode('&#24179;&#25104;');
            $gengo_short = 'H';
            $year -= 1988;
        }
        // Reiwa
        else {
            $gengo = $decode('&#20196;&#21644;');
            $gengo_short = 'R';
            $year -= 2018;
        }

        $wareki = str_replace(['Y','y'], ['Q','q'], $format);
        $datestr = date($wareki, $timestamp);

        $full_gengo = "$gengo$year";
        if ($gannen !== false && $year === 1) {
            $full_gengo = $gengo.$decode('&#20803;');
        }

        return str_replace(['Q','q'], [$full_gengo,"$gengo_short$year"], $datestr);
    }

    public static function quote($format)
    {
        $characters = str_split('dDjlNSwzWFmMntLoYyaABgGhHisuveIOPTZcrU');
        foreach ($characters as $character) {
            $format = str_replace($character, '\\'.$character, $format);
        }

        return $format;
    }
}
