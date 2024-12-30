<?php

/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016 PlusFive (http://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * http://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Xml;

/**
 * HTML source format class.
 *
 * @license  http://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class Format
{
    /**
     * Current version.
     */
    public const VERSION = '1.1.0';

    /**
     * XML Parser object.
     *
     * @var resource
     */
    private $_parser;

    /**
     * Formatted Source.
     *
     * @var string
     */
    private $_formatted = '';

    /**
     * Preformatted Source.
     *
     * @var string
     */
    private $_preformatted = 0;

    /**
     * return code.
     *
     * @var string
     */
    private $_lineBreak = '';

    /**
     * Indent character.
     *
     * @var string
     */
    private $_tabSpace = ' ';

    /**
     * Tab size.
     *
     * @var int
     */
    private $_tabSize = 2;

    /**
     * indent level.
     *
     * @var int
     */
    private $_level = 0;

    /**
     * which whitespace.
     *
     * @var bool
     */
    private $_skipWrap = false;

    /**
     * Preformatting.
     *
     * @var number
     */
    private $_preformated = 0;

    /**
     * CDATA?
     *
     * @var bool
     */
    private $_isCdata = false;

    /**
     * Current tag name or content.
     *
     * @var string
     */
    private $_currentContent = '';

    /**
     * Current element type.
     *
     * @var string
     */
    private $_currentType = '';

    /**
     * XHTML Closer.
     *
     * @ver string
     */
    private $_xhtmlCloser = '';

    /**
     * No Decl.
     *
     * @var string
     */
    private $_pi = null;

    /**
     * No Doctype.
     *
     * @var bool
     */
    private $_dtd = null;

    /**
     * Start Doctype.
     *
     * @var bool
     */
    private $_startdtd = false;

    /**
     * Always wrap Tags.
     *
     * @var array
     */
    private $_wrapAlways = [
        'html' => '', 'head' => '', 'body' => '', 'meta' => '', 'link' => '', 'form' => '', 'map' => '',
        'center' => '', 'frameset' => '',
        'table' => '', 'caption' => '', 'tr' => '', 'thead' => '', 'tbody' => '', 'tfoot' => '',
        'ul' => '', 'ol' => '', 'dl' => '', 'hr' => '', 'noscript' => '', 'optgroup' => '',
        'article' => '', 'header' => '', 'hgroup' => '', 'footer' => '', 'section' => '', 'nav' => '',
        'main' => '', 'template' => '',
    ];

    /**
     * Always wrap Open Tags.
     *
     * @var array
     */
    private $_wrapOpen = [
        'h1' => '', 'h2' => '', 'h3' => '', 'h4' => '', 'h5' => '', 'h6' => '',
        'p' => '', 'li' => '', 'dt' => '', 'dd' => '',
        'title' => '', 'script' => '', 'style' => '', 'div' => '', 'th' => '', 'td' => '',
        'pre' => '', 'address' => '', 'blockquote' => '', 'option' => '',
        'object' => '', 'params' => '', 'embed' => '',
    ];

    /**
     * Always wrap Close Tags.
     *
     * @var array
     */
    private $_wrapClose = ['select' => ''];

    /**
     * Leaving Open Tags.
     *
     * @var array
     */
    private $_leaveOpen = [
        'input' => '', 'select' => '', 'label' => '', 'span' => '', 'a' => '', 'br' => '', 'hr' => '',
        'svg' => '',
    ];

    /**
     * Leaving Close Tags.
     *
     * @var array
     */
    private $_leaveClose = [
        'div' => '', 'span' => '', 'li' => '', 'td' => '',
    ];

    /**
     * Preformatted Tags.
     *
     * @var array
     */
    private $_preformatTags = ['pre' => '', 'textarea' => ''];

    /**
     * Script Tags.
     *
     * @var array
     */
    private $_scriptTags = ['script' => '', 'style' => ''];

    /**
     * CDATA Tags.
     *
     * @var array
     */
    //private $_cdataTags = array('script'=>'', 'style'=>'');

    /**
     * Empty Tags.
     *
     * @var array
     */
    private $_emptyTags = [];
    private $_xmlTags = ['svg' => ''];
    private $inXML = false;

    /**
     * Page splitter.
     *
     * @var array
     */
    private $_splitter = [];

    /**
     * Object constructor.
     *
     * @param string $source
     */
    public function __construct($source, $pi = null, $dtd = null, $splitter = null)
    {
        if (!is_null($splitter)) {
            $this->_splitter = $splitter;
        }

        $this->_orgSource = $source;
        $this->_pi = $pi;
        $this->_dtd = $dtd;

        $this->_lineBreak = $this->_getLineBreak();

        $this->_escapeEntityReference();
        $this->_escapeCdata();

        $this->_processingInstruction();
        $this->_doctype();

        $this->_parser = xml_parser_create();
        //
        xml_set_object($this->_parser, $this);
        //
        xml_set_element_handler($this->_parser, '_handleStart', '_handleEnd');
        xml_set_character_data_handler($this->_parser, '_handleChar');
        xml_set_notation_decl_handler($this->_parser, '_handleDoctype');
        xml_set_processing_instruction_handler($this->_parser, '_handleXmldecl');
        xml_set_external_entity_ref_handler($this->_parser, '_externalEntityRef');
        xml_set_unparsed_entity_decl_handler($this->_parser, '_unparsedEntityDecl');
        xml_set_default_handler($this->_parser, '_handleDefault');
        //
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->_parser, XML_OPTION_SKIP_WHITE, 0);
        //
        if (1 !== xml_parse($this->_parser, $this->_orgSource)) {
            $this->_formatted = $this->_orgSource;
        }

        if (empty($this->_formatted)) {
            $this->_formatted = $this->_orgSource;
        }

        xml_parser_free($this->_parser);
    }

    /**
     * get linebreak character.
     *
     * @return string
     */
    private function _getLineBreak()
    {
        return (preg_match("/(\r\n|\r|\n)/", $this->_orgSource, $lb)) ? $lb[0] : '';
    }

    /**
     * XML Parsing default handler.
     *
     * @param resource $parser
     * @param string   $data
     */
    private function _handleDefault($parser, $data)
    {
        if (isset($this->_splitter['id']) && !isset($this->_splitter['level'])) {
            return;
        }

        // Decl
        if (stripos($data, '<?xml') !== false) {
            return;
        }
        // DTD
        if ($data === '<!DOCTYPE' && !is_null($this->_dtd)) {
            $this->_startdtd = true;
        }
        if ($this->_startdtd === true) {
            return;
        }
        if (preg_match("/(<!\[CDATA\[|\]\]>)/i", $data)) {
            return;
        }
        if ($this->_currentType === 'linebreak' && strpos($data, '<!--') === 0) {
            $this->_insertLinebreak();
        }
        if (strpos($data, '<!--nowrap[') === 0) {
            $this->_skipWrap = true;
        } else {
            $this->_formatted .= $data;
        }
        $this->_currentType = 'default';
        $this->_currentContent = $data;
    }

    /**
     * XML Parsing XML Decl handler.
     *
     * @param resource $parser
     * @param string   $target
     * @param string   $data
     */
    private function _handleXmldecl($parser, $target, $data)
    {
        if (isset($this->_splitter['id']) && !isset($this->_splitter['level'])) {
            return;
        }

        if (!is_null($this->_pi)) {
            return;
        }
        $this->_formatted .= '<?xml'.
                             ' varsion="'.$target.'"'.
                             ' encoding="'.$data.'"'.
                             ' ?'.'>';
        $this->_currentType = 'decl';
        $this->_currentContent = null;
    }

    /**
     * XML Parsing DTD handler.
     *
     * @param resource $parser
     * @param string   $notation_name
     * @param string   $base
     * @param string   $system_id
     * @param string   $public_id
     */
    private function _handleDoctype($parser, $notation_name, $base, $system_id, $public_id)
    {
        if (isset($this->_splitter['id']) && !isset($this->_splitter['level'])) {
            return;
        }

        if (!is_null($this->_dtd)) {
            return;
        }
        if ($this->_currentType === 'decl') {
            $this->_insertLinebreak();
        }
        $this->_formatted .= '<!DOCTYPE';
        $this->_formatted .= ' '.$notation_name;
        if ($public_id) {
            $this->_formatted .= ' PUBLIC';
        }
        if ($public_id) {
            $this->_formatted .= ' "'.$public_id.'"';
        }
        if ($public_id) {
            $this->_formatted .= ' "'.$system_id.'"';
        }
        $this->_formatted .= '>';
        $this->_currentType = 'dtd';
        $this->_currentContent = null;
    }

    /**
     * XML Parsing opentag handler.
     *
     * @param resource $parser
     * @param string   $name    Tag name
     * @param array    $attribs Attributes
     */
    private function _handleStart($parser, $name, $attribs)
    {
        if (isset($this->_splitter['id'])) {
            //
            if (!isset($this->_splitter['level'])) {
                if ($attribs['id'] === $this->_splitter['id']) {
                    $this->_splitter['level'] = $this->_level;
                    ++$this->_level;
                }
                if (isset($this->_splitter['ischildren']) && $this->_splitter['ischildren'] === true) {
                    return;
                }
            } else {
                if ($this->_splitter['level'] === $this->_level) {
                    unset($this->_splitter['level']);
                }
            }
        }
        if (isset($this->_splitter['id']) && !isset($this->_splitter['level'])) {
            return;
        }

        $name = strtolower($name);
        if (isset($this->_wrapAlways[$name]) || $this->inXML) {
            $this->_insertLinebreak();
        }
        if (isset($this->_wrapOpen[$name])) {
            if ($this->_skipWrap) {
                $this->_skipWrap = false;
            } else {
                $this->_insertLinebreak();
            }
        }
        if ($this->_currentType === 'linebreak' && isset($this->_leaveOpen[$name])) {
            $this->_insertLinebreak();
        }

        if ($this->_startdtd === true) {
            $this->_startdtd = false;
            $this->_formatted = rtrim($this->_formatted, "\r\n");
            $this->_insertLinebreak();
        }
        $this->_formatted .= '<'.$name;
        foreach ($attribs as $key => $value) {
            // skip namespace
            if (strtolower($key) === 'xmlns:p5') {
                continue;
            }
            $value = str_replace("\n", '%0A', $value);
            $value = str_replace("\r", '%0D', $value);
            if ($key === $value) {
                $this->_formatted .= ' '.$key;
            } else {
                $this->_formatted .= ' '.$key.'="'.$value.'"';
            }
        }
        $slash = (isset($this->_emptyTags[$name])) ? $this->_xhtmlCloser : '';
        $this->_formatted .= $slash.'>';

        if (isset($this->_preformatTags[$name])) {
            $this->_preformatted = 1;
        }
        if (isset($this->_scriptTags[$name])) {
            $this->_preformatted = 2;
        }
        if ($this->_preformatted === 2) {
            $this->_scriptCode = '';
        }
        $this->_currentType = 'open';
        $this->_currentContent = $name;
        if ($this->_preformatted === 0) {
            ++$this->_level;
        }

        if (isset($this->_xmlTags[$name])) {
            $this->inXML = true;
        }
    }

    /**
     * XML Parsing closetag handler.
     *
     * @param resource $parser
     * @param string   $name   Tag name
     */
    private function _handleEnd($parser, $name)
    {
        if (isset($this->_splitter['id'])) {
            //
            if ($this->_splitter['level'] === $this->_level - 1) {
                unset($this->_splitter['level']);
            }
        }
        if (isset($this->_splitter['id']) && !isset($this->_splitter['level'])) {
            return;
        }

        $name = strtolower($name);

        if ($this->_preformatted === 2) {
            if (preg_match("/(\n+[ ]*)/s", $this->_scriptCode, $match)) {
                $ws = $this->_indent(1);
                $pattern = '/'.preg_quote($match[1], '/').'/s';
                $this->_scriptCode = preg_replace($pattern, "\n$ws", $this->_scriptCode);
            }
            if (preg_match("/(\n+[ ]*)$/s", $this->_scriptCode, $match)) {
                $ws = $this->_indent();
                $pattern = '/'.preg_quote($match[1], '/').'$/s';
                $this->_scriptCode = preg_replace($pattern, "\n$ws", $this->_scriptCode);
            }
            $this->_formatted .= $this->_scriptCode;
            $this->_scriptCode = null;
        }

        // node is empty
        $nowrap = ($this->_currentType === 'open' && $this->_currentContent === $name);

        if ($this->_preformatted === 0) {
            --$this->_level;
        }

        if ($this->_currentType === 'linebreak' && isset($this->_leaveOpen[$name])) {
            $this->_insertLinebreak($nowrap);
        }

        if (isset($this->_wrapClose[$name])) {
            $this->_insertLinebreak($nowrap);
        }
        if (!isset($this->_emptyTags[$name])) {
            if (isset($this->_wrapAlways[$name])) {
                $this->_insertLinebreak($nowrap);
            }
            if (isset($this->_leaveClose[$name])) {
                if ($this->_currentType === 'linebreak' ||
                    isset($this->_wrapAlways[$this->_currentContent]) ||
                    (isset($this->_wrapOpen[$this->_currentContent]) && $name !== 'td')
                ) {
                    $this->_insertLinebreak($nowrap);
                }
            }
            if ($this->inXML && $nowrap) {
                $this->_formatted = preg_replace('/>$/', '/>', $this->_formatted);
            } else {
                $this->_formatted .= "</$name>";
            }
        }
        if (isset($this->_preformatTags[$name])) {
            $this->_preformatted = 0;
        }
        if (isset($this->_scriptTags[$name])) {
            $this->_preformatted = 0;
        }
        $this->_currentType = 'close';
        $this->_currentContent = $name;

        if (isset($this->_xmlTags[$name])) {
            $this->inXML = false;
        }
    }

    /**
     * XML Parsing character data handler.
     *
     * @param resource $parser
     * @param string   $data
     */
    private function _handleChar($parser, $data)
    {
        if (isset($this->_splitter['id']) && !isset($this->_splitter['level'])) {
            return;
        }

        $data = str_replace("\t", str_repeat($this->_tabSpace, $this->_tabSize), $data);
        $data = str_replace(["\r\n", "\r"], "\n", $data);

        if ($this->_preformatted === 2) {
            $this->_scriptCode .= $data;

            return;
        }

        // Escape Line Break
        if (preg_match("/^[\n]+\s*$/", $data) && $this->_preformatted === 0) {
            $this->_currentType = 'linebreak';
            $this->_currentContent = $data;

            return;
        }

        if (preg_match("/^[\n]+\s*/", $data) && $this->_preformatted === 0) {
            if (($this->_currentType === 'open' || $this->_currentType === 'close') &&
                isset($this->_leaveOpen[$this->_currentContent])
            ) {
                $data = preg_replace("/^[\n]+\s*/", "\n".$this->_indent(), $data);
            }
        }

        // Escape White Spaces.
        if ($this->_currentType !== 'char' &&
            preg_match("/^[\s]+$/", $data) &&
            $this->_preformatted === 0
        ) {
            return;
        }

        if ($data === '&' || $data === '<' || $data === '>' ||
            $data === '"' || $data === "'") {
            $data = htmlspecialchars($data, ENT_QUOTES);
        } else {
            if ($this->_currentType === 'linebreak' || $this->_currentType === 'leavelinebreak') {
                if (!$this->_preformatted) {
                    $data = preg_replace("/^[\s]+/", '', $data);
                }
            }
        }

        if (preg_match("/[\n]+\s*$/", $data, $ws) && $this->_preformatted === 0) {
            $data = preg_replace("/([\n]+\s*)$/", '', $data);
            $this->_currentType = 'linebreak';
            $this->_currentContent = $ws[0];
        } else {
            $this->_currentType = 'char';
            $this->_currentContent = $data;
        }
        $this->_formatted .= $data;
    }

    /**
     * XML Parsing external entity reference handler.
     *
     * @param resource $parser
     * @param string   $open_entity_names
     * @param string   $base
     * @param string   $system_id
     * @param string   $public_id
     */
    private function _externalEntityRef($parser, $open_entity_names, $base, $system_id, $public_id)
    {
        if (isset($this->_splitter['id']) && !isset($this->_splitter['level'])) {
            return;
        }

        $this->_formatted .= $open_entity_names;
        $this->_currentType = 'entity';
        $this->_currentContent = $open_entity_names;
    }

    /**
     * XML Parsing unparsed entity reference handler.
     *
     * @param resource $parser
     * @param string   $entity_name
     * @param string   $base
     * @param string   $system_id
     * @param string   $public_id
     * @param string   $notation_name
     */
    private function _unparsedEntityDecl($parser, $entity_name, $base, $system_id, $public_id, $notation_name)
    {
        if (isset($this->_splitter['id']) && !isset($this->_splitter['level'])) {
            return;
        }

        $this->_formatted .= $entity_name;
        $this->_currentType = 'entity';
        $this->_currentContent = $entity_names;
    }

    /**
     * dropping original indent.
     *
     * @param string $data
     *
     * @return array
     */
    private function _dropIndent($data)
    {
        $min = 0;
        if (preg_match_all("/((\r\n|\r|\n)[\t ]*)/", $data, $ws)) {
            foreach ($ws[1] as $str) {
                $len = strlen($str);
                if (empty($min)) {
                    $min = $len;
                }
                if ($min <= $len) {
                    $regex = preg_quote($str, '/');
                }
            }
            $data = preg_replace("/$regex/", $this->_lineBreak, $data);
            $isset = true;
        }

        return [$data, isset($isset)];
    }

    /**
     * indent.
     *
     * @param int $offset
     *
     * @return string
     */
    private function _indent($offset = 0)
    {
        $offset += $this->_level;
        if ($offset < 0) {
            $offset = 0;
        }

        return str_repeat(str_repeat($this->_tabSpace, $this->_tabSize), $offset);
    }

    /**
     * getter HTML source.
     *
     * @return string
     */
    public function toString()
    {
        $this->_rewindEntityReference();

        return $this->_formatted;
    }

    /**
     * append XML Document type.
     */
    private function _doctype()
    {
        if (is_object($this->_dtd)) {
            if (!empty($this->_formatted)) {
                $this->_formatted .= $this->_lineBreak;
            }
            $this->_formatted .= '<!DOCTYPE';
            if (!empty($this->_dtd->name)) {
                $this->_formatted .= ' '.$this->_dtd->name;
            }
            if (!empty($this->_dtd->publicId)) {
                $this->_formatted .= ' PUBLIC';
                if (preg_match('/XHTML/i', $this->_dtd->publicId)) {
                    $this->_xhtmlCloser = ' /';
                }
                $this->_formatted .= ' "'.$this->_dtd->publicId.'"';
            }
            if (!empty($this->_dtd->systemId)) {
                $this->_formatted .= ' "'.$this->_dtd->systemId.'"';
            }
            $this->_formatted .= '>';
        }
    }

    /**
     * append XML Processing Instruction.
     */
    private function _processingInstruction()
    {
        if (is_object($this->_pi)) {
            $this->_formatted .= '<?xml';
            $this->_formatted .= ' version="'.$this->_pi->version.'"';
            if (isset($this->_pi->encoding)) {
                $this->_formatted .= ' encoding="'.$this->_pi->encoding.'"';
            }
            $this->_formatted .= ' ?'.'>';
        }
    }

    /**
     * Insert line break.
     */
    private function _insertLinebreak($nowrap = false)
    {
        if ($nowrap) {
            return;
        }
        $this->_formatted .= $this->_lineBreak;
        $this->_formatted .= $this->_indent();
    }

    /**
     * Tag Closer.
     *
     * @return mixed
     */
    public function getTagCloser()
    {
        return $this->_xhtmlCloser;
    }
}
