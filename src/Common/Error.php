<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common;

use ErrorException;

/**
 * Custom Error Handler.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com/>
 */
class Error
{
    public const MAX_LOG_SIZE = 2097152;
    public const MAX_LOG_FILES = 10;
    public const FEEDBACK_INTERVAL = 10800;

    public const ERROR_HEADER = [
        400 => 'HTTP/1.1 400 Bad Request',
        401 => 'HTTP/1.1 401 Unauthorized',
        403 => 'HTTP/1.1 403 Forbidden',
        404 => 'HTTP/1.1 404 Not Found',
        500 => 'HTTP/1.1 500 Internal Server Error',
    ];

    /**
     * Debug mode.
     *
     * @var int
     */
    protected $debug_mode = 0;

    /**
     * Error type
     *
     * @var int
     */
    private $error_type = E_ALL;

    /**
     * logfile save path.
     *
     * @var string
     */
    private $logdir;

    /**
     * Prefix
     *
     * @var string
     */
    private $prefix = 'GSH';

    /**
     * Template file path.
     *
     * @var mixed
     */
    protected $template;

    /**
     * Temporary Template file path.
     *
     * @var mixed
     */
    protected static $temporary_template;

    /**
     * not feedback flag.
     *
     * @var bool
     */
    protected static $not_feedback = false;

    /**
     * HTTP status
     *
     * @var int
     */
    protected static $http_status = 500;

    /**
     * Object Constructor.
     *
     * @param string $template
     */
    public function __construct($template = null, $error_type = null)
    {
        $this->template = $template;
        if (defined('DEBUG_MODE')) {
            $this->debug_mode = (int)DEBUG_MODE;
        }

        if (is_int($error_type)) {
            $this->error_type = $error_type;
        }

        register_shutdown_function([$this, 'unloadHandler']);
        set_error_handler([$this, 'errorHandler'], $this->error_type);
        set_exception_handler([$this, 'exceptionHandler']);

        if (defined('ERROR_LOG_DESTINATION') && !self::isEmail(ERROR_LOG_DESTINATION)) {
            $dir = dirname(ERROR_LOG_DESTINATION);
            if (!empty($dir)) {
                try {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    if (!file_exists(ERROR_LOG_DESTINATION)) {
                        touch(ERROR_LOG_DESTINATION);
                        chmod(ERROR_LOG_DESTINATION, 0666);
                    }
                } catch (ErrorException $e) {
                    trigger_error(
                        ERROR_LOG_DESTINATION . ' is no such file',
                        E_USER_ERROR
                    );
                }
            }

            if (defined('ERROR_LOGDIR_MODE')) {
                try {
                    chmod($dir, ERROR_LOGDIR_MODE);
                } catch (ErrorException $e) {
                    // Do nothing
                }
            }

            if (defined('ERROR_LOGFILE_MODE')) {
                try {
                    chmod(ERROR_LOG_DESTINATION, ERROR_LOGFILE_MODE);
                } catch (ErrorException $e) {
                    // Do nothing
                }
            }

            $this->logdir = $dir;
        }

        if (defined('FRAMEWORK_PREFIX')) {
            $this->prefix = FRAMEWORK_PREFIX;
        }
    }

