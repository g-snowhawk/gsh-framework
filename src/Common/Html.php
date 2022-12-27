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
 * Methods for HTML.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Html
{
    /**
     * decode escaped HTML tags.
     *
     * @param string $str
     * @param array  $allowtags
     *
     * @return string
     */
    public static function allowtag($str, array $allowtags)
    {
        foreach ($allowtags as $tag) {
            if (empty($tag)) {
                continue;
            }
            $str = preg_replace_callback("/&lt;($tag)(.*?)&gt;/i", '\\Gsnowhawk\\Common\\Html::retag', $str);
            $str = preg_replace("/&lt;\/$tag&gt;/i", "</$tag>", $str);
        }

        return $str;
    }

    /**
     * Replace HTML tag.
     *
     * @param array $match
     *
     * @return string
     */
    public static function retag($match)
    {
        $tag = strtolower($match[1]);
        $attr = preg_replace('/&quot;/', '"', $match[2]);
        $slash = (isset(self::$emptyTags[$tag])) ? ' /' : '';

        return "<$tag$attr$slash>";
    }

    /**
     * Paragraph.
     *
     * @param string $str
     * @param bool   $plain
     *
     * @return string
     */
    public static function paragraph($str, $plain = false)
    {
        if (empty($str)) {
            return '';
        }
        $str = preg_replace("/(\r\n|\r)/", "\n", $str);
        $str = preg_replace('/<br[^>]*>/i', "\n", $str);
        $blocks = '(H[1-6R]|P|DIV|ADDRESS|PRE|FORM|T(ABLE|BODY|HEAD|FOOT|H|R|D)|LI|OL|UL|CAPTION|BLOCKQUOTE|CENTER|DL|DT|DD|DIR|FIELDSET|NOSCRIPT|MENU|ISINDEX|SAMP)';
        $str = preg_replace("/<\/$blocks>[\s]?<$blocks([^>]*)>/is", '</$1><$2$3>', $str);
        $str = preg_replace("/<$blocks([^>]*)>/is", "\n\n<$1$2>", $str);
        $str = preg_replace("/(^[\s]+|[\s]+$)/s", '', $str);
        $paragraphs = preg_split("/\n{2}/", $str);
        $src = '';
        $class = '';
        for ($i = 0, $len = count($paragraphs); $i < $len; ++$i) {
            $paragraph = $paragraphs[$i];
            if (empty($paragraph)) {
                $paragraph = '&nbsp;';
            }
            if ($i == 0 && $plain == false) {
                $class = ' class="at-first"';
            }
            if ($i == $len - 1 && $plain == false) {
                $class = ' class="at-last"';
            }
            if ($len == 1 && $plain == false) {
                $class = ' class="at-first at-last"';
            }
            if (preg_match("/^<$blocks([^>]*)>/is", $paragraph)) {
                $src .= $paragraph;
            } else {
                $paragraph = preg_replace("/\n/", "<br />\n", $paragraph);
                $src .= "<p$class>$paragraph</p>\n";
            }
            $class = '';
        }

        return $src;
    }

    /**
     * convert source encoding.
     *
     * @param string $source
     * @param string $enc
     *
     * @return string
     */
    public static function convertEncoding($source, $enc)
    {
        if (empty($enc)) {
            if (false !== $charset = self::metaCheckCharset($source)) {
                $enc = (empty($charset)) ? 'none' : $charset;
            }
        }
        switch (strtolower($enc)) {
            case 'x-sjis':
                $enc = 'Shift_JIS';
                // no break
            case 'shift_jis':
                $encTo = 'SJIS';
                break;
            case 'gb2312':
                $encTo = 'EUC-CN';
                break;
            case 'none':
                $encTo = mb_internal_encoding();
                $source = mb_encode_numericentity($source, [0x80, 0x10ffff, 0, 0x1fffff], $encFrom);
                $enc = '';
                break;
            default:
                $encTo = $enc;
                break;
        }
        $encTo = Text::checkEncodings($encTo);
        $encFrom = mb_internal_encoding();
        if (strtolower($encTo) != $encFrom) {
            if (!empty($encTo)) {
                $source = mb_convert_encoding(
                    self::replaceXmlEncoding(
                        self::replaceHtmlCharset($source, $enc),
                        $enc
                    ),
                    $encTo,
                    $encFrom
                );
            }
        }

        return $source;
    }

    /**
     * Replace XML encoding.
     *
     * @param string $source
     * @param string $enc
     *
     * @return string
     */
    public static function replaceXmlEncoding($source, $enc)
    {
        $pattern = "/<\?xml[\s]+version\s*=\s*[\"']?([0-9\.]+)[\"']?[\s]+encoding=[\"']?[0-9a-z-_]+[\"']?\s*\?".'>/i';
        $attr = (empty($enc)) ? '' : " encoding=\"{$enc}\"";
        $replace = '<?xml version="$1"'.$attr.'?'.'>';

        return preg_replace($pattern, $replace, $source);
    }

    /**
     * Replace HTML charset.
     *
     * @param string $source
     * @param string $enc
     *
     * @return string
     */
    public static function replaceHtmlCharset($source, $enc)
    {
        $pattern = "/<meta [^>]*http-equiv\s*=\s*[\"']?content-type[\"']?[^>]*?(\/?)>/i";
        $attr = (empty($enc)) ? '' : "; charset={$enc}";
        $replace = '<meta http-equiv="Content-type" '.
                   'content="text/html'.$attr.'"$1>';

        return preg_replace($pattern, $replace, $source);
    }

    /**
     * Style attribute.
     *
     * @param array  $styles
     * @param string $selector
     *
     * @return string
     */
    public static function styleAttr($styles, $selector = null)
    {
        $arr = [];
        foreach ($styles as $key => $value) {
            if (!empty($value)) {
                $arr[] = htmlspecialchars($key).':'.htmlspecialchars($value);
            }
        }
        if (empty($arr)) {
            return '';
        }
        if (is_null($selector)) {
            return 'style="'.implode(';', $arr).';"';
        } else {
            return $selector.' { '.implode(';', $arr).'; }';
        }
    }

    /**
     * check caractorset.
     *
     * @param string $source
     *
     * @return mixed
     */
    public static function metaCheckCharset($source)
    {
        $pattern = "/<meta ([^>]*)http-equiv\s*=\s*[\"']?content-type[\"']?([^>]*)(\/?)>/i";
        if (preg_match($pattern, $source, $match)) {
            foreach ($match as $reg) {
                if (preg_match("/charset\s*=\s*([0-9a-z_-]+)/i", $reg, $cs)) {
                    return $cs[1];
                }
            }

            return '';
        }

        return false;
    }
}
