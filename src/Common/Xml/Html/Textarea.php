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
 * HTML form textarea class.
 *
 * @license  http://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class Textarea
{
    /**
     * Current version.
     */
    public const VERSION = '1.1.0';

    /**
     * Setting default value.
     *
     * @param object $fmObj
     * @param object $html
     * @param object $element
     * @param string $value
     */
    public static function setValue($fmObj, $html, $element, $value)
    {
        $value = preg_replace("/\r\n/", "\n", $value);
        $node = $element->ownerDocument->createTextNode($value);
        $element->appendChild($node);
    }

    /**
     * Replace preview elements.
     *
     * @param object $fmObj
     * @param object $html
     * @param object $element
     * @param string $name
     * @param string $value
     */
    public static function preview($fmObj, $html, $element, $name, $value)
    {
        $parent = $element->parentNode;
        $src = '<input type="hidden" name="'.$name.'" />';
        $node = $fmObj->insertElement($html, $element, $src);
        if (is_array($node)) {
            $node[0]->setAttribute('value', $value);
        } else {
            if (method_exists($node, 'setAttribute')) {
                $node->setAttribute('value', $value);
            }
        }
        $value = preg_replace("/(\r\n|\r|\n)/", '<br />', htmlspecialchars($value));
        $src = '<em class="textbox">'.$value.'</em>';
        $fmObj->insertElement($html, $element, $src);
        $parent->removeChild($element);
    }
}