    /**
     * Custom error handler.
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     * @param array  $errcontext
     */
    public function errorHandler(
        $errno,
        $errstr,
        $errfile,
        $errline,
        $errcontext = null
    ) {
        // Do nothing with the `@' operator
        if (error_reporting() === 0) {
            return;
        }

        if ($this->error_type === 0 && $this->debug_mode === 0) {
            return false;
        }

        if ($errno === E_USER_ERROR
         || $errno === E_USER_NOTICE
         || $errno === E_NOTICE
        ) {
            $message = "$errstr in $errfile on line $errline.";
            if ($errno === E_USER_ERROR) {
                self::feedback($message, $errno);
            }
            self::log($message, $errno);
            if ($errno === E_USER_ERROR) {
                $this->displayError($message, $errno);
            }

            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Custom exception handler.
     *
     * @param object $ex
     */
    public function exceptionHandler($ex)
    {
        $errno = method_exists($ex, 'getSeverity')
            ? $ex->getSeverity() : $ex->getCode();
        $errstr = $ex->getMessage();
        $errfile = $ex->getFile();
        $errline = $ex->getLine();
        $message = "$errstr in $errfile on line $errline.";
        self::feedback($message, $errno);
        self::log($message, $errno, $errfile, $errline);
        $message .= PHP_EOL.$ex->getTraceAsString();
        $this->displayError($message, $errno);
    }

    /**
     * Unload action.
     */
    public function unloadHandler()
    {
        $err = error_get_last();
        if (!is_null($err)) {
            $message = "{$err['message']}"
               . " in {$err['file']} on line {$err['line']}. ";
            $errno = $err['type'];
            self::feedback($message, $errno);
            self::log($message, $errno);
            $this->displayError($message, $errno);
        }
    }

    /**
     * Display Error message.
     *
     * @param string $message
     * @param int    $errno
     * @param array  $tracer
     */
    public function displayError($message, $errno, $tracer = null)
    {
        if (in_array($errno, [E_NOTICE, E_USER_NOTICE])) {
            return;
        }

        if (php_sapi_name() === 'cli') {
            echo rtrim($message), PHP_EOL;
            exit($errno);
        }

        $src = (is_null($this->template))
            ? self::htmlSource()
            : file_get_contents($this->template, FILE_USE_INCLUDE_PATH);
        if (!empty(self::$temporary_template)) {
            $src = file_get_contents(
                self::$temporary_template,
                FILE_USE_INCLUDE_PATH
            );
            self::$temporary_template = null;
        }
        $message = htmlspecialchars(
            $message,
            ENT_COMPAT,
            mb_internal_encoding(),
            false
        );

        if ($this->debug_mode > 0) {
            $debugger = '';
            foreach ((array) $tracer as $trace) {
                $debugger .= PHP_EOL . $trace['file']
                    . ' on line '.$trace['line'];
            }
            $src = preg_replace(
                '/<!--ERROR_DESCRIPTION-->/',
                '<p id="'.$this->prefix.'-errormessage">'
                . nl2br(
                    htmlentities(
                        $message,
                        ENT_QUOTES,
                        'UTF-8',
                        false
                    ) . $debugger
                )
                . '</p>',
                $src
            );
        }
        if (defined('LINK_TO_HOMEPAGE')) {
            $src = preg_replace(
                '/<!--LINK_TO_HOMEPAGE-->/',
                '<a href="'
                . LINK_TO_HOMEPAGE
                . '" class="'.$this->prefix.'-errorhomelink">Back</a>',
                $src
            );
        }

        $status = self::ERROR_HEADER[self::$http_status];
        if (!empty($status)) {
            header($status);
        }
        echo $src;
        exit($errno);
    }

    /**
     * Feedback to administrators.
     *
     * @param string $message
     * @param int    $errno
     *
     * @see Gsnowhawk\Common\Text::explode()
     * @see Gsnowhawk\Common\Environment::server()
     */
    public static function feedback($message, $errno)
    {
        if (!defined('FEEDBACK_ADDR') || false !== self::$not_feedback) {
            return;
        }

        if (!file_exists(ERROR_LOG_DESTINATION)) {
            $dir = dirname(ERROR_LOG_DESTINATION);
            if (!file_exists($dir)) {
                if (false === @mkdir($dir, 0777, true)) {
                    echo "{$dir} is not found";
                    exit;
                }
            } elseif (!is_writable($dir)) {
                echo "{$dir} is not writable";
                exit;
            }
        }

        if ($fh = fopen(ERROR_LOG_DESTINATION, 'r')) {
            $final = '';
            for ($i = -2; ; $i--) {
                if (fseek($fh, $i, SEEK_END) === -1) {
                    break;
                }
                $line = rtrim(fgets($fh, 8192));
                if (empty($line)) {
                    break;
                }
                $final = $line;
            }
            if (preg_match("/^\[(.+?)\].*?\[.+?\]\s*(.+$)/", $final, $match)) {
                if ($match[2] === preg_replace("/^\[(.+?)\].*?\[.+?\]\s*/", '', $message)
                 && time() - strtotime($match[1]) < self::FEEDBACK_INTERVAL
                ) {
                    return;
                }
            }
            fclose($fh);
        }

        $configuration = Text::explode(',', FEEDBACK_ADDR);
        $feedbacks = [];
        foreach ($configuration as $feedback_addr) {
            $feedbacks[] = filter_var(
                $feedback_addr,
                FILTER_VALIDATE_EMAIL,
                [
                    'options' => [
                        'default' => null,
                    ],
                ]
            );
        }
        $feedbacks = array_values(array_filter($feedbacks, 'strlen'));
        if (count($feedbacks) > 0) {
            $message .= PHP_EOL;
            $message .= PHP_EOL.'User: '.(Environment::server('http_x_forwarded_for') ?? Environment::server('remote_addr'));
            $message .= PHP_EOL.'Host: '.Environment::server('server_name');
            $message .= PHP_EOL.'Time: '.date('Y-m-d H:i:s');
            $user_agent = Environment::server('http_user_agent');
            if (!empty($user_agent)) {
                $message .= PHP_EOL;
                $message .= PHP_EOL.'User-Agent: '.$user_agent;
            }
            foreach ($feedbacks as $to) {
                error_log($message, 1, $to);
            }
        }
    }

    /**
     * Recoding Error log.
     *
     * @param string $message
     * @param int    $errno
     */
    public function log($message, $errno)
    {
        if ($this->debug_mode === 0) {
            if (in_array($errno, [0])) {
                return;
            }
        }
        if (defined('ERROR_LOG_DESTINATION')) {
            if (self::isEmail(ERROR_LOG_DESTINATION)) {
                error_log($message, 1, ERROR_LOG_DESTINATION);
            } elseif (!is_null($this->logdir)) {
                $key = (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) ? 'HTTP_X_FORWARDED_FOR' : 'REMOTE_ADDR';
                $client = '[' . filter_input(INPUT_SERVER, $key) . '] ';
                error_log(date('[Y-m-d H:i:s] ') . $client . $message . PHP_EOL, 3, ERROR_LOG_DESTINATION);
            } else {
                error_log($message, 0, ERROR_LOG_DESTINATION);
            }
        }

        self::rotate();
    }

    /**
     * check E-mail format.
     *
     * @param string $str
     *
     * @return bool
     */
    private static function isEmail($str)
    {
        return (bool)filter_var($str, FILTER_VALIDATE_EMAIL);
    }

    public static function rotate($force = false)
    {
        if (!file_exists(ERROR_LOG_DESTINATION)) {
            return true;
        }

        $size = filesize(ERROR_LOG_DESTINATION);
        if ($size === 0) {
            return true;
        }

        $max_log_size = (defined('MAX_LOG_SIZE'))
            ? MAX_LOG_SIZE : self::MAX_LOG_SIZE;
        if (false === $force && $size < $max_log_size) {
            return true;
        }

        $ext = date('.YmdHis');
        if (!rename(ERROR_LOG_DESTINATION, ERROR_LOG_DESTINATION . $ext)) {
            return false;
        }

        $max_log_files = (defined('MAX_LOG_FILES'))
            ? MAX_LOG_FILES : self::MAX_LOG_FILES;
        $files = glob(ERROR_LOG_DESTINATION . '.*');
        if (count($files) <= $max_log_files) {
            return true;
        }

        return unlink($files[0]);
    }

    /**
     * backtrace.
     *
     * @return string
     */
    public static function backtrace()
    {
        $str = '';
        foreach (debug_backtrace() as $trace) {
            if (isset($trace['file'])) {
                $str .= $trace['file'] . ' at ' . $trace['line'] . PHP_EOL;
            }
        }

        return $str;
    }

    /**
     * Default Error HTML.
     *
     * @return string
     */
    protected function htmlSource()
    {
        return <<<_HERE_
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>[{$this->prefix}] System Error</title>
    </head>
    <body>
        <h1>System Error</h1>
        <!--ERROR_DESCRIPTION-->
    </body>
</html>
_HERE_;
    }
}
