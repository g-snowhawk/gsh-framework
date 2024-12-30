<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Config;

/**
 * Contiguration parser class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Parser
{
    /**
     * Default block name.
     */
    public const DEFAULT_BLOCK = 'global';

    /**
     * Configure data array.
     *
     * @var array
     */
    private $configurations;

    /**
     * Object Constructer.
     *
     * @param string $inifile
     */
    public function __construct($inifile)
    {
        $this->configurations = parse_ini_file($inifile, true);
    }

    /**
     * Configuration data.
     *
     * @param string $arg
     *
     * @return mixed
     */
    public function param($arg = null, $value = null)
    {
        if (is_null($arg)) {
            return $this->configurations;
        }

        $arg = strtolower($arg);
        $keys = explode(':', $arg);
        if (count($keys) < 2) {
            $key = $keys[0];
            $block_name = self::DEFAULT_BLOCK;
        } else {
            $key = $keys[1];
            $block_name = $keys[0];
        }

        if ($value) {
            $this->configurations[$block_name][$key] = $value;
        }

        if (empty($key)) {
            return (isset($this->configurations[$block_name])) ? $this->configurations[$block_name] : null;
        }

        return (isset($this->configurations[$block_name]) && isset($this->configurations[$block_name][$key])) ? $this->configurations[$block_name][$key] : null;
    }

    public function merge($more)
    {
        if (!is_array($more)) {
            if (file_exists($more)) {
                $more = parse_ini_file($more, true);
            } else {
                trigger_error('Merge configuration failed', E_USER_WARNING);
            }
        }
        $this->configurations = array_merge($this->configurations, $more);
    }

    /**
     * Save configurations into the file.
     *
     * @param array $configurations
     *
     * @return bool
     */
    public static function toString(array $configurations)
    {
        ksort($configurations);
        $source = '';
        $global = '';
        foreach ($configurations as $block_name => $pair) {
            if (is_array($pair)) {
                $i = 0;
                foreach ($pair as $key => $value) {
                    if (is_int($key)) {
                        $source .= $block_name.'[] = '.self::quote($value).PHP_EOL;
                        continue;
                    }
                    if ($i === 0) {
                        $source .= PHP_EOL."[$block_name]".PHP_EOL;
                        ++$i;
                    }
                    if (!is_array($value)) {
                        $source .= $key.' = '.self::quote($value).PHP_EOL;
                        continue;
                    }
                    $source .= self::pairToLine($key, $value);
                }
            } else {
                $global .= $block_name.' = '.self::quote($pair).PHP_EOL;
            }
        }

        return trim($global.$source);
    }

    /**
     * Key/Value pair to string.
     *
     * @param string $key
     * @param mixed  $value
     * @param bool   $recursive
     *
     * @return string
     */
    private static function pairToLine($key, $value, $recursive = false)
    {
        if (!is_array($value)) {
            return $key.' = '.self::quote($value).PHP_EOL;
        }
        $source = '';
        foreach ($value as $k => $v) {
            $name = (is_int($k)) ? $key.'[]' : $key.'['.$k.']';
            if (!is_array($v)) {
                $source .= $name.' = '.self::quote($v).PHP_EOL;
                continue;
            }
            if ($recursive) {
                $source .= self::pairToLine($name, $v);
            }
        }

        return $source;
    }

    private static function quote($str)
    {
        if (preg_match('/[\W_]+/', $str)) {
            $str = '"' . $str . '"';
        }

        return $str;
    }
}
