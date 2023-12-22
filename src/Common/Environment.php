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
 * Environment Class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com/>
 */
class Environment
{
    /**
     * Object constructor.
     */
    public function __construct()
    {
    }

    public static function cookie($key)
    {
        return filter_input(INPUT_COOKIE, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    public static function get($key, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    {
        return filter_input(INPUT_GET, $key, $filter);
    }

    public static function post($key, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS, $options = 0)
    {
        return filter_input(INPUT_POST, $key, $filter, $options);
    }

    public static function env($key, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    {
        $value = filter_input(INPUT_ENV, $key, $filter);
        if (is_null($value) && isset($_ENV[$key])) {
            $value = filter_var($_ENV[$key], $filter);
        }

        return $value;
    }

    public static function server($key, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    {
        $key = strtoupper($key);
        $value = filter_input(INPUT_SERVER, $key, $filter);
        if (is_null($value) && isset($_SERVER[$key])) {
            $value = filter_var($_SERVER[$key], $filter);
        }

        return $value;
    }

    public static function session($key, $filter = FILTER_UNSAFE_RAW)
    {
        if (!isset($_SESSION[$key])) {
            return;
        }

        return filter_var($_SESSION[$key], $filter);
    }

    public static function osFromUserAgent($user_agent)
    {
        if (preg_match("/(iPod|iPad|iPhone); .+ OS ([0-9_]+) like Mac OS X[;\)] .+$/", $user_agent, $match)) {
            $name = 'iOS';
            $version = strtr($match[2], '_', '.');
        } elseif (preg_match("/Android ([0-9\.]+);/", $user_agent, $match)) {
            $name = 'Android';
            $version = $match[1];
        } elseif (preg_match("/Windows Phone(OS )? ([0-9\.]+);/", $user_agent, $match)) {
            $name = 'Windows Phone';
            $version = $match[2];
        } elseif (preg_match("/Windows NT ([0-9\.]+)[;\)]/", $user_agent, $match)) {
            $name = 'Windows';
            if ($match[1] < 5.1) {
                $version = 'Legacy';
            } elseif ($match[1] < 6) {
                $version = 'XP';
            } elseif ($match[1] < 6.1) {
                $version = 'Vista';
            } elseif ($match[1] < 6.2) {
                $version = '7';
            } elseif ($match[1] < 6.3) {
                $version = '8';
            } else {
                $version = '10';
            }
        } elseif (preg_match("/Mac OS X ([0-9\._]+)[;\)]/", $user_agent, $match)) {
            $name = 'macOS';
            $version = strtr($match[1], '_', '.');
        } elseif (preg_match("/Linux .+; rv:([0-9\.]+)[;\)]/", $user_agent, $match)) {
            $name = 'Linux';
            $version = $match[1];
        } else {
            $name = 'Unknown';
            $version = '-';
        }

        return [$name, $version];
    }

    public static function browserFromUserAgent($user_agent)
    {
        if (preg_match("/Edge\/([0-9\.]+)/", $user_agent, $match)) {
            $name = 'Microsoft Edge';
            $version = $match[1];
        } elseif (preg_match("/Chrome\/([0-9\.]+)/", $user_agent, $match)) {
            $name = 'Chrome';
            $version = $match[1];
        } elseif (preg_match("/Safari\/([0-9\.]+)/", $user_agent, $match)) {
            $name = 'Safari';
            $version = $match[1];
            if (preg_match("/Version\/([0-9\.]+)/", $user_agent, $match)) {
                $version = $match[1];
            }
        } elseif (preg_match("/Firefox\/([0-9\.]+)/", $user_agent, $match)) {
            $name = 'Firefox';
            $version = $match[1];
        } elseif (preg_match("/Opera[ \/]([0-9\.]+)/", $user_agent, $match)) {
            $name = 'Opera';
            $version = $match[1];
        } elseif (preg_match("/Trident\/([0-9\.]+)/", $user_agent, $match)) {
            $name = 'Internet Explorer';
            if ($match[1] < 11) {
                $version = 'unsupported';
            } else {
                $version = '11';
            }
        } elseif (preg_match("/MSIE ([567][0-9\.]+);/", $user_agent, $match)) {
            $name = 'Internet Explorer';
            $version = 'unsupported';
        } else {
            $name = 'Unknown';
            $version = '-';
        }

        return [$name, $version];
    }

    public static function getMemoryLimit(): ?int
    {
        $limit_string = ini_get('memory_limit');
        $unit = strtolower(mb_substr($limit_string, -1));
        $bytes = intval(mb_substr($limit_string, 0, -1), 10);

        switch ($unit) {
            case 'k':
                $bytes *= 1024;
                break;

            case 'm':
                $bytes *= (1024 ** 2);
                break;

            case 'g':
                $bytes *= (1024 ** 3);
                break;

            default:
                break;
        }

        if ("$limit_string" === '-1') {
            $bytes = null;
            if (is_readable('/proc/meminfo')) {
                $fh = fopen('/proc/meminfo', 'r');
                while ($line = fgets($fh)) {
                    if (preg_match('/^MemAvailable:\s+(\d+)\skB$/', $line, $match)) {
                        $bytes = (int)$match[1];
                        break;
                    }
                }
                fclose($fh);
            }
        }

        return $bytes;
    }
}
