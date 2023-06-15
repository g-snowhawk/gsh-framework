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
    private static $dictionary;

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

        if (defined('DICTIONARY_PATH')) {
            $phrase = self::lookup(DICTIONARY_PATH, $key, $locale);
            if (!is_null($phrase)) {
                return $phrase;
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

    private static function lookup($dictionary_path, $key, $locale)
    {
        if (!is_array(self::$dictionary)) {
            self::$dictionary = [];
        }

        // Load dictionary from file
        if (empty(self::$dictionary[$locale])) {
            self::$dictionary[$locale] = [];
            $sep = DIRECTORY_SEPARATOR;
            $path = rtrim($dictionary_path, $sep) . $sep . strtolower($locale);
            $flatten = function ($array, $prefix = null, $separator = '.') use (&$flatten) {
                $data = [];
                foreach ($array as $key => $value) {
                    $newkey = implode($separator, array_filter([$prefix, $key]));
                    if (is_array($value)) {
                        $data = array_merge($data, $flatten($value, $newkey, $separator));
                    } else {
                        $data[$newkey] = $value;
                    }
                }

                return $data;
            };

            $extentions = ['json'];
            if (is_callable('yaml_parse_file')) {
                $extentions[] = 'yml';
            }

            self::$dictionary[$locale] = [];
            foreach ($extentions as $extension) {
                $file = "{$path}.{$extension}";
                if (file_exists($file)) {
                    $dict = null;
                    if ($extension === 'yml') {
                        $dict = yaml_parse_file($file);
                    } elseif ($extension === 'json') {
                        $dict = json_decode(file_get_contents($file), true);
                    }
                    if (is_array($dict)) {
                        if (defined('DICTIONARY_IS_FLATTEN') && DICTIONARY_IS_FLATTEN === 1) {
                            $dict = $flatten($dict);
                        }
                        self::$dictionary[$locale] = array_merge(self::$dictionary[$locale], $dict);
                    }
                }
            }
        }

        return self::$dictionary[$locale][$key] ?? self::$dictionary[$locale][strtolower($key)] ?? null;
    }
}
