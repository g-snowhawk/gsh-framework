<?php
/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Html;

use Gsnowhawk\Common\Environment;
use Gsnowhawk\Common\Text;

/**
 * Methods for form management.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Form
{
    private $post = [];
    private $get = [];

    private $request_method_via_cli = 'post';

    /**
     * Object constructer.
     */
    public function __construct()
    {
        foreach ($_GET as $key => $value) {
            $this->get[$key] = Text::convert(filter_input(INPUT_GET, $key));
        }
        foreach ($_POST as $key => $value) {
            if (is_array($value)) {
                $this->post[$key] = Text::convert(filter_input(INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY));
                continue;
            }
            $this->post[$key] = Text::convert(filter_input(INPUT_POST, $key));
        }
    }

    /**
     * Getter Method.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        switch ($key) {
            case 'method':
                if (php_sapi_name() === 'cli') {
                    return $this->request_method_via_cli;
                }
                $method = strtolower(Environment::server('REQUEST_METHOD'));

                return (empty($method)) ? 'get' : $method;
                break;
        }
    }

    /**
     * Super Globals.
     *
     * @param mixed $name
     * @param mixed $value
     * @param bool  $kill
     *
     * @return mixed
     */
    public function post($name = null, $value = null, $kill = false)
    {
        if (is_null($name)) {
            return $this->post;
        }
        $name = preg_replace('/\[.*$/', '', $name);
        if ($kill === true) {
            unset($this->post[$name]);

            return;
        }
        if (isset($value)) {
            $this->post[$name] = $value;
        }

        return (isset($this->post[$name])) ? $this->post[$name] : null;
    }

    /**
     * Super Globals.
     *
     * @param mixed $name
     * @param mixed $value
     * @param bool  $kill
     *
     * @return mixed
     */
    public function get($name = null, $value = null, $kill = false)
    {
        if (is_null($name)) {
            return $this->get;
        }
        $name = preg_replace('/\[.*$/', '', $name);
        if ($kill === true) {
            unset($this->get[$name]);

            return;
        }
        if (isset($value)) {
            $this->get[$name] = $value;
        }

        return (isset($this->get[$name])) ? $this->get[$name] : null;
    }

    /**
     * Super Globals.
     *
     * @param mixed $name
     *
     * @return mixed
     */
    public function files($name = null)
    {
        if (empty($name)) {
            return $_FILES;
        }

        return (isset($_FILES[$name])) ? $_FILES[$name] : null;
    }

    /**
     * form data.
     *
     * @param mixed $name
     * @param mixed $value
     * @param bool  $kill
     *
     * @return mixed
     */
    public function param($name = null, $value = null, $kill = false)
    {
        $method = $this->method;
        if (false === method_exists($this, $method)) {
            return;
        }

        return $this->$method($name, $value, $kill);
    }

    /**
     * Check exists request data
     *
     * @param mixed $name
     *
     * @return bool
     */
    public function isset($name)
    {
        $method = $this->method;

        return (isset($this->$method[$name]));
    }

    /**
     * Clear request data
     *
     * @param mixed $name
     *
     * @return bool
     */
    public function clear(...$names)
    {
        $method = $this->method;
        foreach ($names as $name) {
            if (isset($this->$method[$name])) {
                unset($this->$method[$name]);
            }
        }
    }

    /**
     * make Pref selector.
     *
     * @param string $name
     * @param string $selected
     * @param bool   $optonly
     *
     * @return string
     */
    public static function prefSelector($name, $selected, $optonly = 0, $label = '')
    {
        $src = '';
        if (!$optonly) {
            $src .= '<select name="'.$name.'" id="'.$name.'">'."\n";
        }

        $prefs = Form\Lang\Ja::PREFS();

        if (!empty($label)) {
            $src .= '<option value="">'.$label.'</option>';
        }

        foreach ($prefs as $pref) {
            $src .= '<option value="'.$pref.'"';
            if ($pref == $selected) {
                $src .= ' selected="selected"';
            }
            $src .= '>'.$pref.'</option>'."\n";
        }
        if (!$optonly) {
            $src .= '</select>'."\n";
        }

        return $src;
    }

    /**
     * key is exists.
     *
     * @param string $key
     * @param string $method
     *
     * @return bool
     */
    public function keyExists($key, $method = 'post')
    {
        if (strtolower($method) !== 'post') {
            return isset($_GET[$key]);
        }

        return isset($_POST[$key]);
    }

    /**
     * Emulate request method via CLI
     *
     * @param string $method
     *
     * @return void
     */
    public function setRequestMethodViaCli($method)
    {
        if (in_array(strtolower($method), ['post','get'])) {
            $this->request_method_via_cli = $method;
        }
    }

    /**
     * Reset parameters
     *
     * @return void
     */
    public function reset(): void
    {
        $this->post = [];
        $this->get = [];
    }

    public static function parseForm($source, $formkey = null, $no_buttons = false): array
    {
        $form = [];
        if (!preg_match_all('/<form\s+([^>]+)>(.+?)<\/form>/is', $source, $forms)) {
            return [];
        }

        $regex = '/<(button|input|select|textarea)\s+([^>]+)>((.*?)<\/\1>)?/is';
        foreach ($forms[2] as $f => $src) {
            preg_match_all($regex, $src, $matches);
            $data = [];
            foreach ($matches[0] as $i => $element) {
                $node_name = strtolower($matches[1][$i]);
                $attrs = $matches[2][$i];
                $child_nodes = $matches[4][$i];

                $name = null;
                $value = null;
                $type = null;
                $choice = null;

                // name
                if (preg_match('/name="([^"]+)"/i', $attrs, $match)) {
                    $name = $match[1];
                }
                if (empty($name)) {
                    continue;
                }

                $name_key = null;
                if (preg_match('/^(.+)\[(.*)\]$/', $name, $match)) {
                    $name = $match[1];
                    $name_key = $match[2];
                }

                switch ($node_name) {
                    case 'button':
                        if (preg_match('/value="([^"]*)"/i', $attrs, $match)) {
                            $value = $match[1];
                        }
                        if (preg_match('/type="([^"]+)"/i', $attrs, $match)) {
                            $type = $match[1];
                        }
                        break;
                    case 'input':
                        if (preg_match('/value="([^"]*)"/i', $attrs, $match)) {
                            $value = $match[1];
                        }
                        if (preg_match('/type="([^"]+)"/i', $attrs, $match)) {
                            $type = $match[1];
                            if ($type === 'checkbox' || $type === 'radio') {
                                if (stripos($attrs, 'checked') !== false) {
                                    $choice = $value;
                                }
                            }
                        }
                        break;
                    case 'select':
                        if (preg_match_all('/<option([^>]*)>(.+?)<\/option>/is', $child_nodes, $hits)) {
                            foreach ($hits[1] as $n => $attr) {
                                $val = (preg_match('/value="([^"]*)"/i', $attr, $match)) ? $match[1] : $hits[2][$n];
                                if (is_null($value)) {
                                    $value = [];
                                }
                                $value[] = $val;
                                if (stripos($attr, 'selected') !== false) {
                                    if (is_null($choice)) {
                                        $choice = [];
                                    }
                                    if (is_null($name_key)) {
                                        $choice = $val;
                                    } else {
                                        $choice[] = $val;
                                    }
                                }
                            }
                            if (is_null($choice) && !empty($value)) {
                                $choice = (is_null($name_key)) ? $value[0] : [$value[0]];
                            }
                        }
                        $type = $node_name;
                        break;
                    case 'textarea':
                        $value = $child_nodes;
                        $type = $node_name;
                        break;
                }

                if ($no_buttons) {
                    if ($node_name === 'button' || in_array($type, ['button','reset','submit'])) {
                        continue;
                    }
                }

                switch (true) {
                    case !isset($data[$name]):
                        if (!is_null($name_key) && !is_array($value)) {
                            $value = (empty($name_key)) ? [$value] : [$name_key => $value];
                        }
                        $data[$name] = [
                            'tag' => $node_name,
                            'type' => $type,
                            'value' => $value,
                        ];
                        if (!is_null($choice)) {
                            if (!is_null($name_key)) {
                                $choice = (empty($name_key)) ? [$choice] : [$name_key => $choice];
                            }
                            $data[$name]['choices'] = $choice;
                        }
                        break;
                    case is_null($name_key):
                        if ($type === 'checkbox' || $type === 'radio') {
                            if (!isset($data[$name]['value'])) {
                                $data[$name]['value'] = [];
                            } elseif (!is_array($data[$name]['value'])) {
                                $data[$name]['value'] = [$data[$name]['value']];
                            }
                            $data[$name]['value'][] = $value;

                            if ($data[$name]['type'] !== $type && is_null($choice)) {
                                $data[$name]['type'] = $type;
                                $choice = $data[$name]['value'];
                            }
                        } else {
                            $data[$name]['value'] = $value;
                        }
                        if (!is_null($choice)) {
                            if (is_array($choice)) {
                                $choice = array_shift($choice);
                            }
                            $data[$name]['choices'] = $choice;
                        }
                        break;
                    default:
                        //if (!is_array($data[$name]['value'])) {
                    //    $data[$name]['value'] = [$data[$name]['value']];
                        //}
                        //if (is_array($value)) {
                    //    $data[$name]['value'] = array_merge($data[$name]['value'], $value);
                        //} else {
                    //    $data[$name]['value'][] = $value;
                        //}
                        if (empty($name_key)) {
                            $data[$name]['value'][] = $value;
                        } else {
                            if ($type === 'radio' && isset($data[$name]['value'][$name_key])) {
                                if (!is_array($data[$name]['value'][$name_key])) {
                                    $data[$name]['value'][$name_key] = [$data[$name]['value'][$name_key]];
                                }
                                $data[$name]['value'][$name_key][] = $value;
                            } else {
                                $data[$name]['value'][$name_key] = $value;
                            }
                        }
                        if (!is_null($choice)) {
                            if (!isset($data[$name]['choices'])) {
                                $data[$name]['choices'] = [];
                            }
                            //if (is_array($choice)) {
                            //    $data[$name]['choices'] = array_merge($data[$name]['choices'], $choice);
                            //} else {
                            //    $data[$name]['choices'][] = $choice;
                            //}
                            if (empty($name_key)) {
                                $data[$name]['choices'][] = $choice;
                            } else {
                                $data[$name]['choices'][$name_key] = $choice;
                            }
                        }
                        break;
                }
            }

            $key = $f;
            if (preg_match('/(name|id)="([^"]+)"/i', $forms[1][$f], $match)) {
                $key = $match[2];
            }
            $form[$key] = $data;
        }

        return (empty($formkey)) ? $form : ($form[$formkey] ?? []);
    }
}
