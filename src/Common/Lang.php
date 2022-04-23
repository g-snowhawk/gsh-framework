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
 * Multi language translator.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Lang
{
    /**
     * Getter method.
     *
     * @param string $name
     *
     * @return string
     */
    public function __get($name)
    {
        if (false === property_exists($this, $name) && false === property_exists(__CLASS__, $name)) {
            return false;
        }

        return $this->$key;
    }

    /**
     * Getter method.
     *
     * @param string $name
     *
     * @return string
     */
    public function __isset($name)
    {
        if (false === property_exists($this, $name)
            && false === property_exists(__CLASS__, $name)
            && false === property_exists($this, "_$name")
            && false === property_exists(__CLASS__, "_$name")
        ) {
            return false;
        }

        return $this->$key;
    }

    /**
     * Translate Language.
     *
     * @param string $key
     * @param mixed  $package
     * @param mixed  $locale
     *
     * @return string
     */
    public static function translate($key, $package = null, $locale = null)
    {
        if (empty($locale)) {
            if (false === ($locale = getenv('GSH_LOCALE'))) {
                $locale = 'En';
            }
        }
        $package_suffix = '\\Lang\\'.$locale;

        if (is_null($package)) {
            $caller = debug_backtrace();
            $package = $caller[1]['class'];
        }

        while ($package) {
            if ($result = self::words($package . $package_suffix, $key)) {
                return $result;
            }
            if (strpos($package, '\\') === false) {
                $package = '';
            }
            $package = preg_replace('/\\\\[^\\\\]+$/', '', $package);
        }

        return self::words($package . $package_suffix, $key, true);
    }

    /**
     * Select Words.
     *
     * @param string $package
     * @param string $key
     * @param bool $final
     *
     * @return string
     */
    private static function words($package, $key, bool $final = false)
    {
        if (!class_exists($package, true)) {
            return false;
        }

        if (defined("$package::$key")) {
            return constant("$package::$key");
        }

        if (defined("$package::PHRASES")) {
            if (is_array($phrases = constant("$package::PHRASES"))) {
                $k = strtolower($key);
                $phrases = array_change_key_case($phrases);
                if (isset($phrases[$k])) {
                    return $phrases[$k];
                }
            }
        }

        if ($final && strpos($key, ' ') !== false) {
            return $key;
        }

        $inst = new $package();

        // Compatibility with older versions
        if (property_exists($inst, "_$key")) {
            $key = "_$key";

            return $inst->$key;
        }

        return (property_exists($inst, $key)) ? $inst->$key : '';
    }
}
