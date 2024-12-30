<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016 PlusFive (http://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * http://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Xml\Html;

/**
 * HTML form input class.
 *
 * @license  http://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class Input
{
    /**
     * Set default value.
     *
     * @param object $fmObj
     * @param object $html
     * @param object $element
     * @param string $name
     * @param mixed  $value
     * @param mixed  $sec
     */
    public static function setValue($fmObj, $html, $element, $type, $name, $value, $sec)
    {
        if ($type == 'radio') {
            Radio::setValue($fmObj, $html, $element, $value);
        } elseif ($type == 'checkbox') {
            Checkbox::setValue($fmObj, $html, $element, $name, $value, $sec);
        } elseif ($type == 'File') {
            File::setValue($fmObj, $html, $element, $name, $value, $sec);
        } elseif ($type === 'date') {
            $value = (empty($value)) ? null : date('Y-m-d', strtotime($value));
            Text::setValue($fmObj, $html, $element, $name, $value, $sec);
        } elseif ($type === 'datetime') {
            $value = (empty($value)) ? null : date('Y-m-d\TH:i:s', strtotime($value));
            Text::setValue($fmObj, $html, $element, $name, $value, $sec);
        } else {
            Text::setValue($fmObj, $html, $element, $name, $value, $sec);
        }
    }

    /**
     * Change source Input to Preview.
     *
     * @param object $fmObj
     * @param object $html
     * @param string $name
     * @param mixed  $value
     */
    public static function preview($fmObj, $html, $form, $element, $type, $name, $value, $sec)
    {
        if ($type == 'radio') {
            Radio::preview($fmObj, $html, $form, $element, $value);
        } elseif ($type == 'checkbox') {
            Checkbox::preview($fmObj, $html, $form, $element, $name, $value);
        } elseif ($type == 'file') {
            File::preview($fmObj, $html, $form, $element, $type, $name, $value, $sec);
        } else {
            Text::preview($fmObj, $html, $form, $element, $type, $name, $value, $sec);
        }
    }
}
