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
 * Send Mail class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Mail
{
    /**
     * SMTP hostname or IP address.
     *
     * @var string
     */
    private $smtp = '';

    /**
     * Port number of SMTP server.
     *
     * @var string
     */
    private $port = '';

    /**
     * SMTP-Auth username.
     *
     * @var string
     */
    private $user = '';

    /**
     * SMTP-Auth password.
     *
     * @var string
     */
    private $passwd = '';

    /**
     * Use TLS.
     *
     * @var bool
     */
    private $tls = false;

    /**
     * Authentication Types.
     *
     * @var array
     */
    private $auth_types = [];

    /**
     * Mail sender address.
     *
     * @var string
     */
    private $from = '';

    /**
     * Mail envelope sender address.
     *
     * @var string
     */
    private $envfrom = '';

    /**
     * Mail subject.
     *
     * @var string
     */
    private $subject = '';
    private $raw_subject = '';

    /**
     * Plain text content.
     *
     * @var string
     */
    private $message = '';

    /**
     * HTML content.
     *
     * @var string
     */
    private $html = '';

    /**
     * Mailto Addresses.
     *
     * @var array
     */
    private $to = [];

    /**
     * Carbon copy Addresses.
     *
     * @var array
     */
    private $cc_addr = [];

    /**
     * Blind carbon copy Addresses.
     *
     * @var array
     */
    private $bcc_addr = [];

    /**
     * Mail headers.
     *
     * @var array
     */
    private $head = [];

    /**
     * Mail attachments.
     *
     * @var array
     */
    private $attachment = [];

    /**
     * SMTP stream.
     *
     * @var stream
     */
    private $socket;

    /**
     * log.
     *
     * @var string
     */
    private $log = '';

    /**
     * copy
     *
     * @var string
     */
    private $eml = '';

    /**
     * Error message.
     *
     * @var string
     */
    private $error = '';

    /**
     * Delimiter.
     *
     * @var string
     */
    private $delimiter = "\r\n";

    /**
     * encode.
     *
     * @var string
     */
    private $encoding;

    /**
     * Caracterset.
     *
     * @var array
     */
    private $charset = [
        'jis' => 'ISO-2022-JP',
        'sjis' => 'Shift_JIS',
        'utf-8' => 'UTF-8',
    ];

    /**
     * Object constructor.
     *
     * @param string     $host
     * @param string|int $port
     * @param string     $user
     * @param string     $passwd
     * @param string     $encoding
     */
    public function __construct(string $host = '', string|int $port = '', string $user = '', string $passwd = '', string $encoding = 'jis')
    {
        $this->setHost($host);
        $this->setPort($port);
        $this->user = $user;
        $this->passwd = $passwd;
        $this->encoding = $encoding;

        $this->from = self::noreplyAt();

        if (defined('GSH_MAIL_EXTRA_HEADERS') && is_array(GSH_MAIL_EXTRA_HEADERS)) {
            foreach (GSH_MAIL_EXTRA_HEADERS as $key => $value) {
                if (!isset($this->head[$key])) {
                    $this->setHeader($key, $value);
                }
            }
        }
    }

    /**
     * SMTP host.
     *
     * @param string $host
     */
    public function setHost(string $host = ''): void
    {
        if (empty($host)) {
            $host = ini_get('SMTP');
            if (empty($host)) {
                $host = 'localhost';
            }
        }

        $this->smtp = $host;

        // Windows OS
        if (preg_match('/^WIN/i', PHP_OS)) {
            if ($this->smtp !== 'localhost' && $host !== ini_get('SMTP')) {
                ini_set('SMTP', $host);
            }
        }
    }

    /**
     * SMTP port.
     *
     * @param string $port
     */
    public function setPort(string|int $port): void
    {
        if (empty($port)) {
            $port = ini_get('smtp_port');
            if (empty($port)) {
                $port = 25;
            }
        }

        $int = intval($port);

        if ($int < 1 || $int > 65535) {
            throw new ErrorException("Invalit port number ($port)");
        }

        $this->port = $port;
    }

    /**
     * Set delimiter
     *
     * @param string $delimiter
     */
    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    /**
     * Using TLS
     *
     * @param bool $use
     */
    public function useTLS(bool $use = true): void
    {
        $this->tls = $use;
    }

    /**
     * SET Encoding.
     *
     * @param string $encoding
     */
    public function setEncoding(string $encoding): void
    {
        $this->encoding = $encoding;
    }

    /**
     * Set envelope From address.
     *
     * @param string $envfrom
     */
    public function envfrom(string $envfrom): void
    {
        $this->envfrom = $this->normalizeAddress($envfrom);
    }

    /**
     * Set reply to address.
     *
     * @param string $replay_to
     */
    public function replyto(string $replay_to): void
    {
        $this->head['Reply-To'] = $this->normalizeAddress($replay_to);
    }

    /**
     * Set From address.
     *
     * @param string $from
     */
    public function from(string $from): void
    {
        $this->from = $this->normalizeAddress($from);
    }

    /**
     * Set To address.
     *
     * @param ?string $to
     * @param string  $prop
     */
    public function to(?string $to = null, $prop = 'to'): void
    {
        if (is_string($to) && strpos($to, ',') !== false) {
            $to = array_map('trim', explode(',', $to));
        }
        if (is_null($this->$prop)) {
            $this->$prop = [];
        }
        if (is_null($to)) {
            $this->$prop = [];
        } elseif (is_array($to)) {
            foreach ($to as $value) {
                if (!empty($value)) {
                    $this->{$prop}[] = $this->normalizeAddress($value);
                }
            }
        } elseif (!empty($to)) {
            $this->{$prop}[] = $this->normalizeAddress($to);
        }
    }

    /**
     * Set Cc address.
     *
     * @param ?string $cc
     */
    public function cc(?string $cc = null): void
    {
        $this->to($cc, 'cc_addr');
    }

    /**
     * Set Bcc address.
     *
     * @param ?string $bcc
     */
    public function bcc(?string $bcc = null): void
    {
        $this->to($bcc, 'bcc_addr');
    }

    /**
     * Set Attachment path.
     *
     * @param mixed  $attachment
     * @param string $attachment
     */
    public function attachment($attachment = null, ?string $filename = null): void
    {
        if (is_null($attachment)) {
            $this->attachment = [];
        } else {
            if (is_null($filename)) {
                $this->attachment[] = $attachment;
            } else {
                $this->attachment[] = [
                    'mimetype' => File::mime($attachment),
                    'filename' => $filename,
                    'contents' => file_get_contents($attachment),
                ];
            }
        }
    }

    /**
     * Set mail subject.
     *
     * @param string $subject
     */
    public function subject(string $subject): void
    {
        $str = preg_replace("/(\r\n|\r|\n)/", ' ', $subject);
        $this->subject = $this->encodeHeader($str);
        $this->raw_subject = $str;
    }

    /**
     * Set message content.
     *
     * @param string $message
     */
    public function message(string $message): void
    {
        $str = preg_replace("/(\r\n|\r)/", $this->delimiter, $message);
        $this->message = $this->convertText($str);
    }

    /**
     * Set message HTML source.
     *
     * @param string $source
     */
    public function html(string $source): void
    {
        $str = preg_replace("/(\r\n|\r)/", $this->delimiter, $source);
        if (empty($this->message)) {
            $this->message = strip_tags($str);
        }
        $this->html = $this->convertText($str);
    }

    /**
     * Set mail headers.
     *
     * @param string $key
     * @param string $value
     */
    public function setHeader(string $key, string $value): void
    {
        $this->head[$key] = preg_replace("/[\s]+/", ' ', $value);
    }

    /**
     * Normalizing email address.
     *
     * @param string $addr
     *
     * @return string
     */
    public function normalizeAddress(string $addr): string
    {
        if (preg_match('/^([^<]+)<([^>]+)>/', $addr, $match)) {
            $addr = $this->encodeHeader($match[1]).'<'.$match[2].'>';
        }

        return $addr;
    }

    /**
     * Strip email address.
     *
     * @param string $addr
     *
     * @return string
     */
    public function stripAddress(string $addr): string
    {
        return (preg_match('/^[^<]*<([^>]+)>/', $addr, $match)) ? $match[1] : $addr;
    }

    /**
     * Encode header element.
     *
     * @param string $str
     *
     * @return string
     */
    public function encodeHeader(string $str): string
    {
        $encoded = base64_encode($this->convertText($str));

        return '=?'.$this->getCharset().'?B?'.$encoded.'?=';
    }

    /**
     * Convert encoding.
     *
     * @param string $str
     *
     * @return string
     */
    public function convertText(string $str): string
    {
        if ($this->encoding === 'utf-8') {
            return $str;
        }

        return Text::convert($str, $this->encoding);
    }

    /**
     * Create mail header.
     *
     * @param string $boundary
     *
     * @return string
     */
    public function createHeader(string $boundary): string
    {
        $cs = $this->getCharset();
        $dlm = $this->delimiter;
        $header = 'From: '.$this->from.$dlm;
        if (!empty($this->cc_addr)) {
            $header .= 'Cc: '.implode(',', $this->cc_addr).$dlm;
        }
        if (!empty($this->bcc_addr)) {
            $header .= 'Bcc: '.implode(',', $this->bcc_addr).$dlm;
        }

        if (defined('GSH_MAIL_EXTRA_HEADERS_DEV') && is_array(GSH_MAIL_EXTRA_HEADERS_DEV)) {
            foreach (GSH_MAIL_EXTRA_HEADERS_DEV as $key => $value) {
                $this->setHeader($key, $value);
            }
        }

        $isset_date = false;
        foreach ($this->head as $key => $value) {
            $header .= "$key: $value".$dlm;
            if (strtolower($key) === 'date') {
                $isset_date = true;
            }
        }
        if (false === $isset_date) {
            $header .= 'Date: ' . date(DATE_RFC822) . $dlm;
        }
        $header .= 'Mime-Version: 1.0'.$dlm;
        if (empty($this->attachment) && empty($this->html)) {
            $header .= "Content-Type: text/plain; charset=$cs".$dlm;
            $header .= 'Content-Transfer-Encoding: 7bit'.$dlm;
        } else {
            $multipart = (empty($this->html)) ? 'mixed' : 'alternative';
            $header .= "Content-Type: multipart/$multipart; boundary=\"$boundary\"".$dlm;
        }

        return $header;
    }

    /**
     * Create Attachment.
     *
     * @param string       $boundary
     * @param string|array $file
     *
     * @return string
     */
    public function createAttachment(string $boundary, string|array $file): string
    {
        $message = '';
        if (is_array($file)) {
            $mime = $file['mimetype'];
            $basename = $this->encodeHeader($file['filename']);
            $encoded = chunk_split(base64_encode($file['contents']));
        } elseif (is_file($file)) {
            $mime = File::mime($file);
            $basename = $this->encodeHeader($file);
            $encoded = chunk_split(base64_encode(file_get_contents($file)));
        }
        if (!empty($encoded)) {
            $dlm = $this->delimiter;
            $message = $dlm.$dlm.
                        "--$boundary".$dlm.
                        "Content-Type: $mime; name=\"$basename\"".$dlm.
                        "Content-Disposition: attachment; filename=\"$basename\"".$dlm.
                        'Content-Transfer-Encoding: base64'.$dlm.$dlm.
                        $encoded.$dlm;
        }

        return $message;
    }

    /**
     * Create message.
     *
     * @param string $boundary
     *
     * @return string
     */
    public function createMessage(string $boundary): string
    {
        $cs = $this->getCharset();
        $dlm = $this->delimiter;
        if (empty($this->attachment) && empty($this->html)) {
            $message = $this->message;
        } else {
            $message = "--$boundary".$dlm;
            if (empty($this->html)) {
                $message .= "Content-Type: text/plain; charset=$cs".$dlm;
                $message .= 'Content-Transfer-Encoding: 7bit'.$dlm;
                $message .= $dlm;
                $message .= $this->message;
                foreach ($this->attachment as $file) {
                    $message .= $this->createAttachment($boundary, $file);
                }
            } else {
                // Alternative content
                $message .= "Content-Type: text/plain; charset=$cs".$dlm;
                $message .= 'Content-Disposition: inline;'.$dlm;
                $message .= 'Content-Transfer-Encoding: quoted-printable'.$dlm;
                $message .= $dlm;
                $message .= quoted_printable_decode($this->message);
                // HTML content
                $message .= $dlm;
                $message .= "--$boundary".$dlm;
                $message .= "Content-Type: text/html; charset=$cs".$dlm;
                $message .= 'Content-Disposition: inline;'.$dlm;
                $message .= 'Content-Transfer-Encoding: quoted-printable'.$dlm;
                $message .= $dlm;
                $message .= quoted_printable_decode($this->html);
            }
            $message .= $dlm."--$boundary--";
        }

        return $message;
    }

    /**
     * Send Mail.
     *
     * @return bool
     */
    public function send(): bool
    {
        if (empty($this->to)) {
            $this->error = 'Empty Rceipt to Email address.';
            trigger_error($this->error);

            return false;
        }
        $to = implode(',', $this->to);
        $boundary = md5(uniqid(rand()));
        // header
        $header = $this->createHeader($boundary);
        // message
        $message = $this->createMessage($boundary);

        if ($this->smtp === 'localhost') {
            $envfrom = (
                false !== filter_var($this->envfrom, FILTER_VALIDATE_EMAIL)
                && !empty(ini_get('sendmail_path'))
            ) ? '-f'.$this->envfrom : '';

            $this->eml = 'Subject: '.$this->subject.$this->delimiter
                       . 'To: '.$to.$this->delimiter
                       . $header.$this->delimiter
                       . $message;

            return mail($to, $this->subject, $message, $header, $envfrom);
        } else {
            if (false !== filter_var($this->envfrom, FILTER_VALIDATE_EMAIL)) {
                $header .= "RETURN-PATH: {$this->envfrom}{$this->delimiter}";
            }

            return $this->mail($to, $this->subject, $message, $header);
        }
    }

    /**
     * Send Mail by external SMTP server.
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param string $header
     *
     * @return bool
     */
    public function mail(string $to, string $subject, string $message, string $header): bool
    {
        $server = $this->smtp;
        $from = $this->from;

        if (false === $this->open()) {
            return false;
        }

        if ($this->tls === true) {
            $result = $this->command('STARTTLS');
            if (!preg_match('/^220.*$/', $result)) {
                $this->error = $result;

                return false;
            } else {
                stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            if (false === $this->command("EHLO $server")) {
                fclose($this->socket);
                $this->smtp = "ssl://$server";
                $this->port = 465;
                if (false === $this->open()) {
                    return false;
                }
            }
        }

        if (false === $this->auth()) {
            $this->close();

            return false;
        }

        if (false === $this->command('MAIL FROM: <'.$this->stripAddress($from).'>')) {
            return false;
        }

        $rcpt = array_merge($this->to, $this->cc_addr, $this->bcc_addr);
        foreach ($rcpt as $rcpt_to) {
            if (false === $this->command('RCPT TO: <'.$this->stripAddress($rcpt_to).'>')) {
                return false;
            }
        }

        if (false === $this->command('DATA')) {
            return false;
        }

        $dlm = $this->delimiter;
        $this->eml = "Subject: {$subject}".$dlm
                   . "To: {$to}".$dlm
                   . $header.$dlm
                   . $message.$dlm;
        if (false === $result = $this->command($this->eml.$dlm.'.')) {
            return false;
        }
        if (!preg_match('/^250 /', $result)) {
            $this->error = $result;
            $this->eml = '';

            return false;
        }

        return fclose($this->socket);
    }

    /**
     * Send SMTP command.
     *
     * @param string $command
     *
     * @return string|false
     */
    public function command(string $command): string|false
    {
        fputs($this->socket, $command.$this->delimiter);
        $this->log .= $command.$this->delimiter;
        if (feof($this->socket)) {
            $this->error = 'Lost connection...';
            fclose($this->socket);

            return false;
        }
        $result = fgets($this->socket);
        while (preg_match('/^([0-9]{3})-(.+)$/', $result, $match)) {
            $match[2] = preg_replace("/[\s]+$/", '', $match[2]);
            if ($match[1] === '250') {
                if (empty($this->auth_types) && preg_match('/AUTH[ =](.+)$/i', $match[2], $hit)) {
                    $this->auth_types = explode(' ', $hit[1]);
                    if (is_array($this->auth_types)) {
                        sort($this->auth_types);
                    }
                }
                if ($match[2] === 'STARTTLS') {
                    $this->tls = true;
                }
            }
            $this->log .= $result;
            if ($match[1] >= 400) {
                $this->error = $match[2];

                return false;
            }
            $result = fgets($this->socket);
        }
        $this->log .= $result;
        if (preg_match('/^[45][0-9]{2} (.+)$/', $result, $match)) {
            $this->error = $match[1];

            return false;
        }

        return $result;
    }

    /**
     * Autholize SMTP.
     *
     * @return bool
     */
    public function auth(): bool
    {
        $user = $this->user;
        $passwd = $this->passwd;
        if (empty($this->auth_types) || empty($user)) {
            return true;
        }
        $auth = false;
        foreach ($this->auth_types as $authType) {
            $result = $this->command("AUTH $authType");
            if (preg_match('/^334(.*)$/', $result, $ts)) {
                if ($authType === 'CRAM-MD5') {
                    $cCode = preg_replace("/^[\s]+/", '', $ts[1]);
                    $timestamp = base64_decode($cCode);
                    $str = base64_encode($user.' '.hash_hmac('MD5', $timestamp, $passwd));
                } elseif ($authType === 'LOGIN') {
                    $str = base64_encode($user);
                    $result = $this->command("$str");
                    if (!preg_match('/^334/', $result)) {
                        continue;
                    }
                    $str = base64_encode($passwd);
                } elseif ($authType === 'PLAIN') {
                    $str = base64_encode($user."\0".$user."\0".$passwd);
                }
                $result = $this->command("$str");
                if (preg_match('/^235/', $result)) {
                    $auth = true;
                    break;
                }
            }
        }

        return $auth;
    }

    /**
     * Open connection.
     *
     * return bool
     */
    public function open(): bool
    {
        $server = $this->smtp;
        $port = $this->port;

        if ((int)$port === 465 && stripos($server, 'tls://') !== 0) {
            $server = "tls://{$server}";
        }

        try {
            $timeout = (defined('SMTP_TIMEOUT')) ? SMTP_TIMEOUT : 30;
            $this->socket = @fsockopen($server, $port, $errno, $errstr, $timeout);
        } catch (ErrorException $e) {
            if (preg_match('/connection timed out/is', $errstr)) {
                $this->error = 'Connection timed out';

                return false;
            }

            $this->socket = false;
        }

        if (false === $this->socket) {
            if (preg_match('/certificate verify failed/is', $errstr)) {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);
                $this->socket = @stream_socket_client(
                    "$server:$port",
                    $errno,
                    $errstr,
                    5,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
            }
            if (false === $this->socket) {
                trigger_error($errstr);
                $this->error = 'Connection failed SMTP Server (' . $server . ')';

                return false;
            }
        }
        $this->log .= 'Start connection SMTP Server ('.$server.')'.$this->delimiter;
        $this->log .= fgets($this->socket);
        $server = preg_replace("/^.+:\/\//", '', $server);

        return $this->command("EHLO $server");
    }

    /**
     * Close connection.
     *
     * return bool
     */
    public function close(): bool
    {
        $result = $this->command('QUIT');

        return fclose($this->socket);
    }

    /**
     * SMTP log.
     *
     * @return string
     */
    public function getLog(): string
    {
        return $this->log;
    }

    /**
     * Error message.
     *
     * @return string
     */
    public function error(): string
    {
        return $this->error;
    }

    /**
     * Character set for message.
     *
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset[$this->encoding];
    }

    /**
     * no-reply address.
     *
     * @return string
     */
    public static function noreplyAt(?string $user_name = 'no-reply'): string
    {
        $host = Environment::server('http_host') ?? '';
        $host = preg_replace('/:[0-9]+$/', '', $host);
        if (preg_match('/^[0-9:\.]+$/', $host)) {
            $host = gethostbyaddr($host);
        }
        if (empty($host) || preg_match('/^[0-9\.]+$/', $host)) {
            $host = 'localhost';
        }

        return "{$user_name}@{$host}";
    }

    /**
     * Parse E-mail source.
     *
     * @return ?array
     */
    public static function parseEmailSource($source): ?array
    {
        list($header, $body) = preg_split('/(\r\n\r\n|\r\r|\n\n)/s', $source, 2);

        if (!preg_match('/^([\x20-\x7E]+):\s*(.+)/s', preg_replace('/^\s+/s', '', $header))) {
            return [null, $source];
        }

        $headers = [];
        $lines = preg_split('/(\r\n|\r|\n)/', $header);
        foreach ($lines as $line) {
            if (preg_match('/^([\x20-\x7E]+):\s*(.+)/', $line, $match)) {
                $label = trim($match[1]);
                $content = $match[2];

                if (preg_match('/=\?.+?\?=/', $content)) {
                    $content = mb_decode_mimeheader($content);
                }

                if (isset($headers[$label])) {
                    if (!is_array($headers[$label])) {
                        $headers[$label] = [$headers[$label]];
                    }
                    $headers[$label][] = $content;
                } else {
                    $headers[$label] = $content;
                }
            } else {
                $eol = (strtolower($label) === 'content-type') ? '' : PHP_EOL;
                if (preg_match('/=\?.+?\?=/', $line)) {
                    if (preg_match('/^\s+=\?.+/', $line)) {
                        $line = ltrim($line);
                        $eol = '';
                    }
                    $line = mb_decode_mimeheader($line);
                }

                if (is_array($headers[$label])) {
                    // +7.3
                    $i = array_key_last($headers[$label]);
                    $headers[$label][$i] .= $eol . $line;
                } else {
                    $headers[$label] .= $eol . $line;
                }
            }
        }

        // TODO: Parse body for multipart/mixed

        return [$headers, $body];
    }

    public function latestContents()
    {
        return $this->eml;
    }
}
