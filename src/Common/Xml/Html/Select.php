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

use Gsnowhawk\Common\Text;
use Gsnowhawk\Common\Xml\Html;

/**
 * HTML form select element class.
 *
 * @license  http://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class Select
{
    /**
     * Current version.
     */
    public const VERSION = '1.1.0';

    public static function setValue($fmObj, $html, $element, $name, $value)
    {
        if (is_array($value)) {
            if (preg_match("/.+\[([a-zA-Z0-9_\-]+)\]/", $name, $match)) {
                if (isset($value[$match[1]])) {
                    $value = $value[$match[1]];
                } else {
                    return;
                }
            }
        }

        // options
        $options = $element->getElementsByTagName('option');

        for ($j = 0, $max = $options->length; $j < $max; ++$j) {
            $val = $value;
            $opt = $options->item($j);
            if (false === $opt->hasAttribute('value')) {
                $optValue = $opt->nodeValue;
            } else {
                $optValue = $opt->getAttribute('value');
            }
            $attvalue = Html::rewindEntityReference($optValue);
            $entities = mb_encode_numericentity($attvalue, Text::CONVMAP);
            $decoders = mb_decode_numericentity($attvalue, Text::CONVMAP);
            if ((is_array($val) && (in_array($attvalue, $val) || in_array($entities, $val) || in_array($decoders, $val))) ||
                ($attvalue == $val || $entities == $val || $decoders == $val)
            ) {
                $opt->setAttribute('selected', 'selected');
            } else {
                $opt->removeAttribute('selected');
            }
        }
    }

    /**
     * Change source Input to Preview.
     *
     * @param object $fmObj
     * @param object $html
     * @param object $form
     * @param object $element
     * @param string $name
     * @param mixed  $value
     * @param mixed  $sec
     */
    public static function preview($fmObj, $html, $form, $element, $name, $value, $sec)
    {
        if (is_array($value)) {
            $num = 0;
            // ???
            if (preg_match("/(.+)\[\]/", $name, $match)) {
                if (isset($sec[$match[1]])) {
                    $num = $sec[$match[1]]++;
                } else {
                    $sec[$match[1]] = 1;
                }
            }
            if (preg_match("/.+\[([a-zA-Z0-9]+)\]/", $name, $match)) {
                $num = $match[1];
            }
            $value = $value[$num];
        }

        $opts = $element->getElementsByTagName('option');
        $label = $value;
        foreach ($opts as $opt) {
            $oVal = $opt->getAttribute('value');
            if (!empty($oVal) && $oVal == $value) {
                $label = $opt->firstChild->nodeValue;
                break;
            }
        }
        $parent = $element->parentNode;
        // replace
        $src = '<input type="hidden" '.'name="'.$name.'" />';
        $node = $fmObj->insertElement($html, $element, $src);
        if (is_array($node)) {
            $node[0]->setAttribute('value', $value);
        } else {
            if (method_exists($node, 'setAttribute')) {
                $node->setAttribute('value', $value);
            }
        }
        //$src = '<em class="textfield">' . $value . '</em>';
        $src = '<em class="textfield">'.$label.'</em>';
        $fmObj->insertElement($html, $element, $src);
        $parent->removeChild($element);
    }
}
