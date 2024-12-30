<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016-2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Xml;

use ErrorException;
use Exception;

/**
 * XML DOM custom exception.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <https://www.plus-5.com/>
 */
class DomException extends ErrorException
{
    public function __construct(
        $message,
        $code = 0,
        $severity = E_ERROR,
        $filename = __FILE__,
        $lineno = __LINE__,
        Exception $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $severity,
            $filename,
            $lineno,
            $previous
        );
    }
}
