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

use Gsnowhawk\Common\Xml\Dom;
use Gsnowhawk\Common\Xml\Html;
use Gsnowhawk\Common\Variable;

/**
 * HTML form checkbox class.
 *
 * @license  http://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class Checkbox
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
        $attvalue = Html::rewindEntityReference($element->getAttribute('value'));
        $entities = mb_convert_encoding($attvalue, 'HTML-ENTITIES', mb_internal_encoding());
        $decoders = mb_convert_encoding($attvalue, mb_internal_encoding(), 'HTML-ENTITIES');
        if (Variable::isHash($value)) {
            if (preg_match("/.+\[([a-zA-Z0-9\-_]+)\]/", $name, $match)) {
                $sec = $match[1];
                $value = (isset($value[$sec])) ? $value[$sec] : null;
            }
        }
        if ((is_array($value) && (in_array($attvalue, $value) || in_array($entities, $value) || in_array($decoders, $value))) ||
            ($attvalue == $value || $entities == $value || $decoders == $value)
        ) {
            $element->setAttribute('checked', 'checked');
        } else {
            $element->removeAttribute('checked');
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
     */
    public static function preview($fmObj, $html, $form, $element, $name, $value)
    {
        $name = preg_replace("/\[.*\]$/", '', $name);
        $container = $html->getElementById($name);
        $src = '';
        if (!is_array($value)) {
            $container = $container->parentNode;
        }
        if (is_object($container)) {
            $separator = $container->getAttribute('separator');
            if (empty($separator)) {
                $separator = '<br />';
            }
            $src = '<'.$container->nodeName.' id="'.$name.'">';
            if (is_array($value)) {
                $src .= '<em class="textfield">'.implode($separator, $value).'</em>';
                foreach ($value as $val) {
                    $src .= '<input type="hidden"'.
                            ' name="'.$name.'[]"'.
                            ' value="'.$val.'"'.
                            '/>';
                }
            } else {
                $label = '';
                $node = Dom::getParentNode($element, 'label');
                if (!empty($value)) {
                    if (!is_object($node)) {
                        $id = $element->getAttribute('id');
                        $labels = $form->getElementsByTagName('label');
                        for ($l = 0, $max = $labels->length; $l < $max; ++$l) {
                            $tmp = $labels->item($l);
                            if (!empty($id) && $id === $tmp->getAttribute('for')) {
                                $node = $tmp;
                                break;
                            }
                        }
                    }
                    if (!is_object($node)) {
                        $label = $val;
                    } else {
                        $children = $node->childNodes;
                        foreach ($children as $child) {
                            if ($child->nodeType === 3) {
                                $label .= $child->nodeValue;
                            }
                        }
                    }
                }

                $src .= '<em class="textfield">'.$label.'</em>';
                $src .= '<input type="hidden"'.
                        ' name="'.$name.'"'.
                        ' value="'.$value.'"'.
                        '/>';
            }
            $src .= '</'.$container->nodeName.'>';
            if (is_array($value)) {
                $fmObj->insertElement($html, $container, $src, 0, 1);
                $container->parentNode->removeChild($container);
            } else {
                $html->replaceChild($src, $container);
            }
        }
    }
}
