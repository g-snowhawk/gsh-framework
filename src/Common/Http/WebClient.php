<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2022 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Http;

use CURLFile;
use Gsnowhawk\Common\File;

/**
 * Web client
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class WebClient
{
    private $handler;
    private $user_agent;
    private $enc;
    private $timeout;
    private $cookie_save_path;
    private $request_header;
    private $response_header;
    private $response_body;
    private $info;
    private $cookies;
    private $params;

    public function __construct($user_agent = '', $enc = '', $timeout = 180)
    {
        $this->user_agent = (empty($user_agent)) ? 'curl/' . (curl_version())['version'] : $user_agent;
        $this->enc = $enc;
        $this->timeout = $timeout;
        $this->cookie_save_path = tempnam(sys_get_temp_dir(), 'cookie_');
        $this->handler = curl_init();
        $this->reset();
        $this->params = [];
        $this->request_header = [];
    }

    public function __destruct()
    {
        curl_close($this->handler);
        if (file_exists($this->cookie_save_path)) {
            unlink($this->cookie_save_path);
        }
    }

    public function reset(): void
    {
        curl_reset($this->handler);
        curl_setopt($this->handler, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->handler, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($this->handler, CURLOPT_ENCODING, $this->enc);
        curl_setopt($this->handler, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->handler, CURLOPT_HEADER, true);
        curl_setopt($this->handler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handler, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->handler, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($this->handler, CURLOPT_COOKIEJAR, $this->cookie_save_path);
        curl_setopt($this->handler, CURLOPT_COOKIEFILE, $this->cookie_save_path);

        $this->request_header = [];
    }

    public function setHeader($header): void
    {
        if (is_array($header)) {
            $this->request_header = array_merge($this->request_header, $header);
        } else {
            $this->request_header[] = (string) $header;
        }
    }

    public function setParams($params): void
    {
        $this->params = [];
        foreach ($params as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $i => $var) {
                    $this->params["{$name}[{$i}]"] = $var;
                }
            } else {
                $this->params[$name] = $value;
            }
        }
    }

    public function head($url, $params = [])
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return self::exec($url, 'HEAD');
    }

    public function get($url, $params = [])
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return self::exec($url, 'GET');
    }

    public function post($url, $params = [], $files = [])
    {
        $this->setParams($params);

        foreach ($files as $name => $file) {
            if (is_array($file)) {
                foreach ($file as $i => $value) {
                    if (empty($path = self::createFile($value))) {
                        continue;
                    }
                    $this->params["{$name}[{$i}]"] = $path;
                }
            } else {
                if (empty($path = self::createFile($file))) {
                    continue;
                }
                $this->params[$name] = $path;
            }
        }

        return self::exec($url, 'POST');
    }

    public function exec($url, $method)
    {
        $this->reset();
        curl_setopt($this->handler, CURLOPT_URL, $url);
        if ($method === 'HEAD') {
            curl_setopt($this->handler, CURLOPT_NOBODY, true);
        } elseif ($method === 'POST') {
            curl_setopt($this->handler, CURLOPT_POST, true);
            curl_setopt($this->handler, CURLOPT_POSTFIELDS, $this->params);

            $this->request_header[] = 'Content-Type: multipart/form-data';
        }

        // Request header
        if (!empty($this->request_header)) {
            $headers = array_unique($this->request_header);
            curl_setopt($this->handler, CURLOPT_HTTPHEADER, $headers);
        }

        if (false === ($response = curl_exec($this->handler))
            || false === ($this->info = curl_getinfo($this->handler))
        ) {
            return false;
        }

        list($header, $body) = explode("\r\n\r\n", $response, 2);
        $this->response_body = $body;

        $headers = explode("\r\n", $header);
        $this->response_header = [];
        $this->cookies = [];
        foreach ($headers as $line) {
            if (preg_match('/^([^:\s]+):\s*(.+)$/', $line, $match)) {
                $key = $match[1];
                $value = $match[2];
                if (strtolower($key) === 'set-cookie') {
                    $data = explode('=', (explode(';', $value))[0], 2);
                    $this->cookies[$data[0]] = urldecode($data[1]);
                } else {
                    $this->response_header[$key] = $value;
                }
            }
        }

        return true;
    }

    public function status(): ?int
    {
        return $this->info['http_code'] ?? null;
    }

    public function cookie($key = null)
    {
        if (is_null($key)) {
            return $this->cookies;
        }

        return $this->cookies[$key] ?? null;
    }

    public function header(): ?array
    {
        return $this->response_header;
    }

    public function body(): ?string
    {
        return $this->response_body;
    }

    private static function createFile($path): ?object
    {
        if (false === ($path = @realpath($path)) || is_dir($path)) {
            trigger_error("`{$path}' is not file", E_USER_WARNING);

            return null;
        }

        return new CURLFile($path, File::mime($path));
    }
}
