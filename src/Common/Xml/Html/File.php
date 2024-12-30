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
 * HTML form file class.
 *
 * @license  http://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class File
{
    /**
     * Current version.
     */
    public const VERSION = '1.1.0';

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
    public static function setValue($fmObj, $html, $element, $name, $value, $sec)
    {
    }

    /**
     * Change source Input to Preview.
     *
     * @param object $fmObj
     * @param object $html
     * @param object $form
     * @param object $element
     * @param string $type
     * @param string $name
     * @param mixed  $value
     * @param mixed  $sec
     */
    public static function preview($fmObj, $html, $form, $element, $type, $name, $value, $sec)
    {
        $name = preg_replace("/\[.*\]$/", '', $name);
        $val = '';

        if (empty($value)) {
            $value = $fmObj->FILES($name);
            if (!empty($value['name'])) {
                $destination = dirname($value['tmp_name']).'/'.$value['name'];
                move_uploaded_file($value['tmp_name'], $destination);
                $value['tmp_name'] = $destination;
                $val = $value['name'];
                $fmObj->POST($name, serialize($value));
            }
        }

        $element->setAttribute('type', 'hidden');
        $element->setAttribute('name', 's1_attachment['.$name.']');
        if ($type != 'hidden') {
            $src = '<em class="textfield">'.htmlspecialchars($val).'</em>';
            $fmObj->insertElement($html, $element, $src, 0, 1);
        }
    }
}
