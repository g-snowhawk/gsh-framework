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

use Gsnowhawk\Common\Xml\Html;

/**
 * HTML form elements class.
 *
 * @license  http://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class Element extends Html
{
    /**
     * XML Parser object.
     *
     * @var resource
     */
    private $_parser;

    /**
     * Formatted Source.
     *
     * @var array
     */
    private $_elements = [];

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
    private $_tabSpace = [];

    /**
     * Tab size.
     *
     * @var int
     */
    private $_tabSize = 4;

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
    private $_isWrap = false;

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
     * Always wrap Close Tags.
     *
     * @var array
     */
    private $_tags = ['input', 'textarea', 'select', 'button'];

    /**
     * Leaving Open Tags.
     *
     * @var array
     */
    private $_leaveOpen = ['input', 'a', 'br', 'hr'];

    /**
     * Object constructor.
     *
     * @param string $source
     */
    public function __construct($source, $pi = null, $dtd = null, $enc = null)
    {
        //$this->_emptyTags = parent::$emptyTags;

        $this->_orgSource = self::htmlToXml($source, true);
        $this->_pi = $pi;
        $this->_dtd = $dtd;

        //$this->_lineBreak = $this->_getLineBreak();

        $this->_escapeEntityReference();
        //$this->_escapeCdata();

        $this->_processingInstruction();
        $this->_doctype();

        $this->_parser = xml_parser_create();
        //
        xml_set_object($this->_parser, $this);
        //
        xml_set_element_handler($this->_parser, '_handleStart', '_handleEnd');
        //xml_set_character_data_handler($this->_parser, '_handleChar');
        //xml_set_notation_decl_handler($this->_parser, '_handleDoctype');
        //xml_set_processing_instruction_handler($this->_parser, '_handleXmldecl');
        //xml_set_external_entity_ref_handler($this->_parser, '_externalEntityRef');
        //xml_set_unparsed_entity_decl_handler($this->_parser, '_unparsedEntityDecl');
        //xml_set_default_handler($this->_parser, '_handleDefault');
        //
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->_parser, XML_OPTION_SKIP_WHITE, 0);
        //
        if (1 !== xml_parse($this->_parser, $this->_orgSource)) {
            //exit(xml_error_string(xml_get_error_code($this->_parser)));
        }

        xml_parser_free($this->_parser);
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
        $name = strtolower($name);
        if (in_array($name, $this->_tags)) {
            $attribs['tag'] = $name;
            array_push($this->_elements, $attribs);
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
    }

    /**
     * escape HTML entities.
     */
    private function _escapeEntityReference()
    {
        $this->_orgSource = parent::escapeEntityReference($this->_orgSource);
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
     * Read only properties.
     *
     * return array
     */
    public function elements()
    {
        return (array) $this->_elements;
    }
}
