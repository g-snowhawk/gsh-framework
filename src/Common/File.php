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

use ErrorException;

/**
 * Methods for file management.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class File
{
    /**
     * Writting File.
     *
     * @param string $file
     * @param string $source
     * @param string $mode   File open mode
     *
     * @return bool
     */
    public static function write($file, $source, $mode = 'w+b')
    {
        if (!is_writable(dirname($file))) {
            return false;
        }
        if (false !== $fh = fopen($file, $mode)) {
            if (false !== rewind($fh)) {
                if (false !== fwrite($fh, $source)) {
                    fflush($fh);
                    ftruncate($fh, ftell($fh));

                    return fclose($fh);
                }
            }
        }

        return false;
    }

    /**
     * Reading File.
     *
     * @param string $file
     *
     * @return bool
     */
    public static function read($file)
    {
        if (!file_exists($file)) {
            return;
        }
        $contents = '';
        if ($fh = fopen($file, 'rb')) {
            while (!feof($fh)) {
                $contents .= fread($fh, 8192);
            }
        }

        return $contents;
    }

    /**
     * Removing file as matching filter.
     *
     * @param string $dir
     * @param string $filter
     *
     * @return bool
     */
    public static function rm($dir, $filter)
    {
        if (!is_dir($dir)) {
            return true;
        }
        if ($dh = opendir($dir)) {
            while (false !== ($file = readdir($dh))) {
                if ($file != '.' && $file != '..') {
                    if (preg_match($filter, $file)) {
                        unlink("$dir/$file");
                    }
                }
            }
        }

        return closedir($dh);
    }

    public static function copy($src, $dest, $recurse = false, $link = null, $dirmode = 0777, $filemode = 0666)
    {
        $src = rtrim($src, '/');

        if (is_dir($src)) {
            if (is_dir($dest)) {
                $path = rtrim($dest, '/') . '/' . basename($src);
            } else {
                $path = $dest;
            }

            if ($link === 'symbolic') {
                return symlink($src, rtrim($dest, '/'));
            }

            $result = mkdir($path, $dirmode, true);

            if (false !== $recurse) {
                $files = glob("$src/*");
                foreach ($files as $file) {
                    if (false === $result = self::copy($file, "$path/" . basename($file), $recurse, $link, $dirmode, $filemode)) {
                        return false;
                    }
                }
            }

            return $result;
        }

        switch ($link) {
            case 'hard':
                try {
                    return link($src, $dest);
                } catch (ErrorException $e) {
                    return copy($src, $dest);
                }
                break;
            case 'symbolic':
                return symlink($src, $dest);
                break;
            default:
                return copy($src, $dest);
                break;
        }
    }

    /**
     * Copying directories.
     *
     * @deprecated
     *
     * @param string $dir
     * @param string $dest
     * @param bool   $recursive
     * @param number $dir_mode
     * @param number $file_mode
     *
     * @return bool
     */
    public static function copydir($dir, $dest, $recursive = false, $dir_mode = 0755, $file_mode = 0644)
    {
        if (!is_dir($dest)) {
            if (false === self::mkdir($dest, $dir_mode)) {
                return false;
            }
        }
        if ($dh = opendir($dir)) {
            while ($file = readdir($dh)) {
                if ($file != '.' && $file != '..') {
                    $path = $dir.'/'.$file;
                    $new = $dest.'/'.$file;
                    if (is_dir($path)) {
                        self::copydir($path, $new, $recursive, $dir_mode, $file_mode);
                    } else {
                        if (false === copy($path, $new)) {
                            return false;
                        }
                        @chmod($new, $file_mode);
                    }
                }
            }
            closedir($dh);

            return true;
        }

        return false;
    }

    /**
     * Removing directories.
     *
     * @deprecated Alias to self::rmdir.
     *
     * @param string $dir
     * @param bool   $recursive
     *
     * @return bool
     */
    public static function rmdirs($dir, $recursive = false)
    {
        return self::rmdir($dir, $recursive);
    }

    /**
     * Removing directories.
     *
     * @param string $dir
     * @param bool   $recursive
     *
     * @return bool
     */
    public static function rmdir($dir, $recursive = false)
    {
        if ($recursive === true) {
            $dh = opendir($dir);
            while ($file = readdir($dh)) {
                if ($file !== '.' && $file !== '..') {
                    $path = $dir . '/' . $file;
                    if (is_dir($path)) {
                        self::rmdir($path, $recursive);
                    } else {
                        unlink($path);
                    }
                }
            }
            closedir($dh);
        }

        return rmdir($dir);
    }

    /**
     * Correct file path.
     *
     * @param string $path
     * @param mixed  $separator
     *
     * @return string
     */
    public static function realpath($path, $separator = null)
    {
        if (is_null($path)) {
            return;
        }
        $isunc = (preg_match('/^\\\\/', $path)) ? true : false;
        $path = preg_replace('/[\/\\\]/', '/', $path);
        $path = preg_replace('/\/+/', '/', $path);
        $path = preg_replace('/\/\.\//', '/', $path);
        $pattern = '/\/(\.*)?[^\/\.]+((\.*)?([^\/\.]+)?)*?\/\.\.\//';
        while (preg_match($pattern, $path)) {
            $path = preg_replace($pattern, '/', $path);
        }
        if (DIRECTORY_SEPARATOR == '/') {  // UNIX
            $path = preg_replace('/^[a-z]{1}:/i', '', $path);
        } else {  // Windows
            if ($isunc === true) {
                $path = DIRECTORY_SEPARATOR.$path;
            }
        }
        if (empty($separator)) {
            $separator = DIRECTORY_SEPARATOR;
        }

        return self::replaceDirectorySeparator($path, $separator);
    }

    /**
     * Replacing directory separator.
     *
     * @param string $path
     *
     * @return string
     */
    public static function replaceDirectorySeparator($path, $separator = null)
    {
        $pattern = '/('.preg_quote('\\', '/').'|'.preg_quote('/', '/').')/';
        if (empty($separator)) {
            $separator = DIRECTORY_SEPARATOR;
        }

        return preg_replace($pattern, $separator, $path);
    }

    /**
     * Getting file size.
     *
     * @param float $byte
     * @param int   $dp   number of desimal place
     * @param bool  $si
     *
     * @return string
     */
    public static function size($byte = 0, $dp = 2, $si = true)
    {
        if ($si === true) {
            if ($byte < pow(10, 3)) {
                return $byte.' Byte';
            }
            if ($byte < pow(10, 6)) {
                return round($byte / pow(10, 3), $dp).' KB';
            }
            if ($byte < pow(10, 9)) {
                return round($byte / pow(10, 6), $dp).' MB';
            }
            if ($byte < pow(10, 12)) {
                return round($byte / pow(10, 9), $dp).' GB';
            }
            if ($byte < pow(10, 15)) {
                return round($byte / pow(10, 12), $dp).' TB';
            }
            if ($byte < pow(10, 18)) {
                return round($byte / pow(10, 15), $dp).' PB';
            }
            if ($byte < pow(10, 21)) {
                return round($byte / pow(10, 18), $dp).' EB';
            }
            if ($byte < pow(10, 24)) {
                return round($byte / pow(10, 21), $dp).' ZB';
            }

            return round($byte / pow(10, 24), $dp).' YB';
        }
        if ($byte < pow(2, 10)) {
            return $byte.' Byte';
        }
        if ($byte < pow(2, 20)) {
            return round($byte / pow(2, 10), $dp).' KiB';
        }
        if ($byte < pow(2, 30)) {
            return round($byte / pow(2, 20), $dp).' MiB';
        }
        if ($byte < pow(2, 40)) {
            return round($byte / pow(2, 30), $dp).' GiB';
        }
        if ($byte < pow(2, 50)) {
            return round($byte / pow(2, 40), $dp).' TiB';
        }
        if ($byte < pow(2, 60)) {
            return round($byte / pow(2, 50), $dp).' PiB';
        }
        if ($byte < pow(2, 70)) {
            return round($byte / pow(2, 60), $dp).' EiB';
        }
        if ($byte < pow(2, 80)) {
            return round($byte / pow(2, 70), $dp).' ZiB';
        }

        return round($byte / pow(2, 80), $dp).' YiB';
    }

    public static function strToBytes($str)
    {
        if (stripos($str, 'K')) {
            $result = (int)$str * 1024;
        } elseif (stripos($str, 'M')) {
            $result = (int)$str * 1048576;
        } elseif (stripos($str, 'G')) {
            $result = (int)$str * 1073741824;
        } else {
            $result = (int)$str;
        }

        return $result;
    }

    /**
     * Getting MIME Type.
     *
     * @param string $path
     *
     * @return string
     */
    public static function mime($path)
    {
        $tmp = explode('.', $path);
        $ext = array_pop($tmp);
        $mime = self::mimetype(strtolower($ext));

        return (empty($mime)) ? 'application/octet-stream' : $mime;
    }

    /**
     * Check existing file.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function fileExists($path)
    {
        if (!preg_match("/^([a-z]:|\/)/i", $path)) {
            return file_exists($path);
        }
        $inc_dirs = explode(PATH_SEPARATOR, ini_get('open_basedir'));
        if (empty($inc_dirs)) {
            return file_exists($path);
        }
        foreach ($inc_dirs as $dir) {
            $pattern = '/^'.preg_quote($dir, '/').'/i';
            if (preg_match($pattern, $path)) {
                return file_exists($path);
            }
        }

        return false;
    }

    /**
     * Check existing file.
     *
     * @param string $path
     * @param int $use_include_path
     *
     * @return bool
     */
    public static function exists($path, $use_include_path = 0)
    {
        $exists = false;
        if (!preg_match("/^([a-z]:|\/)/i", $path)) {
            if ($use_include_path !== FILE_USE_INCLUDE_PATH) {
                return file_exists($path);
            }
            $include_path = explode(PATH_SEPARATOR, ini_get('include_path'));
            foreach ($include_path as $dir) {
                $file = "{$dir}/{$path}";
                if (file_exists($file)) {
                    $exists = true;
                    $path = $file;
                    break;
                }
            }
            if ($exists === false) {
                return false;
            }
        }

        $inc_dirs = explode(PATH_SEPARATOR, ini_get('open_basedir'));
        if (empty($inc_dirs)) {
            return file_exists($path);
        }

        foreach ($inc_dirs as $dir) {
            $pattern = '/^'.preg_quote($dir, '/').'/i';
            if (preg_match($pattern, $path)) {
                return file_exists($path);
            }
        }

        return false;
    }

    /**
     * Temporary Directory path.
     *
     * @return string
     */
    public static function tmpdir()
    {
        $dir = ini_get('upload_tmp_dir');
        if (empty($dir)) {
            $dir = sys_get_temp_dir();
        }

        return $dir;
    }

    /**
     * Create new direstory.
     *
     * @param string $path
     * @param mixed  $mode
     *
     * @return bool
     */
    public static function mkdir($path, $mode = 0777)
    {
        $path = self::replaceDirectorySeparator($path);
        if (file_exists($path)) {
            return is_dir($path);
        }
        $base = rtrim($path, DIRECTORY_SEPARATOR);
        $dirs = [];
        while (!file_exists($base)) {
            array_unshift($dirs, basename($base));
            $prev = $base;
            $base = dirname($base);
            if ($prev == $base) {
                return false;
            }
            if (file_exists($base) && !is_writable($base)) {
                return false;
            }
        }
        if ($base === DIRECTORY_SEPARATOR) {
            $base = '';
        }
        foreach ($dirs as $dir) {
            $base .= DIRECTORY_SEPARATOR.$dir;
            try {
                @mkdir($base);
                @chmod($base, $mode);
            } catch (ErrorException $e) {
                if (preg_match('/File exists/', $e->getMessage())) {
                    continue;
                }
            }
        }

        return is_dir($path);
    }

    /**
     * Check mime type by extension.
     *
     * @param string $key
     *
     * @return string
     */
    public static function mimetype($key)
    {
        $types = [
            'bmp' => 'image/bmp',
            'css' => 'text/css',
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'js' => 'text/javascript',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'swf' => 'application/x-shockwave-flash',
            'tiff' => 'image/tiff',
            'txt' => 'text/plain',
        ];

        return (isset($types[$key])) ? $types[$key] : '';
    }

    public static function find($needle, $haystack, $recursive = false)
    {
        $haystack = rtrim($haystack, '/');
        $match = [];
        $entries = scandir($haystack);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "{$haystack}/{$entry}";
            if ((is_array($needle) && in_array($entry, $needle)) || $entry === $needle) {
                $match[] = $path;
            }

            if ($recursive && is_dir($path)) {
                $recursed = self::find($needle, $path, $recursive);
                if (is_array($recursed)) {
                    $match = array_merge($match, $recursed);
                }
            }
        }

        return (empty($match)) ? null : $match;
    }

    public static function isEmpty($dir)
    {
        if (!is_dir($dir)) {
            trigger_error("{$dir} is not directory", E_USER_WARNING);

            return false;
        }

        $entries = scandir($dir);
        $skip = ['.','..'];

        return count(array_diff($entries, $skip)) === 0;
    }
}
