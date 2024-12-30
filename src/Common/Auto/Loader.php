<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Auto;

/**
 * Auto loader class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Loader
{
    /**
     * Default file extension.
     *
     * @var string
     */
    private static $fileExtension = '.php';

    /**
     * Include path.
     *
     * @var mixed
     */
    private static $includePath = null;

    /**
     * Ignore namespace.
     *
     * @var array
     */
    private static $ignoreNameSpaceToPath = [];

    /**
     * Default namespace.
     *
     * @var mixed
     */
    private static $namespace = null;

    /**
     * Namespace separator.
     *
     * @var string
     */
    private static $namespaceSeparator = '\\';

    /**
     * Set the namespace.
     *
     * @param string $ns
     */
    public static function setNameSpace($ns)
    {
        self::$namespace = $ns;
    }

    /**
     * Set ignore namespace.
     *
     * @param array $ignore
     */
    public static function setIgnoreNameSpaceToPath(array $ignore)
    {
        self::$ignoreNameSpaceToPath = $ignore;
    }

    /**
     * Get ignore namespace.
     *
     * @param array $ignore
     */
    public static function getIgnoreNameSpaceToPath()
    {
        return self::$ignoreNameSpaceToPath;
    }

    /**
     * Register given function as __autoload() implementation.
     *
     * @return bool
     */
    public static function register($prepend = false)
    {
        return spl_autoload_register(self::class.'::autoLoad', true, $prepend);
    }

    /**
     * auto loader.
     *
     * @param string $className
     *
     * @return mixed
     */
    private static function autoLoad($className)
    {
        if (empty($className)) {
            return;
        }
        if (class_exists($className)) {
            return;
        }
        if (false === self::isIncludable($className)) {
            return;
        }
        if ($path = self::convertNameToPath($className, true)) {
            include_once $path;

            return;
        }
        throw new Exception("$path is not found in ".implode(PATH_SEPARATOR, $dirs));
    }

    /**
     * Check class file exists.
     *
     * @param string $className
     *
     * @return mixed
     */
    public static function isIncludable($className)
    {
        if (empty($className)) {
            return false;
        }
        if (class_exists($className)) {
            $tmp = new \ReflectionClass($className);
            $path = $tmp->getFileName();
        } elseif (false === $path = self::convertNameToPath($className, true)) {
            return false;
        }

        // self judgement
        if (strtolower($path) === strtolower($_SERVER['SCRIPT_FILENAME'])) {
            return false;
        }

        return $path;
    }

    /**
     * Convert ClassName to Path.
     *
     * @param string $name
     * @param bool   $fullpath
     *
     * @return string
     */
    public static function convertNameToPath($name, $fullpath = false)
    {
        if (!empty(self::$ignoreNameSpaceToPath)) {
            $name = preg_replace('/^\\\?('. preg_quote(implode('|', self::$ignoreNameSpaceToPath), '/') .')/', '', $name);
        }

        $path = '';
        $namespace = '';
        if (false !== ($lastNsPos = strripos($name, self::$namespaceSeparator))) {
            $namespace = substr($name, 0, $lastNsPos);
            $name = substr($name, $lastNsPos + 1);
            $path = str_replace(self::$namespaceSeparator, DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
        }
        $path .= str_replace('_', DIRECTORY_SEPARATOR, $name).self::$fileExtension;

        // Search include path.
        if ($fullpath !== false) {
            $dirs = explode(PATH_SEPARATOR, ini_get('include_path'));
            foreach ($dirs as $dir) {
                $file = $dir.DIRECTORY_SEPARATOR.$path;
                if (false !== $realpath = realpath($file)) {
                    return $realpath;
                }

                $dest = preg_replace("/(([^\." . preg_quote(DIRECTORY_SEPARATOR, '/') . ']+)' . preg_quote(self::$fileExtension, '/') . ')$/', '$2/$1', $file);
                if (false !== $realpath = realpath($dest)) {
                    return $realpath;
                }

                $file = preg_replace('/\.php$/', '.class.php', $file);
                if (false !== $realpath = realpath($file)) {
                    return $realpath;
                }
            }

            return false;
        }

        return $path;
    }

    public static function parentHasMethod($class, string $method)
    {
        foreach (class_parents($class) as $parent) {
            if (is_callable([$parent, $method]) || method_exists($parent, $method)) {
                return $parent;
            }
        }

        return null;
    }
}
