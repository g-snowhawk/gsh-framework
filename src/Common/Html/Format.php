<?php
/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016-2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Html;

/**
 * Methods for form management.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @copyright (c)2016-2019 PlusFive (https://www.plus-5.com)
 * @author   Taka Goto <www.plus-5.com>
 */
class Format extends Tags
{
    private $level = 0;
    private $levels = [];
    private $parents = [];
    private $source = '';
    private $formatted = '';
    private $nl = false;
    private $prev = null;
    private $type = null;
    private $text = '';
    private $flg = '"';
    private $tab = '  ';
    private $omit = false;

    /**
     * Object constructor.
     *
     * @param string $tab
     * @param bool   $omit
     */
    public function __construct($tab = null, $omit = false)
    {
        if (!is_null($tab)) {
            $this->tab = $tab;
        }
        $this->omit = filter_var($omit, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Start format.
     *
     * @param string $source
     *
     * @return string
     */
    public function start($source)
    {
        $this->formatted = '';
        $this->level = 0;
        $this->source = preg_replace("/(\r\n|\r|\n)/", PHP_EOL, $source);
        while ($this->source) {
            if (false === $this->parse()) {
                trigger_error('HTML format failed', E_USER_NOTICE);
                $this->formatted = $source;
                break;
            }
        }

        return $this->formatted;
    }

    /**
     * HTML parser.
     *
     * @return string
     */
    private function parse()
    {
        $pattern_comment = '/^(\s*)(<!--.*?-->)([\s]*)/s';
        $pattern_dtd = '/^[\s]*<!([a-zA-Z]+)([^>]*)>([\s]*)/s';
        $pattern_end_tag = '/^[\s]*<\/([^\s>]+)([^>]*)>([\s]*)/s';
        $pattern_start_tag = '/^[\s]*(<(((?'.'>[^<>]+)|(?1))*)>)([\s]*)/s';
        $pattern_other_tag = '/^[\s]*(<[^>]*>)([\s]*)/s';
        $pattern_text_node = '/^([\s]*)([^<>]+)/s';

        // Comment
        if (preg_match($pattern_comment, $this->source, $match)) {
            $nl = '';
            $indent = '';
            if (!preg_match('/['.preg_quote(PHP_EOL, '/').']+/', $match[1])) {
                $nl = PHP_EOL;
                $indent = $this->indent();
            }
            $this->formatted .= $nl.$indent.$match[2];
            $this->type = 'comment';
            $this->source = preg_replace($pattern_comment, '', $this->source, 1);
        }

        // DTD
        elseif (preg_match($pattern_dtd, $this->source, $match)) {
            $this->formatted .= '<!'.strtoupper($match[1]).$match[2].'>';
            $this->type = 'dtd';

            $this->nl = preg_match('/['.preg_quote(PHP_EOL, '/').']+[\t ]*$/s', $match[3]);

            $this->source = preg_replace($pattern_dtd, '', $this->source, 1);
        }

        // Preformatted Text
        elseif ($this->type === 'start' && isset($this->preformatted_tags[$this->prev])) {
            $pos = strpos($this->source, '</'.$this->prev);
            $substr = substr($this->source, 0, $pos);
            $this->formatted .= $substr;
            $this->prev = 'preformatted';
            $this->type = 'source';
            $this->source = substr($this->source, $pos);
        }

        // Script source
        elseif ($this->type === 'start' && in_array($this->prev, ['script', 'style'])) {
            $pos = strpos($this->source, '</'.$this->prev);
            $substr = substr($this->source, 0, $pos);
            $this->text .= $substr;
            $this->type = 'source';
            $this->source = substr($this->source, $pos);
        }

        // End tag
        elseif (preg_match($pattern_end_tag, $this->source, $match)) {
            $tag = strtolower($match[1]);

            if (isset($this->preformatted_tags[$tag])) {
                if (preg_match('/^.+(code|var|kbd)>[\s]*$/s', $this->formatted)) {
                    $this->rtrim();
                }
            } else {
                if ($this->type === 'textnode'
                    || ($this->type === 'start' && $this->prev === $tag)
                    || ($this->type === 'end' && isset($this->inline_tags[$this->prev]))
                ) {
                    $this->rtrim();
                }
            }
            if ($tag === 'script' || $tag === 'style') {
                if (preg_match('/\S+/', $this->text)) {
                    $indent = $this->indent();
                    $tx = $this->text;

                    // Script code indention
                    if (preg_match_all("/^([ \t]*)/m", $tx, $matches)) {
                        $tmp = array_unique($matches[1]);
                        sort($tmp);
                        $src_indent = array_shift($tmp);
                    }
                    $tx = preg_replace('/'.preg_quote(PHP_EOL.$src_indent, '/').'/s', PHP_EOL.$indent, $tx);

                    $this->formatted .= preg_replace('/[\s]+$/', '', $tx);
                } else {
                    $this->type = '';
                }
                $this->text = '';
            }

            $decrement = 1;
            $get_parent = function () {
                if (count($this->parents) > 0) {
                    return end($this->parents);
                }
            };
            $parent = $get_parent();
            if (isset($this->valid_parents[$tag])) {
                $myset = $this->valid_parents[$tag];

                if (!isset($this->inline_tags[$tag])) {
                    $myset['html'] = 0;
                }

                $set_parent = function ($tag, $level) {
                    if (isset($this->empty_tags[$tag])) {
                        return;
                    }
                    if ($level === 'down') {
                        $this->parents[] = $tag;
                    } elseif ($level === 'up') {
                        array_pop($this->parents);
                    }
                };

                while (!isset($myset[$parent]) && $tag !== $parent) {
                    $set_parent($parent, 'up');
                    $parent = $get_parent();
                    ++$decrement;
                    if (!$parent) {
                        break;
                    }
                }
            }
            $this->decrement($decrement);

            // omitting end tags
            if ($this->endTagOmitting($tag) !== true) {
                $nl = '';
                $key = $tag.':'.$this->level;
                if (isset($this->always_indention_end_tags[$tag])
                    || ($this->type === 'end' && isset($this->always_wrap_end_tags[$this->prev]))
                    || (isset($this->levels[$key]) && $this->levels[$key] === 1)
                    || $this->type === 'source'
                ) {
                    $nl = PHP_EOL;
                }

                if ($tag === 'textarea') {
                    $nl = '';
                }

                $indent = ($nl === PHP_EOL) ? $this->indent() : '';
                $this->formatted .= $nl.$indent.'</'.$tag.$match[2].'>';
            }

            $this->setParent($tag, 'up');
            $this->prev = $tag;
            $this->type = 'end';
            $e = $tag.':'.$this->level;
            if (isset($this->levels[$e])) {
                $this->remove($e);
            }

            $this->nl = preg_match('/['.preg_quote(PHP_EOL, '/').']+[\t ]*$/s', $match[3]);

            $r = ($match[3] === ' ') ? ' ' : '';
            $this->source = preg_replace($pattern_end_tag, $r, $this->source, 1);
        }

        // Start tag
        elseif (preg_match($pattern_start_tag, $this->source, $outer)) {
            preg_match('/<([^\s\/>]+)(.*)>/s', $outer[1], $match);
            $tag = strtolower($match[1]);
            $nl = '';

            if ($tag === 'meta'
                && preg_match('/http-equiv="content-type"/i', $match[2])
                && preg_match('/content="text\/html;\s+charset=(.+)/i', $match[2], $cs)
            ) {
                $match[2] = ' charset="' . str_replace(['"',"'"], '', $cs[1]) . '"';
            }

            if (isset($this->always_indention_start_tags[$tag])
                || ($this->type === 'start' && isset($this->always_wrap_start_tags[$this->prev]))
                || ($this->type === 'end' && isset($this->always_wrap_end_tags[$this->prev]))
            ) {
                $nl = PHP_EOL;
            } elseif (isset($this->entrust_indention_by_source[$tag])) {
                $nl = ($this->nl) ? PHP_EOL : '';
            }

            if ($this->type === 'start' && $nl === PHP_EOL) {
                end($this->levels);
                $key = key($this->levels);
                $this->levels[$key] = 1;
            }

            // omitting start tags
            if ($match[2] || $this->startTagOmitting($tag) !== true) {
                $indent = ($nl === PHP_EOL) ? $indent = $this->indent() : '';
                $this->formatted .= $nl.$indent.'<'.$tag.$match[2].'>';
                if (!isset($this->empty_tags[$tag])) {
                    $this->append($match[1].':'.$this->level);
                    ++$this->level;
                }
            }

            $this->setParent($tag, 'down');
            $this->prev = $tag;
            $this->type = 'start';

            if (in_array($tag, ['script', 'style'])) {
                $this->text .= $outer[4];
            }

            $this->nl = preg_match('/['.preg_quote(PHP_EOL, '/').']+[\t ]*$/s', $outer[4]);

            $r = ($outer[4] === ' ') ? ' ' : '';
            $this->source = preg_replace($pattern_start_tag, $r, $this->source, 1);
        }

        // Other tag
        elseif (preg_match($pattern_other_tag, $this->source, $outer)) {
            $source = $outer[1];
            $op = substr_count($source, '<');
            $cl = substr_count($source, '>');
            $count = $op - $cl;
            if ($count !== 0) {
                --$count;
                $this->source = preg_replace($pattern_other_tag, '', $this->source, 1);
                $pattern = '/^(';
                while ($count !== 0) {
                    $pattern .= '.*?' . '>';
                    --$count;
                }
                $pattern .= ')(\s*)/s';
                preg_match($pattern, $this->source, $match);
                $source .= $match[1];
                $whitespace = $match[2];
                $this->source = preg_replace($pattern, '', $this->source, 1);
            }

            preg_match('/<([^\s\/>]+)(.*)>/s', $source, $match);
            $tag = strtolower($match[1]);
            $nl = '';

            if (isset($this->always_indention_start_tags[$tag])
                || ($this->type === 'start' && isset($this->always_wrap_start_tags[$this->prev]))
                || ($this->type === 'end' && isset($this->always_wrap_end_tags[$this->prev]))
            ) {
                $nl = PHP_EOL;
            } elseif (isset($this->entrust_indention_by_source[$tag])) {
                $nl = ($this->nl) ? PHP_EOL : '';
            }

            if ($this->type === 'start' && $nl === PHP_EOL) {
                end($this->levels);
                $key = key($this->levels);
                $this->levels[$key] = 1;
            }

            // omitting start tags
            if ($match[2] || $this->startTagOmitting($tag) !== true) {
                $indent = ($nl === PHP_EOL) ? $indent = $this->indent() : '';
                $this->formatted .= $nl.$indent.'<'.$tag.$match[2].'>';
                if (!isset($this->empty_tags[$tag])) {
                    $this->append($match[1].':'.$this->level);
                    ++$this->level;
                }
            }

            $this->setParent($tag, 'down');
            $this->prev = $tag;
            $this->type = 'start';

            if (in_array($tag, ['script', 'style'])) {
                $this->text .= $whitespace;
            }

            $this->nl = preg_match('/['.preg_quote(PHP_EOL, '/').']+[\t ]*$/s', $whitespace);
        }

        // Text
        elseif (preg_match($pattern_text_node, $this->source, $match)) {
            $nl = ($this->nl) ? PHP_EOL : '';
            if (preg_match('/['.preg_quote(PHP_EOL, '/').']+/', $match[1])) {
                $nl = PHP_EOL;
            }
            if ($this->type === 'start') {
                if (isset($this->always_wrap_start_tags[$this->prev])) {
                    $nl = PHP_EOL;
                }
                if ($this->type === 'start' && $nl === PHP_EOL) {
                    end($this->levels);
                    $key = key($this->levels);
                    $this->levels[$key] = 1;
                }
            }
            if (!in_array($this->type, ['start', 'end']) || !preg_match('/^[\s]+</', $this->source)) {
                $indent = ($nl === PHP_EOL) ? $this->indent() : '';

                $space = ($match[1] === ' ') ? ' ' : '';

                $this->formatted .= $nl.$indent.$space.$match[2];
                $this->type = 'textnode';
            }

            $this->nl = preg_match('/['.preg_quote(PHP_EOL, '/').']+[\t ]*$/s', $match[2]);

            $this->source = preg_replace($pattern_text_node, '', $this->source);
        }

        // Failed format
        else {
            return false;
        }
    }

    /**
     * Indent source.
     *
     * @return string
     */
    private function indent()
    {
        return str_repeat($this->tab, $this->level);
    }

    /**
     * Decrement nested level.
     *
     * @param int $n
     *
     * @return string
     */
    private function decrement($n = 1)
    {
        $this->level -= $n;
        if ($this->level < 0) {
            $this->level = 0;
        }
    }

    /**
     * Append to level array.
     *
     * @param string $value
     */
    private function append($value)
    {
        $this->levels[$value] = 0;
    }

    /**
     * Remove from level array.
     *
     * @param string $value
     */
    private function remove($value)
    {
        unset($this->levels[$value]);
    }

    /**
     * Right trim source code.
     *
     * @param string $replace
     */
    private function rtrim($replace = null)
    {
        if (is_null($replace)) {
            $this->formatted = rtrim($this->formatted);
        } else {
            $this->formatted = preg_replace('/[\s]+$/s', $replace, $this->formatted);
        }
    }

    /**
     * Set the parent array.
     *
     * @param string $tag
     * @param string $level
     */
    private function setParent($tag, $level)
    {
        if (isset($this->empty_tags[$tag])) {
            return;
        }
        if ($level === 'down') {
            $this->parents[] = $tag;
        } elseif ($level === 'up') {
            array_pop($this->parents);
        }
    }

    /**
     * Get the parent array.
     *
     * @return string
     */
    private function getParent()
    {
        if (count($this->parents) > 0) {
            return end($this->parents);
        }
    }

    /**
     * Omitting the start tags.
     *
     * @param string $tag
     *
     * @return string
     */
    private function startTagOmitting($tag)
    {
        if (!$this->omit) {
            return false;
        }
        if (!isset($this->omit_start_tags[$tag])) {
            return false;
        }

        /*
         * An html element is start tag may be omitted
         * if the first thing inside the html element is not a comment.
         */
        if ($tag === 'html') {
            if (preg_match('/<\/html>[\s]*<!--.*?-->/is', $this->source)) {
                return false;
            }
            if (!preg_match('/^[\s]*<!--.*?-->/s', $this->source)) {
                return true;
            }
        }

        /*
         * A head element is start tag may be omitted
         * if the element is empty, or
         * if the first thing inside the head element is an element.
         */
        elseif ($tag === 'head') {
            if (preg_match('/^[\s]*<([^!\?].+|\/head)>/is', $this->source)) {
                return true;
            }
        }

        /*
         * A body element is start tag may be omitted
         * if the element is empty, or
         * if the first thing inside the body element
         * is not a space character or a comment, except
         * if the first thing inside the body element is a meta,
         * link, script, style, or template element.
         */
        elseif ($tag === 'body') {
            if (preg_match('/<\/body>[\s]*<!--.*?-->/is', $this->source)
                || preg_match('/^[\s]*<(meta|link|script|style|template).*?'.'>/is', $this->source)
            ) {
                return false;
            }

            if (preg_match('/^[\s]*<\/body>/is', $this->source)) {
                return true;
            }

            return $this->followedWhitespaceOrComments();
        }

        /*
         * A colgroup element is start tag may be omitted
         * if the first thing inside the colgroup element
         * is a col element, and if the element is not immediately
         * preceded by another colgroup element
         * whose end tag has been omitted.
         * (It can't be omitted if the element is empty.)
         */
        elseif ($tag === 'colgroup') {
            if (preg_match('/^.+<(colgroup).*?'.'>(((?!<\/\1>).)*)$/is', $this->formatted)) {
                return false;
            }
            if (preg_match('/^[\s]*<col.*?'.'>/is', $this->source)) {
                return true;
            }
        }

        /*
         * A tbody element is start tag may be omitted
         * if the first thing inside the tbody element
         * is a tr element, and if the element is not immediately
         * preceded by a tbody, thead, or tfoot element
         * whose end tag has been omitted.
         * (It can't be omitted if the element is empty.)
         */
        elseif ($tag === 'tbody') {
            if (preg_match('/^.+<(tbody|thead|tfoot).*?'.'>(((?!<\/\1>).)+)$/is', $this->formatted)) {
                return false;
            }
            if (preg_match('/^[\s]*<tr>/is', $this->source)) {
                return true;
            }
        }
    }

    /**
     * Omitting the end tags.
     *
     * @param string $tag
     *
     * @return string
     */
    private function endTagOmitting($tag)
    {
        if (!$this->omit) {
            return false;
        }
        if (!isset($this->omit_end_tags[$tag])) {
            return false;
        }

        /*
         * An html element is end tag may be omitted
         * if the html element is not immediately followed by a comment.
         *
         * A body element is end tag may be omitted
         * if the body element is not immediately followed by a comment.
         */
        if (in_array($tag, ['html', 'body'])) {
            if (!preg_match('/^[\s]*<!--.*?-->/s', $this->source)) {
                return true;
            }
        }

        /*
         * A head element is end tag may be omitted
         * if the head element is not immediately
         * followed by a space character or a comment.
         */
        elseif ($tag === 'head') {
            return $this->followedWhitespaceOrComments();
        }

        /*
         * A p element is end tag may be omitted
         * if the p element is immediately followed by an address,
         * article, aside, blockquote, div, dl, fieldset, footer,
         * form, h1, h2, h3, h4, h5, h6, header, hgroup, hr, main,
         * nav, ol, p, pre, section, table, or ul, element, or
         * if there is no more content in the parent element and
         * the parent element is not an a element.
         */
        elseif ($tag === 'p') {
            if (preg_match('/^[\s]*<\/a>/is', $this->source)) {
                return false;
            }
            if (preg_match('/^[\s]*<(address|article|aside|blockquote|div|dl|fieldset|footer|form|h[1-6]|header|hgroup|hr|main|nav|ol|p|pre|section|table|ul)[^>]*>/is', $this->source)
                || preg_match('/^[\s]*<\/.+?'.'>/s', $this->source)
            ) {
                return true;
            }
        }

        /*
         * An li element is end tag may be omitted
         * if the li element is immediately followed by another li element or
         * if there is no more content in the parent element.
         */
        elseif ($tag === 'li') {
            if (preg_match('/^[\s]*<\/?(ul|ol|li).*?'.'>/is', $this->source)) {
                return true;
            }
        }

        /*
         * A dt element is end tag may be omitted
         * if the dt element is immediately followed by another dt element or
         * a dd element.

         * A dd element is end tag may be omitted
         * if the dd element is immediately followed by another dd element or
         * a dt element, or if there is no more content in the parent element.
         */
        elseif (in_array($tag, ['dt', 'dd'])) {
            if (preg_match('/^[\s]*<\/?(dl|d[dt]).*?'.'>/is', $this->source)) {
                return true;
            }
        }

        /*
         * A thead element is end tag may be omitted
         * if the thead element is immediately
         * followed by a tbody or tfoot element.
         *
         * A tbody element is end tag may be omitted
         * if the tbody element is immediately
         * followed by a tbody or tfoot element, or
         * if there is no more content in the parent element.
         */
        elseif (in_array($tag, ['thead', 'tbody'])) {
            if (preg_match('/^[\s]*<(tfoot|tbody).*?'.'>/is', $this->source)) {
                return true;
            }
            if ($tag === 'tbody') {
                if (preg_match('/^[\s]*<\/table>/is', $this->source)) {
                    return true;
                }
            }
        }

        /*
         * A tfoot element is end tag may be omitted
         * if the tfoot element is immediately followed by a tbody element,
         * or if there is no more content in the parent element.
         */
        elseif ($tag === 'tfoot') {
            if (preg_match('/^[\s]*<\/table>/is', $this->source)) {
                return true;
            }
        }

        /*
         * A colgroup element is end tag may be omitted
         * if the colgroup element is not immediately
         * followed by a space character or a comment.
         */
        elseif ($tag === 'colgroup') {
            return $this->followedWhitespaceOrComments();
        }

        /*
         * A tr element is end tag may be omitted
         * if the tr element is immediately followed by another tr element,
         * or if there is no more content in the parent element.
         */
        elseif ($tag === 'tr') {
            if (preg_match('/^[\s]*<(tr|\/table|\/tbody|\/thead|\/tfoot).*?'.'>/is', $this->source)) {
                return true;
            }
        }

        /*
         * A td element is end tag may be omitted
         * if the td element is immediately followed by a td or th element,
         * or if there is no more content in the parent element.
         *
         * A th element is end tag may be omitted
         * if the th element is immediately followed by a td or th element,
         * or if there is no more content in the parent element.
         */
        elseif (in_array($tag, ['th', 'td'])) {
            if (preg_match('/^[\s]*<\/?(t[dhr]|table|tbody|thead|tfoot).*?'.'>/is', $this->source)) {
                return true;
            }
        }

        /*
         * An optgroup element is end tag may be omitted
         * if the optgroup element is immediately followed by another optgroup element,
         * or if there is no more content in the parent element.
         */
        elseif ($tag === 'optgroup') {
            if (preg_match('/^[\s]*<\/?(select|optgroup).*?'.'>/is', $this->source)) {
                return true;
            }
        }

        /*
         * An option element is end tag may be omitted
         * if the option element is immediately
         * followed by another option element, or
         * if it is immediately followed by an optgroup element, or
         * if there is no more content in the parent element.
         */
        elseif ($tag === 'option') {
            if (preg_match('/^[\s]*<\/?(select|optgroup|option).*?'.'>/is', $this->source)) {
                return true;
            }
        }

        /*
         * An rb element is end tag may be omitted
         * if the rb element is immediately
         * followed by an rb, rt, rtc or rp element, or
         * if there is no more content in the parent element.
         *
         * An rt element is end tag may be omitted
         * if the rt element is immediately
         * followed by an rb, rt, rtc, or rp element, or
         * if there is no more content in the parent element.
         *
         * An rp element is end tag may be omitted
         * if the rp element is immediately
         * followed by an rb, rt, rtc or rp element, or
         * if there is no more content in the parent element.
         */
        elseif (in_array($tag, ['rb', 'rt', 'rtc', 'rp'])) {
            if (preg_match('/^[\s]*<(rb|rt|rtc|rp|\/ruby).*?'.'>/is', $this->source)) {
                return true;
            }
        }

        /*
         * An rtc element is end tag may be omitted
         * if the rtc element is immediately
         * followed by an rb, rtc or rp element, or
         * if there is no more content in the parent element.
         */
        elseif ($tag === 'rtc') {
            if (preg_match('/^[\s]*<(rb|rtc|rp|\/ruby).*?'.'>/is', $this->source)) {
                return true;
            }
        }
    }

    /**
     * Shift the level.
     *
     * @param string $tag
     *
     * @return string
     */
    private function shiftLevel($tag)
    {
        $shift_count = 0;
        $get_parent = function () {
            if (count($this->parents) > 0) {
                return end($this->parents);
            }
        };
        $parent = $get_parent();
        if (isset($this->valid_parents[$tag])) {
            $myset = $this->valid_parents[$tag];

            if (!isset($this->inline_tags[$tag])) {
                $myset['html'] = 0;
            }

            $set_parent = function ($tag, $level) {
                if (isset($this->empty_tags[$tag])) {
                    return;
                }
                if ($level === 'down') {
                    $this->parents[] = $tag;
                } elseif ($level === 'up') {
                    array_pop($this->parents);
                }
            };

            while (!isset($myset[$parent])) {
                $set_parent($parent, 'up');
                $this->decrement();
                $this->rtrim();
                $parent = $get_parent();
                ++$shift_count;
                if (!$parent) {
                    break;
                }
            }
        }

        return $shift_count;
    }

    /**
     * Followed whitespace or comments.
     *
     * @return string
     */
    private function followedWhitespaceOrComments()
    {
        if (preg_match('/^([\s]+)/s', $this->source, $match)) {
            if (preg_match("/[\f\v]+/", $match[1])
                || substr_count(PHP_EOL, $match[1]) > 1
            ) {
                return false;
            }
        }
        if (!preg_match('/^[\s]*<!--.*?-->/s', $this->source)) {
            return true;
        }
    }

    /**
     * Check tail of source code.
     *
     * @return string
     */
    private function checkTail($str)
    {
        return preg_match('/['.preg_quote(PHP_EOL, '/').']+[\t ]*$/s', $str);
    }

    /**
     * Wrapped start tag.
     *
     * @param string $nl
     */
    private function wrappedStartTag($nl)
    {
        if ($this->type === 'start' && $nl === PHP_EOL) {
            end($this->levels);
            $key = key($this->levels);
            $this->levels[$key] = 1;
        }
    }
}
