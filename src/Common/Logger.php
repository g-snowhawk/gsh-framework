<?php
/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2021 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common;

use PDOException;
use ErrorException;

class Logger
{
    private $db;
    private $path;
    private $format;

    public function __construct($source, $format)
    {
        $this->format = $format;
        if (is_object($source) && is_a($source, 'Db')) {
            $this->db = clone $source;
            $this->format = $this->db->prepare($format);
        } elseif (file_exists($source)) {
            $this->path = $source;
        } else {
            trigger_error('No source for logging');
        }
    }

    public function log(array $log)
    {
        $log = array_map(function ($value) {
            return (is_array($value)) ? implode(',', $value) : $value;
        }, $log);
        if (!empty($this->db)) {
            $this->toDatabase($log);
        } elseif (!empty($this->path)) {
            $this->toFile($log);
        } else {
            trigger_error(implode("\t", $log));
        }
    }

    private function toDatabase(array $log)
    {
        try {
            $this->format->execute(array_values($log));
        } catch (PDOException $e) {
            trigger_error($e->getMessage());
        } catch (ErrorException $e) {
            trigger_error($e->getMessage());
        }
    }

    private function toFile(array $log)
    {
        @file_put_contents(
            $this->path,
            sprintf($this->format, $log),
            FILE_APPEND|LOCK_EX
        );
    }
}
