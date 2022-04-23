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
 * Methods for Authentication.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Security
{
    public const DEFAULT_ENCRYPT_METHOD = 'aes-256-ecb';

    public static $salt_hash_algo = 'sha256';
    public static $salt_length = 16;
    public static $repeat_count = 3;

    /**
     * Password database.
     *
     * @var string
     */
    private $source;

    /**
     * Database object.
     *
     * @var Gsnowhawk\Common\Db
     */
    private $db;

    /**
     * password encrypt algorithm.
     *
     * @var string
     */
    private $password_algo;

    /**
     * Object constructer.
     *
     * @param string $source
     * @param Gsnowhawk\Common\Db  $db
     */
    public function __construct($source, Db $db = null, $password_algo = 'sha1')
    {
        $this->source = $source;
        $this->db = $db;
        $this->password_algo = $password_algo;
    }

    /**
     * Password encrypt algorithm.
     *
     * @return string
     */
    public function getAlgorithm()
    {
        return $this->password_algo;
    }

    /**
     * Check User/Password.
     *
     * @param string $uname
     * @param string $upass
     * @param string $secret
     * @param string $expire
     *
     * @return bool
     */
    public function authentication($uname, $upass, $secret = '', $expire = null)
    {
        if (empty($uname) || empty($upass)) {
            return false;
        }
        $method = (file_exists($this->source)) ? 'byFile' : 'byDb';
        try {
            $this->$method($uname, $upass, $secret, $expire);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Authentication by password file.
     *
     * @param string $uname
     * @param string $upass
     * @param string $secret
     * @param string $expire
     *
     * @return bool
     */
    private function byFile($uname, $upass, $secret, $expire = null)
    {
        $inc = file_get_contents($this->source);
        $pattern = "/(^|[\s]+)".$uname.":([^:\s]+)/s";
        if (preg_match($pattern, $inc, $match)) {
            $check = trim($match[2]);
            if (false === $this->compare($check, $upass, $secret)) {
                throw new \Exception('username and password do not match.');
            }
        } else {
            throw new \Exception("username `$uname' is not found.");
        }
    }

    /**
     * Authentication by password database.
     *
     * @param string $uname
     * @param string $upass
     * @param string $secret
     * @param string $expire
     */
    private function byDb($uname, $upass, $secret, $expire = null)
    {
        $user_column = 'uname';
        $passwd_column = 'upass';

        $statement = "$user_column = ?";
        if (!empty($expire)) {
            $statement .= " AND ($expire IS NULL OR $expire >= CURRENT_TIMESTAMP)";
        }

        $check = $this->db->get($passwd_column, $this->source, $statement, [$uname]);

        if ($check) {
            if (false === $this->compare($check, $upass, $secret)) {
                throw new \Exception('username and password do not match.');
            }
        } else {
            throw new \Exception("username `$uname' is not found.");
        }
    }

    /**
     * Compare user input password and reference.
     *
     * @param string $encrypted
     * @param string $plain
     * @param string $secret
     */
    private function compare($encrypted, $plain, $secret = '')
    {
        if ($this->password_algo === 'crypt') {
            return crypt($plain, $encrypted) === $encrypted;
        } elseif (in_array($this->password_algo, self::hash_algos())) {
            return password_verify($plain.$secret, $encrypted);
        } elseif (in_array($this->password_algo, hash_algos())) {
            return self::encrypt($plain, $secret, $this->password_algo) === $encrypted;
        }

        return self::decrypt($encrypted, $secret, $this->password_algo) === $plain;
    }

    /**
     * Create password.
     *
     * @param int $figure
     * @param int $nums
     * @param int $chrs
     *
     * @return string
     */
    public static function createPassword(
        $figure = 8,
        $nums = 0,
        $chrs = 0,
        $chars_seed = '!#%&()+,-/:;<=>?@[]^_{|}~'
    ) {
        $alpha = array_merge(range('a', 'z'), range('A', 'Z'));
        $numeric = range(0, 9);
        $chars = str_split($chars_seed);
        $str = '';
        $count = $figure - $nums - $chrs;
        for ($i = 0; $i < $count; ++$i) {
            $str .= $alpha[array_rand($alpha)];
        }
        for ($i = 0; $i < $nums; ++$i) {
            $str .= $numeric[array_rand($numeric)];
        }
        for ($i = 0; $i < $chrs; ++$i) {
            $str .= $chars[array_rand($numeric)];
        }

        $count = rand(0, $figure);
        for ($i = 0; $i < $count; ++$i) {
            $str = str_shuffle($str);
        }

        return $str;
    }

    /**
     * Encryption plain data.
     *
     * @param string $plain
     * @param string $secret
     * @param string $method
     *
     * @return mixed
     */
    public static function encrypt($plain, $secret, $method = self::DEFAULT_ENCRYPT_METHOD, $fixed = false)
    {
        if (empty($plain)) {
            return $plain;
        }

        if (in_array($method, self::hash_algos())) {
            return password_hash($plain.$secret, $method);
        } elseif (in_array($method, hash_algos())) {
            return hash($method, $plain.$secret);
        }

        $iv_length = openssl_cipher_iv_length($method);

        $salt_length = self::$salt_length;
        $repeat = $salt_length * self::$repeat_count;
        $key_length = $repeat - $salt_length;

        $salt = (false === $fixed) ? openssl_random_pseudo_bytes($salt_length)
                                   : $secret;
        $salty = '';
        $ext = '';
        while (strlen($salty) < $repeat) {
            $ext = hash(self::$salt_hash_algo, $ext.$secret.$salt, true);
            $salty .= $ext;
        }

        $key = substr($salty, 0, $key_length);
        $iv = substr($salty, $key_length, $iv_length);

        $encrypted = base64_encode($salt.openssl_encrypt($plain, $method, $key, OPENSSL_RAW_DATA, $iv, $tag));
        if (!empty($tag)) {
            $encrypted .= '!' . bin2hex($tag);
        }

        return $encrypted;
    }

    /**
     * Decryption encrypted data.
     *
     * @param string $encrypted
     * @param string $secret
     * @param string $method
     *
     * @return mixed
     */
    public static function decrypt($encrypted, $secret, $method = self::DEFAULT_ENCRYPT_METHOD, $fixed = false)
    {
        $salt_length = (false === $fixed) ? self::$salt_length
                                          : strlen($secret);
        $key_length = $salt_length * self::$repeat_count - $salt_length;

        $tag = '';
        if (false !== strpos($encrypted, '!')) {
            list($encrypted, $tag) = explode('!', $encrypted);
            $tag = hex2bin($tag);
        }

        $encrypted = base64_decode($encrypted);
        $salt = substr($encrypted, 0, $salt_length);
        $encrypted = substr($encrypted, $salt_length);

        $data = $secret.$salt;
        $result = hash(self::$salt_hash_algo, $data, true);
        $hash = [$result];
        for ($i = 1; $i < self::$repeat_count; ++$i) {
            $hash[] = hash(self::$salt_hash_algo, $hash[$i - 1].$data, true);
            $result .= $hash[$i];
        }

        $iv_length = openssl_cipher_iv_length($method);
        $key = substr($result, 0, $key_length);
        $iv = substr($result, $key_length, $iv_length);

        return openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv, $tag);
    }

    public static function hash_algos(Bool $advanced = false): array
    {
        $algos = [];
        if (defined('PASSWORD_BCRYPT')) {
            $algos[] = PASSWORD_BCRYPT;
        }
        if (defined('PASSWORD_ARGON2I')) {
            $algos[] = PASSWORD_ARGON2I;
        }
        if (defined('PASSWORD_ARGON2ID')) {
            $algos[] = PASSWORD_ARGON2ID;
        }

        return $algos;
    }

    public static function openssl_get_cipher_methods(): array
    {
        return preg_grep('/^aes-.+$/', openssl_get_cipher_methods());
    }
}
