<?php
/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016-2019 PlusFive (http://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common\Xml;

use DOMDocument;
use ErrorException;

use Gsnowhawk\Common\Xml\Html;

/**
 * XML DOM class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <http://www.plus-5.com/>
 */
class Dom
{
    /**
     * DOMDocument object.
     *
     * @ver DOMDocument
     */
    protected $dom;

    /**
     * Skip WhiteSpace.
     *
     * @var bool
     */
    private $skipWhiteSpace = false;

    /**
     * XML Processing Instruction.
     *
     * @var bool
     */
    private $pi = false;

    /**
     * Error message.
     *
     * @var string
     */
    private $error = '';

    /**
     * flag for Parse error
     *
     * @var bool
     */
    protected $parseError = false;

    /**
     * Object constructor.
     *
     * @param mixed $source
     * @param bool  $ishtml
     */
    public function __construct($source, $ishtml = false, $exception = false)
    {
        $this->pi = preg_match("/<\?xml[^>]+>/", $source);
        $this->dom = $this->load($source, $exception);
    }

    /**
     * Getter Method.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        if (true === property_exists($this->dom, $key)) {
            return $this->dom->$key;
        }

        return;
    }

    /**
     * Reload source to DOM Document.
     *
     * @param string $template
     */
    public function reload($template)
    {
        $this->pi = preg_match("/<\?xml[^>]+>/", $template);
        $this->dom = $this->load($template);
    }

    /**
     * Load source to DOM Document.
     *
     * @param string $template
     * @return mixed
     */
    public function load($template, $exception = false)
    {
        clearstatcache();
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = !$this->skipWhiteSpace;

        try {
            $source = (is_file($template)) ? file_get_contents($template) : $template;
        } catch (ErrorException $e) {
            $message = $e->getMessage();
            if (stripos($message, 'File name is longer than the maximum allowed path length on this platform') !== false
                || stripos($message, 'open_basedir restriction in effect.') !== false
                || stripos($message, 'Unable to find the wrapper') !== false
            ) {
                $source = $template;
            } else {
                throw new ErrorException($message);
            }
        }

        if (!empty($source) || $source === '0') {
            $firstline_format = '';
            if (preg_match("/^([\r\n]+)/", $source, $match)) {
                $firstline_format = $match[1];
            }
            $source = Html::escapeEntityReference($source);
            // if source is plain text
            if (!preg_match("/^[\s]*</", $source) || !empty($firstline_format)) {
                $source = "<dummy>$source</dummy>";
            } elseif (!preg_match('/<.+?'.'>/', $source)) {
                return $dom->createTextNode($source);
            }
            $old_error_handler = set_error_handler([$this, 'errorHandler']);
            try {
                $dom->loadXML($source);
            } catch (DomException $e) {
                if (preg_match('/junk after document element/', $e->getMessage())
                    || preg_match('/Extra content at the end of the document in Entity/i', $e->getMessage())
                ) {
                    $xml = "<dummy>$source</dummy>";
                    $dom = $this->load($xml);
                } elseif (preg_match("/Namespace prefix ([^\s]+)/", $e->getMessage(), $match)) {
                    switch (strtolower($match[1])) {
                        case 'rdf':
                            $ns_uri = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
                            break;
                        case 'dc':
                            $ns_uri = 'http://purl.org/dc/elements/1.1/';
                            break;
                        default:
                            $ns_uri = '';
                            if (defined('XML_NAMESPACE') && defined('XML_NAMESPACE_URI') && XML_NAMESPACE === $match[1]) {
                                $ns_uri = XML_NAMESPACE_URI;
                            }
                            break;
                    }
                    if (preg_match("/^[\s]*<dummy[\s]+/is", $source)) {
                        $xml = preg_replace("/^[\s]*<dummy([\s]+)/is", "<dummy xmlns:{$match[1]}=\"$ns_uri\"$1", $source);
                    } else {
                        $xml = "<dummy xmlns:{$match[1]}=\"$ns_uri\">$source</dummy>";
                    }
                    $dom = $this->load($xml);
                } elseif (preg_match('/Space required after the Public Identifier in Entity/', $e->getMessage(), $match)) {
                    $xml = preg_replace_callback("/<!DOCTYPE\s+([^>]+)>/i", [$this, 'setPi'], $source);
                    $dom = $this->load($xml);
                } elseif (preg_match('/PCDATA invalid Char value/i', $e->getMessage(), $match)) {
                    $xml = preg_replace('/(?![\r\n\t])[[:cntrl:]]/', '', $source);
                    $dom = $this->load($xml);
                } elseif (preg_match('/Opening and ending tag mismatch:/', $e->getMessage()) ||
                         preg_match("/expected '>' in Entity, line:/", $e->getMessage()) ||
                         preg_match('/Specification mandate value for attribute .+ in Entity, line:/', $e->getMessage()) ||
                         preg_match('/xmlParseEntityRef: no name in Entity, line:/', $e->getMessage())
                ) {
                    throw new DomException($e->getMessage());
                } else {
                    $this->error = $e->getMessage();
                }
            }
            set_error_handler($old_error_handler);
            if (!empty($this->error)) {
                if (false !== $exception) {
                    throw new DomException($this->error);
                }
                trigger_error($this->error, E_USER_ERROR);
            }
        } else {
            $this->parseError = true;
        }

        return $dom;
    }

    /**
     * Get parant node.
     *
     * @param DOMElement $element
     * @param string     $node_name
     * @param string     $class_name
     * @return mixed
     */
    public static function getParentNode(DOMElement $element, $node_name, $class_name = '')
    {
        while ($parent = $element->parentNode) {
            if (!empty($class_name) && $parent->hasAttribute('class')) {
                $classes = preg_split("/[\s]+/", $parent->getAttribute('class'));
                foreach ($classes as $class) {
                    if ($class === $class_name) {
                        return $parent;
                    }
                }
            }
            if ($parent->nodeName === $node_name) {
                return $parent;
            }
            $element = $parent;
        }

        return;
    }

    /**
     * Get Chiled Nodes.
     *
     * @return mixed
     */
    public function getChildNodes()
    {
        return $this->dom->childNodes;
    }

    /**
     * Get elements.
     *
     * @param string $id
     * @param string $attr
     * @return mixed
     */
    public function getElementById($id, $attr = 'id')
    {
        self::_setIdAttrs($this->dom, $attr);

        return $this->dom->getElementById($id);
    }

    /**
     * Get elements.
     *
     * @param string $tagName
     * @return mixed
     */
    public function getElementsByTagName($tagName)
    {
        return $this->dom->getElementsByTagName(strtolower($tagName));
    }

    /**
     * Get elements.
     *
     * @param string $url
     * @param string $tagName
     * @return mixed
     */
    public function getElementsByTagNameNS($url, $tagName)
    {
        return $this->dom->getElementsByTagNameNS($url, strtolower($tagName));
    }

    /**
     * Insert child node.
     *
     * @param mixed  $node    Source code or DOMElement
     * @param object $refNode
     * @return mixed
     */
    public function insertBefore($node, \DOMNode $refNode)
    {
        if (is_string($node)) {
            $node = $this->importChild($node);
        }
        $parentNode = $refNode->parentNode;
        if (!is_object($parentNode)) {
            return;
        }
        if (is_array($node) || method_exists($node, 'item')) {
            $imported = [];
            foreach ($node as $child) {
                $ret = $parentNode->insertBefore($child, $refNode);
                $imported[] = $ret;
            }
            if (count($imported) > 1) {
                return new Dom\NodeList($imported);
            } elseif (count($imported) > 0) {
                return $imported[0];
            }

            return;
        }

        return $parentNode->insertBefore($node, $refNode);
    }

    /**
     * Insert after child node.
     *
     * @param mixed  $node    Source code or DOMElement
     * @param object $refNode
     * @return mixed
     */
    public function insertAfter($node, \DOMNode $refNode)
    {
        if (is_string($node)) {
            $node = $this->importChild($node);
        }
        $parentNode = $refNode->parentNode;
        if (!is_object($parentNode)) {
            return;
        }
        $nextSibling = $refNode->nextSibling;
        if (is_array($node) || method_exists($node, 'item')) {
            $imported = [];
            foreach ($node as $child) {
                if (is_object($nextSibling)) {
                    $ret = $parentNode->insertBefore($child, $nextSibling);
                } else {
                    $ret = $parentNode->appendChild($child);
                }
                $imported[] = $ret;
            }
            if (count($imported) > 1) {
                return new Dom\NodeList($imported);
            } elseif (count($imported) > 0) {
                return $imported[0];
            }

            return;
        }
        if (is_object($nextSibling)) {
            $ret = $parentNode->insertBefore($node, $nextSibling);
        } else {
            $ret = $parentNode->appendChild($node);
        }

        return $ret;
    }

    /**
     * Append Comment.
     *
     * @param string $node
     * @param object $refNode
     * @param string $lf
     * @return mixed
     */
    public function appendComment($data, $refNode, $lf = '')
    {
        $com = $refNode->appendChild($this->dom->createComment($data));
        if (!empty($lf)) {
            $this->insertBefore($this->dom->createTextNode($lf), $com);
        }
    }

    /**
     * Append child node.
     *
     * @param mixed  $node    Source code or XML::DOM::Element
     * @param object $refNode
     * @return mixed
     */
    public function appendChild($node, $refNode)
    {
        if (is_scalar($node)) {
            $node = $this->importChild($node);
        }
        if (is_array($node) || method_exists($node, 'item')) {
            $imported = [];
            foreach ($node as $child) {
                if (is_object($refNode)) {
                    $ret = $refNode->appendChild($child);
                    $imported[] = $ret;
                }
            }
            if (count($imported) > 1) {
                return new Dom\NodeList($imported);
            } elseif (count($imported) > 0) {
                return $imported[0];
            }

            return;
        }

        return $refNode->appendChild($node);
    }

    /**
     * Remove child node.
     *
     * @param object $node
     * @param bool   $recursive
     * @return mixed
     */
    public function removeChild($node, $recursive = false)
    {
        if (!is_object($node)) {
            return;
        }
        if (get_class($node) === 'Dom\\NodeList' || get_class($node) === 'DOMNodeList') {
            for ($last = $node->length - 1, $i = $last; $i >= 0; --$i) {
                if (false === $node->item($i)->parentNode->removeChild($node->item($i))) {
                    return false;
                }
            }

            return true;
        }
        if ($recursive !== true) {
            return $node->parentNode->removeChild($node);
        }

        return $this->cleanUpNode($node);
    }

    /**
     * Clean up childnodes.
     *
     * @param DOMElement $node
     * @return mixed
     */
    private function cleanUpNode($node)
    {
        while ($node->hasChildNodes()) {
            $this->cleanUpNode($node->firstChild);
        }

        return $node->parentNode->removeChild($node);
    }

    /**
     * Import child node.
     *
     * @param mixed  $node    Source code or DOMElement
     * @param object $refNode
     * @return object
     */
    public function replaceChild($node, $refNode)
    {
        if (is_string($node)) {
            $node = $this->importChild($node);
        }
        if (get_class($refNode) === 'DOMNodeList') {
            while ($refNode->length > 1) {
                $refNode->item(0)->parentNode->removeChild($refNode->item(0));
            }
            $refNode = $refNode->item(0);
        }
        $parent = $refNode->parentNode;
        if (is_null($parent)) {
            return;
        }
        if (is_array($node) || method_exists($node, 'item')) {
            $imported = [];
            foreach ($node as $child) {
                $ret = $parent->insertBefore($child, $refNode);
                $imported[] = $ret;
            }
            $parent->removeChild($refNode);
            if (count($imported) > 1) {
                return new Dom\NodeList($imported);
            } elseif (count($imported) > 0) {
                return $imported[0];
            }

            return;
        }

        return $parent->replaceChild($node, $refNode);
    }

    /**
     * Import child node.
     *
     * @param mixed $node Source code or DOMElement
     * @return object
     */
    public function importChild($node)
    {
        if (is_scalar($node)) {
            $node = $this->load($node);
            $node = $node->childNodes;
        }
        if (property_exists($node, 'length')) {
            $imported = [];
            foreach ($node as $child) {
                if ($child->nodeName === 'dummy') {
                    $children = $child->childNodes;
                    foreach ($children as $childNode) {
                        $imported[] = $this->dom->importNode($childNode, true);
                    }
                    continue;
                }
                $imported[] = $this->dom->importNode($child, true);
            }

            return new Dom\NodeList($imported, true);
        }

        return $this->dom->importNode($node, true);
    }

    /**
     * Create new DOM node.
     *
     * @param string $tag
     * @param string $value
     * @return object
     */
    public function createElement($name, $value = '')
    {
        return $this->dom->createElement($name, $value);
    }

    /**
     * Create new Text node.
     *
     * @param string $text
     * @return object
     */
    public function createTextNode($text)
    {
        return $this->dom->createTextNode($text);
    }

    /**
     * Processing Instruction.
     * @return mixed
     */
    public function processingInstruction()
    {
        if ($this->pi) {
            return (object) [
                'version' => $this->dom->xmlVersion,
                'encoding' => $this->dom->xmlEncoding,
            ];
        }

        return;
    }

    /**
     * Doctype.
     *
     * @return mixed
     */
    public function doctype()
    {
        if (is_object($this->dom->doctype)) {
            return $this->dom->doctype;
        }

        return;
    }

    /**
     * Error message (Read only).
     *
     * @return string
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * Using getElementById.
     *
     * @param DOMNode $node
     * @param string  $attr
     */
    private static function _setIdAttrs(\DOMNode $node, $attr)
    {
        foreach ($node->childNodes as $cn) {
            if ($cn->hasAttributes()) {
                if ($cn->hasAttribute($attr)) {
                    // Important
                    if (false === $cn->getAttributeNode('id')->isID()) {
                        $cn->setIdAttribute($attr, true);
                    }
                }
            }
            if ($cn->hasChildNodes()) {
                self::_setIdAttrs($cn, $attr);
            }
        }
    }

    /**
     * Save XML.
     *
     * @param DOMNode
     * @return string
     */
    public function saveXML($node)
    {
        return $this->dom->saveXML($node);
    }

    /**
     * Custom Error Handler.
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     * @param array  $errcontext
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        throw new DomException($errstr, $errno, 0, $errfile, $errline);
    }
}
